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

require('../../config.php');

$issueid = required_param('issueid', PARAM_INT);

$issues_cert = $DB->get_record('customcert_issues', ['id' => $issueid], '*', MUST_EXIST);

$context   = context_system::instance();
$component = 'local_certimagegen';
$filearea  = 'content';
$itemid    = 0;
$filename  = 'certimage_' . $issues_cert->code . '.jpg';

$fs = get_file_storage();

/* 1. Serve image if exists */
if ($file = $fs->get_file($context->id, $component, $filearea, $itemid, '/', $filename)) {
    send_stored_file($file, 0, 0, true);
    exit;
}

/* 2. Queue adhoc task */
$task = new \local_certimagegen\task\generate_cert_image();
$task->set_custom_data((object)[
    'issueid' => $issueid,
]);
\core\task\manager::queue_adhoc_task($task);

/* 3. Serve placeholder */
$placeholder = __DIR__ . '/pix/placeholder.jpg';
header('Content-Type: image/jpeg');
readfile($placeholder);
exit;
