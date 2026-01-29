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
// defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/../../config.php');

$code = required_param('code', PARAM_TEXT);
$issueid = intval($code);

$imagepath = "$CFG->dataroot/local_certimagegen/issue_$issueid.png";
$imageurl = new moodle_url('/pluginfile.php/1/local_certimagegen/issue_' . $issueid . '.png');

if (!file_exists($imagepath)) {
    throw new moodle_exception(get_string('certimage_not_found', 'local_certimagegen'));
}

$PAGE->set_context(context_system::instance());
$PAGE->set_pagelayout('popup');
$PAGE->set_title(get_string('shared_certificate','local_certimagegen'));
$PAGE->set_url(new moodle_url('/local/certimagegen/view.php', ['code' => $code]));

echo '<!DOCTYPE html><html><head>';
echo '<meta property="og:title" content="'.get_string('certificate_of_achievement', 'local_certimagegen').'"/>';
echo '<meta property="og:type" content="website"/>';
echo '<meta property="og:image" content="' . $imageurl . '"/>';
echo '<meta name="twitter:card" content="summary_large_image">';
echo '<style>body { text-align: center; padding: 20px; } img { max-width: 100%; height: auto; }</style>';
echo '</head><body>';
echo '<h3>'.get_string('here_is_the_certificate', 'local_certimagegen').'</h3>';
echo '<img src="' . $imageurl . '" alt="Certificate Image"/>';
echo '</body></html>';
