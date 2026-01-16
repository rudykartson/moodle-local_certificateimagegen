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
        global $DB, $CFG;

        require_once($CFG->libdir . '/filelib.php');
        require_once($CFG->dirroot . '/mod/customcert/lib.php');

        $data = $this->get_custom_data();
        $userid    = $data->userid ?? null;
        $courseid  = $data->courseid ?? null;
        $objectid_id  = $data->objectid ?? null;
        $issues_cert = $DB->get_record('customcert_issues', ['id' => $objectid_id]);
        $objectid = $issues_cert->code;

        // ---------- LOG FUNCTION ----------
        $logfile = '/var/www/html/moodle/local/certimagegen/logs/course_complete.log';
        $log = function($message) use ($logfile) {
            file_put_contents($logfile, date('Y-m-d H:i:s') . " - $message\n", FILE_APPEND);
        };
        // -----------------------------------

        $log("Course Completed Event:\n" . print_r($data, true));

        if (!$userid || !$courseid || !$objectid) {
            $log("Missing required data: userid={$userid}, courseid={$courseid}, objectid={$objectid}");
            return;
        }

        try {
            $customcert = $DB->get_record('customcert', ['course' => $courseid,'id' => $issues_cert->customcertid,]);
            $templatedata = $DB->get_record('customcert_templates', ['id' => $customcert->templateid]);
            $templateid = $templatedata->id;

            $template = new template($templatedata);

            $pdfpath = $CFG->tempdir . "/customcert_user_{$userid}_template_{$templateid}.pdf";
            $pdfdata = $template->generate_pdf('S', $userid, true);
            file_put_contents($pdfpath, $pdfdata);

            if (filesize($pdfpath) < 100) {
                $log("Generated PDF is too small. Possible failure. Path: $pdfpath");
                return;
            }

            $context = \context_system::instance();
            $component = 'local_certimagegen';
            $filearea  = 'content';
            $itemid    = 0;
            $filename  = 'certimage_' . $objectid . '.jpg';

            $pdftoppmPath = trim(shell_exec('command -v pdftoppm'));

            if (empty($pdftoppmPath)) {
                $log("pdftoppm is not installed or not in system PATH.");
                return;
            }

            $outputBase = $CFG->tempdir . "/certimage_" . $objectid; // no extension
            $pdfEscaped = escapeshellarg($pdfpath);
            $outputEscaped = escapeshellarg($outputBase);
            $command = "$pdftoppmPath -jpeg -r 150 -f 1 -singlefile -jpegopt jpeg-quality=30 $pdfEscaped $outputEscaped";

            $log("Executing command: $command");

            exec($command, $cmdOutput, $return_var);

            $log("Command return code: $return_var");
            if (!empty($cmdOutput)) {
                $log("Command output:\n" . implode("\n", $cmdOutput));
            }

            if ($return_var !== 0) {
                $log("pdftoppm failed with exit code $return_var");
                return;
            }

            $imagePath = $outputBase . ".jpg"; 

            if (!file_exists($imagePath)) {
                $log("Expected image file not found: $imagePath");
                return;
            }

            $imagecontent = file_get_contents($imagePath);
            $fs = get_file_storage();

            if ($existing = $fs->get_file($context->id, $component, $filearea, $itemid, '/', $filename)) {
                $existing->delete(); // safer than raw DB delete
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
            $log("✅ Image generated and stored: $filename");

        } catch (\Exception $e) {
            $log("❌ Exception: " . $e->getMessage());
        }
    }
}
