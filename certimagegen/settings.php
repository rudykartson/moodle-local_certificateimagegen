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
 * Add page to admin menu.
 *
 * @package    local_certimagegen
 * @author Andreas Grabs <moodle@grabs-edv.de>
 * @copyright  Andreas Grabs
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

if ($hassiteconfig) {

    
    $pluginname = get_string('pluginname', 'local_certimagegen'); 

    $settings = new admin_settingpage('local_qbsettings', $pluginname);

    $settings->add(new admin_setting_heading('local_certimagegen_mainheading', '', get_string('pageheading', 'local_certimagegen')));

    $customcertpath = $CFG->dirroot . '/mod/customcert';


    if (!is_dir($customcertpath)) {
        $settings->add(new admin_setting_heading(
            'local_certimagegen/customcertwarning',
            get_string('customcert_required', 'local_certimagegen'),
            html_writer::tag('div', get_string('customcert_missing', 'local_certimagegen'), ['class' => 'alert alert-danger'])
        ));
    }

    // Check if Imagick is installed
    // if (!extension_loaded('imagick') || !class_exists('Imagick')) {
    //     $settings->add(new admin_setting_heading(
    //         'local_certimagegen/imagickwarning',
    //         get_string('imagick_required', 'local_certimagegen'),
    //         html_writer::tag('div', get_string('imagick_missing', 'local_certimagegen'), ['class' => 'alert alert-danger'])
    //     ));
    // }

    $pdftoppmPath = trim(shell_exec('command -v pdftoppm'));
    if (empty($pdftoppmPath)) {
        // throw new \Exception("pdftoppm is not installed or not in the system PATH.");
        $settings->add(new admin_setting_heading(
            'local_certimagegen/pdftoppmwarning',
            get_string('pdftoppm_required', 'local_certimagegen'),
            html_writer::tag('div', get_string('pdftoppm_missing', 'local_certimagegen'), ['class' => 'alert alert-danger'])
        ));
    }

    // Display instruction to include header script
    $certurl = new moodle_url('/local/certimagegen/certificates.php');
    $settings->add(new admin_setting_heading(
        'local_certimagegen/headerinfo',
        get_string('header_instructions_title', 'local_certimagegen'),
        html_writer::tag('div', get_string('header_instructions', 'local_certimagegen', $certurl->out(false)), ['class' => 'bg-warning'])
    ));

    // Upload default certificate image
    $settings->add(new admin_setting_configstoredfile(
        'local_certimagegen/defaultcertimage',
        get_string('default_cert_image', 'local_certimagegen'),
        get_string('default_cert_image_desc', 'local_certimagegen'),
        'defaultcertimage'
    )); 
    $fs = get_file_storage();
    $context = context_system::instance();
    $files = $fs->get_area_files($context->id, 'local_certimagegen', 'defaultcertimage', 0, 'itemid, filepath, filename', false);
    if (empty($files)) {
        $settings->add(new admin_setting_heading(
            'local_certimagegen/requirednotice',
            '',
            html_writer::div('<strong style="color: red;">' . get_string('default_cert_image_missing', 'local_certimagegen') . '</strong>')
        ));
    }

    $label = get_string('messagelabel', 'local_certimagegen');
    $desc = get_string('messagedesc', 'local_certimagegen');
    $settings->add(new admin_setting_configtext('local_certimagegen/shareurlmessage', $label, $desc, 'Check this out:'));

    // $settings->add(new admin_setting_configeditor(
    $settings->add(new admin_setting_confightmleditor(
        'local_certimagegen/customtextheadcontent', 
        get_string('customtextlabel', 'local_certimagegen'),
        get_string('customtextdesc', 'local_certimagegen'),
        'Masterclass Certificate of Completion - {username},
This certificate is proudly presented to {username} in recognition of their successful completion of the {activityname} activity as part of the {coursename} course.'
    ));

     $settings->add(new admin_setting_confightmleditor(
        'local_certimagegen/customtextbodycontent',
        get_string('customtextcardlabel', 'local_certimagegen'),
        get_string('customtextcarddesc', 'local_certimagegen'),
        'Enter a custom heading, or include placeholders like {coursename} or {activityname} for dynamic content.
Lorem ipsum dolor sit amet, consectetur adipiscing elit.
Lorem ipsum dolor sit amet, consectetur adipiscing elit.
Lorem ipsum dolor sit amet, consectetur adipiscing elit.
Lorem ipsum dolor sit amet, consectetur adipiscing elit.'
    ));

    $courses = $DB->get_records("course");
    $choices1 = [];
    foreach ($courses as $course) {
        $choices1[$course->id] = $course->fullname;
    }

    $settings->add(new admin_setting_configmultiselect(
        'local_certimagegen/allowSectionCertificates',
        get_string('sectionCertificates', 'local_certimagegen'),
        get_string('sectionCertificates_desc', 'local_certimagegen'),
        [],
        $choices1
    )); 

    $settings->add(new admin_setting_configcolourpicker(
        'local_certimagegen/defaultcertcolorborder',
        get_string('default_sharecert_color', 'local_certimagegen'),
        get_string('default_sharecert_color_desc', 'local_certimagegen'),
        '#fb0' // default color (white)
    ));


    $ADMIN->add('localplugins', $settings);

}