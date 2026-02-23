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
// defined('MOODLE_INTERNAL') || die();

require_once("../../config.php");
global $DB, $CFG, $USER, $PAGE, $OUTPUT;
require_once($CFG->libdir.'/filelib.php');
require_once($CFG->dirroot . '/mod/customcert/lib.php');

$code = required_param('code', PARAM_TEXT);
$currenturl = $PAGE->url->out(false);
$theme = $PAGE->theme;

// Get logo URL
$logo = $theme->setting_file_url('logo', 'logo') ?: $theme->setting_file_url('logo_white', 'logo_white');
$logourl = $logo ?: '';

// Validate certificate 
if (!$DB->record_exists('customcert_issues', ['code' => $code])) {
    throw new moodle_exception('Certificate not issued for you');
}

// Fetch certificate, user, course data
$issues_cert = $DB->get_record('customcert_issues', ['code' => $code], '*', MUST_EXIST);
$certi = $DB->get_record('customcert', ['id' => $issues_cert->customcertid], '*', MUST_EXIST);
$userid = $issues_cert->userid;
$objectid = $issues_cert->code;
$courseid = $certi->course;
$user = $DB->get_record('user', ['id' => $userid]);
$activity = $DB->get_record('customcert', ['course' => $courseid]);
$course = $DB->get_record('course', ['id' => $courseid]);

// Functions
function local_certimagegen_coursecolor($courseid) {
    $basecolors = ['#81ecec', '#74b9ff', '#a29bfe', '#dfe6e9', '#00b894', '#0984e3', '#b2bec3', '#fdcb6e', '#fd79a8', '#6c5ce7'];
    return $basecolors[$courseid % 10];
}

function local_certimagegen_getcourse_image($courseid) {
    global $DB, $CFG;
    require_once($CFG->dirroot. '/course/classes/list_element.php');
    $course = $DB->get_record('course', ['id' => $courseid]);
    $course = new core_course_list_element($course);
    foreach ($course->get_course_overviewfiles() as $file) {
        $isimage = $file->is_valid_image();
        return file_encode_url("$CFG->wwwroot/pluginfile.php", '/'. $file->get_contextid(). '/'. $file->get_component(). '/'. $file->get_filearea(). $file->get_filepath(). $file->get_filename(), !$isimage);
    }
    $color = local_certimagegen_coursecolor($courseid);
    $pattern = new \core_geopattern();
    $pattern->setColor($color);
    $pattern->patternbyid($courseid);
    return $pattern->datauri();
}

// Certificate image URL
$context = context_system::instance(); 
$component = 'local_certimagegen';   
$filearea  = 'content';  
$itemid    = 0;         
$filename  = 'certimage_'.$objectid.'.jpg';  
$cert_image_url = moodle_url::make_pluginfile_url($context->id, $component, $filearea, $itemid, '/', $filename);

// Config-based texts
$colorcode = get_config('local_certimagegen', 'defaultcertcolorborder');
$sharemessage = get_config('local_certimagegen', 'shareurlmessage');
$customtext = get_config('local_certimagegen', 'customtextheadcontent');
$customcardtext = get_config('local_certimagegen', 'customtextbodycontent');

// Replace placeholders
$customtextcontent = strtr($customtext, [
    '{username}' => $user->firstname.' '.$user->lastname,
    '{activityname}' => $activity->name,
    '{coursename}' => $course->fullname,
]);
$customcardtextcontent = strtr($customcardtext, [
    '{username}' => $user->firstname.' '.$user->lastname,
    '{activityname}' => $activity->name,
    '{coursename}' => $course->fullname,
]);

$customtextlines = array_filter(array_map('trim', preg_split("/\r\n|\n|\r/", $customtextcontent)));

$customtextlines2 = [];
foreach ($customtextlines as $index => $line) {
  if ($index === 0) {
    continue;
  }
  $customtextlines2[] = $line;
} 

$customcardtextlines = array_filter(array_map('trim', preg_split("/\r\n|\n|\r/", $customcardtextcontent)));
$customcardtextlines2 = [];
foreach ($customcardtextlines as $index => $line) {
    if ($index === 0) {
      continue;
    }
    $customcardtextlines2[] = str_replace(['<p>', '</p>'], '', $line);
  }


// Find course module id
$cmid = '';
$modinfo = get_fast_modinfo($courseid);
foreach ($modinfo->get_cms() as $cm) {
    if($cm->modname === 'customcert'){
        $coursemodule = $DB->get_record('course_modules', ['course' => $courseid, 'module' => $cm->module, 'instance' => $activity->id]);
        $cmid = $coursemodule->id;
    }
}

// Section name
$selectcourse = get_config('local_certimagegen', 'allowSectionCertificates');
$coursearr = $selectcourse ? explode(",", $selectcourse) : [];
// $section = $DB->get_record_sql("SELECT * FROM mdl_course_sections WHERE CONCAT(',', sequence, ',') LIKE '%,".$cmid.",%'");
$sql = "SELECT * FROM {course_sections} WHERE CONCAT(',', sequence, ',') LIKE :pattern";
$section = $DB->get_record_sql($sql, ['pattern' => '%,' . $cmid . ',%']);
$sectioname = $section->name ?: $course->fullname;

// Prepare template data
$templatecontext = [
    'download'              => get_string('download','local_certimagegen'),
    'certificate_img'       => get_string('certificate_img','local_certimagegen'),
    'sharemessage'          => $sharemessage,
    'cert_image_url'        => $cert_image_url,
    'currenturl'            => $currenturl,
    'logourl'               => $logourl,
    'customtextlineshead'   => $customtextlines,
    'customtextlines'       => $customtextlines2,
    'customcardtextlines'   => $customcardtextlines2,
    'colorcode'             => $colorcode,
    'course_image_url'      => local_certimagegen_getcourse_image($courseid),
    'course_name'           => in_array($courseid, $coursearr) ? $sectioname : $course->fullname,
    'course_summary'        => $course->summary,
    // 'course_summary'     => $customcardtextlines[0],
    'cmid'                  => $cmid,
];

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_certimagegen/certificate', $templatecontext);
echo $OUTPUT->footer();


?>
