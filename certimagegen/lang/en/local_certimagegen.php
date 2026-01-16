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
defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Certificate Image Generator';

$string['pageheading'] = 'To retrieve the images of all certificates, the administrator must access the Certificates page.';

$string['customcert_required'] = 'Custom certificate is Required';
$string['customcert_missing'] = 'To utilize the features of our plugin, a "Custom Certificate" plugin is required. You can download and install the plugin from moodle website plugins directory';

$string['pdftoppm_required'] = 'Poppler library tool (pdftoppm) is Required';
$string['pdftoppm_missing'] = 'The Poppler library tool (pdftoppm) is not installed. Please install it on your server to enable certimagegen features.';

$string['header_instructions_title'] = 'Header Menu Requirement';
$string['header_instructions'] = 'To use the certificate feature, please add the following menu in your site\'s header via Site administration → Appearance → Advanced theme settings → Custom menu items:<br><pre>Certficates|{$a}</pre>';

$string['default_cert_image'] = 'Default Certificate Image';
$string['default_cert_image_missing'] = 'Default Certificate Image is Missing';
$string['default_cert_image_desc'] = 'Upload a default certificate image that will be used when no user-specific image is provided.';

$string['customtextlabel'] = 'Enter Shared Certificate Header Content';
$string['customtextdesc'] = 'Separate each line with a line break to ensure proper formatting.';

$string['customtextcardlabel'] = 'Enter Content Certificate Course Card';
$string['customtextcarddesc'] = 'Separate each line with a line break to ensure proper formatting.';

$string['messagelabel'] = 'Message for shared URL';
$string['messagedesc'] = 'Write the message to attach with your shared url.';

$string['sectionCertificates'] = 'Choose the course for the section name to appear';
$string['sectionCertificates_desc'] = 'Select the course for which you want to use the section name in place of the course name, For multiple select courses use (ctrl+select)';

$string['default_sharecert_color'] = 'Shared Certificate Color';
$string['default_sharecert_color_desc'] = 'Choose color for shared Certificate border';