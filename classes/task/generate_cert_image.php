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

namespace local_certimagegen\task;

defined('MOODLE_INTERNAL') || die();

class generate_cert_image extends \core\task\adhoc_task {

    public function execute() {
        global $DB, $CFG;

        require_once($CFG->dirroot . '/mod/customcert/lib.php');

        $data = $this->get_custom_data();
        $issueid = $data->issueid ?? null;

        if (!$issueid) {
            return;
        }

        $issues_cert = $DB->get_record('customcert_issues', ['id' => $issueid], '*', MUST_EXIST);
        $context = \context_system::instance();
        $fs = get_file_storage();
        $filename = 'certimage_' . $issues_cert->code . '.jpg';

        // Prevent duplicate generation
        if ($fs->file_exists($context->id, 'local_certimagegen', 'content', 0, '/', $filename)) {
            return;
        }

        $issue = $DB->get_record('customcert_issues', ['id' => $issueid], '*', IGNORE_MISSING);
        if (!$issue) {
            return;
        }

        $customcert = $DB->get_record('customcert', ['id' => $issue->customcertid], '*', MUST_EXIST);
        $template   = $DB->get_record('customcert_templates', ['id' => $customcert->templateid], '*', MUST_EXIST);

        $tpl = new \mod_customcert\template($template);

        $pdfpath = $CFG->tempdir . "/cert_{$issues_cert->code}.pdf";
        file_put_contents($pdfpath, $tpl->generate_pdf('S', $issue->userid, true));

        $outputbase = $CFG->tempdir . "/cert_{$issues_cert->code}";
        exec("pdftoppm -jpeg -r 150 -singlefile " .
             escapeshellarg($pdfpath) . " " .
             escapeshellarg($outputbase));

        $imagepath = $outputbase . ".jpg";
        if (!file_exists($imagepath)) {
            @unlink($pdfpath);
            return;
        }

        $fs->create_file_from_pathname([
            'contextid' => $context->id,
            'component' => 'local_certimagegen',
            'filearea'  => 'content',
            'itemid'    => 0,
            'filepath'  => '/',
            'filename'  => $filename,
        ], $imagepath);

        @unlink($pdfpath);
        @unlink($imagepath);
    }
}
