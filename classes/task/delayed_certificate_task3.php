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
 * @copyright   2025 Rudraksh Batra <batra.rudraksh@gmail.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_certimagegen\task;

defined('MOODLE_INTERNAL') || die();

use core\task\adhoc_task;
use mod_customcert\template;

class delayed_certificate_task extends adhoc_task {

    public function execute() {
        $this->run_direct($this->get_custom_data());
    }

    public static function run_direct($data) {
        global $DB, $CFG;

        require_once($CFG->libdir . '/filelib.php');
        require_once($CFG->dirroot . '/mod/customcert/lib.php');

        //--------------log code------------------
        $logdata = print_r($data, true);
        $logfile = '/var/www/html/moodle/local/certimagegen/logs/course_complete.log';
        file_put_contents($logfile, date('Y-m-d H:i:s') . " - Course Completed Event:\n" . $logdata . "\n\n", FILE_APPEND);
        //--------------end log code------------------ 

        $userid    = $data->userid ?? null;
        $courseid  = $data->courseid ?? null;
        $objectid_id = $data->objectid ?? null;

        $issues_cert = $DB->get_record('customcert_issues', ['id' => $objectid_id]);
        $objectid = $issues_cert->code ?? null;

        if (!$userid || !$courseid || !$objectid) {
            mtrace("Missing data for delayed_certificate_task");
            return;
        }

        $customcert = $DB->get_record('customcert', ['course' => $courseid], '*', MUST_EXIST);
        $templatedata = $DB->get_record('customcert_templates', ['id' => $customcert->templateid], '*', MUST_EXIST);
        $templateid = $templatedata->id;

        file_put_contents($logfile, date('Y-m-d H:i:s') . " - Course Completed Event2:\n" . $logdata . "\n\n", FILE_APPEND);

        $template = new template($templatedata);

        $pdfpath = $CFG->tempdir . "/customcert_user_{$userid}_template_{$templateid}.pdf";
        $pdfdata = $template->generate_pdf('S', $userid, true);
        file_put_contents($pdfpath, $pdfdata);

        $context = \context_system::instance();
        $component = 'local_certimagegen';
        $filearea  = 'content';
        $itemid    = 0;
        $filename  = 'certimage_' . $objectid . '.jpg';

        $pdftoppmPath = trim(shell_exec('command -v pdftoppm'));
        if (empty($pdftoppmPath)) {
            throw new \Exception("pdftoppm is not installed or not in the system PATH.");
        }

        file_put_contents($logfile, date('Y-m-d H:i:s') . " - Course Completed Event3:\n" . $logdata . "\n\n", FILE_APPEND);

        try {
            $outputBase = $CFG->tempdir . "/certimage_" . $objectid;
            $pdfEscaped = escapeshellarg($pdfpath);
            $outputEscaped = escapeshellarg($outputBase);

            $command = "$pdftoppmPath -jpeg -r 150 -f 1 -singlefile -jpegopt jpeg-quality=30 $pdfEscaped $outputEscaped";
            exec($command, $output, $return_var);

            file_put_contents($logfile, date('Y-m-d H:i:s') . " - Course Completed Event4:\n" . $logdata . "\n\n", FILE_APPEND);

            if ($return_var !== 0) {
                throw new \Exception("pdftoppm failed with exit code $return_var");
            }

            $imagePath = $outputBase . ".jpg";
            if (!file_exists($imagePath)) {
                throw new \Exception("Expected image file not found: $imagePath");
            }

            $imagecontent = file_get_contents($imagePath);
            $fs = get_file_storage();

            // Delete old image if it exists
            if ($existing = $fs->get_file($context->id, $component, $filearea, $itemid, '/', $filename)) {
                $existing->delete();
            }

            file_put_contents($logfile, date('Y-m-d H:i:s') . " - Course Completed Event5:\n" . $logdata . "\n\n", FILE_APPEND);

            $filerecord = [
                'contextid' => $context->id,
                'component' => $component,
                'filearea'  => $filearea,
                'itemid'    => $itemid,
                'filepath'  => '/',
                'filename'  => $filename
            ];

            $fs->create_file_from_string($filerecord, $imagecontent);
            mtrace("Image generated and stored: $filename");

        } catch (\Exception $e) {
            mtrace("Image conversion failed: " . $e->getMessage());
        }
    }
}
