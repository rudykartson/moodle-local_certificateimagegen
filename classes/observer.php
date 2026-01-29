<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.
/**
 * @package     local_certimagegen
 * @copyright   2026 Rudraksh Batra <batra.rudraksh@gmail.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
    namespace local_certimagegen;

    defined('MOODLE_INTERNAL') || die();

    use mod_customcert\event\issue_created;
    use mod_customcert\template;

class observer {

    public static function on_certificate_issued(\mod_customcert\event\issue_created $event) {
        $data = $event->get_data();

        $task = new \local_certimagegen\task\generate_cert_image();
        $task->set_custom_data((object)[
            'issueid' => (int)$data['objectid'],
        ]);

        \core\task\manager::queue_adhoc_task($task);
    }
    
    public static function on_certificate_template_updated(\mod_customcert\event\template_updated $event) {
        global $DB, $CFG;
        require_once($CFG->libdir . '/filelib.php');
        require_once($CFG->dirroot . '/mod/customcert/lib.php');
        $evt = $event->get_data();
               
        $courseid = $evt['courseid'];
        $evtemplateid = $evt['objectid'];
            
        $customcerts = $DB->get_records_sql('SELECT c.id,c.course,c.templateid,c.name,ic.userid,ic.customcertid,ic.code FROM {customcert} c JOIN {customcert_issues} ic ON c.id = ic.customcertid WHERE c.course ='. $courseid.' AND c.templateid = '.$evtemplateid);

        $evtdatas = [];
        foreach ($customcerts as $key => $customcert) {
            $issueobj = $DB->get_record('customcert_issues', ['userid' => $customcert->userid,'customcertid'=>$customcert->id]);
            if($issueobj){
                $evtdatas[] = ["objectid"=>$issueobj->code,"userid"=>$issueobj->userid ,"templateid"=>$customcert->templateid];
            }
        }


        foreach ($evtdatas as $key => $evtdata) {
            $userid = $evtdata['userid'];
            $objectid = $evtdata['objectid'];
            $templateid = $evtdata['templateid'];
                
                // $customcert = $DB->get_record('customcert', ['course' => $courseid], '*', MUST_EXIST);
                $templatedata = $DB->get_record('customcert_templates', ['id' => $templateid]);          

                $template = new template($templatedata);

                $pdfpath = $CFG->tempdir . "/customcert_user_{$userid}_template_{$templateid}.pdf";
                $pdfdata = $template->generate_pdf('S', $userid, true);
                file_put_contents($pdfpath, $pdfdata);

                // === Configuration ===
                $context = \context_system::instance();
                $component = 'local_certimagegen';
                $filearea  = 'content';
                $itemid    = 0;
                $filename  = 'certimage_' . $objectid . '.jpg';

                $pdftoppmPath = trim(shell_exec('command -v pdftoppm'));
                if (empty($pdftoppmPath)) {
                    throw new \Exception("pdftoppm is not installed or not in the system PATH.");
                }

                try {

                    $outputBase = $CFG->tempdir . "/certimage_" . $objectid; // no extension
                    // $command = "pdftoppm -jpeg -f 1 -singlefile -jpegopt quality=10 $pdfpath $outputBase";

                    $pdfEscaped = escapeshellarg($pdfpath);
                    $outputEscaped = escapeshellarg($outputBase);
 
                    // Updated command with correct jpegopt
                    $command = "$pdftoppmPath -jpeg -r 150 -f 1 -singlefile -jpegopt jpeg-quality=30 $pdfEscaped $outputEscaped";


                    exec($command, $output, $return_var);

                    if ($return_var !== 0) {
                        throw new \Exception("pdftoppm failed with exit code $return_var");
                    }

                    $imagePath = $outputBase . ".jpg"; 

                    if (!file_exists($imagePath)) {
                        throw new \Exception("Expected image file not found: $imagePath");
                    }

                    $imagecontent = file_get_contents($imagePath);

                    $fs = get_file_storage();

                    if ($existing = $fs->get_file($context->id, $component, $filearea, $itemid, '/', $filename)) {

                        // $existing->delete();
                        $DB->delete_records('files', [
                            'contextid' => $context->id,
                            'component' => $component,
                            'filearea'  => $filearea,
                            'itemid'    => $itemid,
                            'filepath'  => '/',
                            'filename'  => $filename
                        ]);
                    }


                    $filerecord = [
                        'contextid' => $context->id,
                        'component' => $component,
                        'filearea'  => $filearea,
                        'itemid'    => $itemid,
                        'filepath'  => '/',
                        'filename'  => $filename 
                    ];

                    $storedfile = $fs->create_file_from_string($filerecord, $imagecontent);
                    debugging("Image generated and stored: {$filename}", DEBUG_DEVELOPER);
               
                } catch (\Exception $e) {
                    debugging("Image conversion failed: " . $e->getMessage(), DEBUG_DEVELOPER);
                }
     
        }
    }
}
