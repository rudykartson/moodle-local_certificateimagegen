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

declare(strict_types=1);

use mod_customcert\template as customcert_template;

require_once(__DIR__ . '/../../config.php');

global $DB, $CFG, $USER, $PAGE, $OUTPUT; 

require_once($CFG->dirroot . '/lib/enrollib.php');
require_once($CFG->dirroot . '/mod/customcert/lib.php');
require_once($CFG->dirroot . '/user/profile/lib.php');
require_once($CFG->libdir . '/filelib.php');
require_once($CFG->dirroot . '/lib/badgeslib.php');

require_login();

$contextsystem = context_system::instance();

// Page setup.
$PAGE->set_context($contextsystem);
$PAGE->set_url(new moodle_url('/local/certimagegen/certificates.php'));
$PAGE->set_pagelayout('standard');
$PAGE->set_title('My Certificates');
$PAGE->set_heading(format_string($SITE->fullname));

$pdftoppmPath = trim(shell_exec('command -v pdftoppm'));
if (empty($pdftoppmPath)) {
    redirect( new moodle_url('/admin/settings.php', ['section' => 'local_qbsettings']),
    get_string('pdftoppm_missing', 'local_certimagegen'));
 
}
/**
 * Ensure certificate image exists for a given issue.
 *
 * @param int $userid
 * @param int $templateid
 * @param string $code
 * @return bool created or already exists
 */
function local_certimagegen_ensure_issue_image(int $userid, int $templateid, string $code): bool {
    global $CFG, $DB;

    $context = context_system::instance();
    $fs = get_file_storage();
    $component = 'local_certimagegen';
    $filearea = 'content';
    $itemid = 0;
    $filename = 'certimage_' . $code . '.jpg';

    // If the file already exists, nothing to do.
    $existing = $fs->get_file($context->id, $component, $filearea, $itemid, '/', $filename);
    if ($existing) {
        return true;
    }

    // Check pdftoppm.
    $pdftoppmPath = trim((string) shell_exec('command -v pdftoppm'));
    if (empty($pdftoppmPath)) {
        debugging('pdftoppm is not installed or not in PATH; skipping image generation.', DEBUG_DEVELOPER);
        return false;
    }

    // Load template.
    $templatedata = $DB->get_record('customcert_templates', ['id' => $templateid], '*', MUST_EXIST);
    $template = new customcert_template($templatedata);

    // Generate PDF to temp.
    $pdfpath = $CFG->tempdir . "/customcert_user_{$userid}_template_{$templateid}.pdf";
    $pdfdata = $template->generate_pdf(false, $userid, true);
    file_put_contents($pdfpath, $pdfdata);

    // Convert to JPEG (single page).
    $outputBase = $CFG->tempdir . "/certimage_" . $code; // No extension
    $pdfEscaped = escapeshellarg($pdfpath);
    $outputEscaped = escapeshellarg($outputBase);
    $cmd = $pdftoppmPath . " -jpeg -r 150 -f 1 -singlefile -jpegopt jpeg-quality=30 " . $pdfEscaped . " " . $outputEscaped;

    @exec($cmd, $out, $ret);
    if ($ret !== 0) {
        debugging('pdftoppm failed with exit code ' . $ret, DEBUG_DEVELOPER);
        if (file_exists($pdfpath)) { @unlink($pdfpath); }
        return false;
    }

    $imagePath = $outputBase . ".jpg";
    if (!file_exists($imagePath)) {
        debugging('Expected image file not found after pdftoppm: ' . $imagePath, DEBUG_DEVELOPER);
        if (file_exists($pdfpath)) { @unlink($pdfpath); }
        return false;
    }

    $imagecontent = file_get_contents($imagePath);
    $filerecord = [
        'contextid' => $context->id,
        'component' => $component,
        'filearea'  => $filearea,
        'itemid'    => $itemid,
        'filepath'  => '/',
        'filename'  => $filename,
    ];

    $fs->create_file_from_string($filerecord, $imagecontent);

    // Cleanup temps.
    if (file_exists($pdfpath)) { @unlink($pdfpath); }
    if (file_exists($imagePath)) { @unlink($imagePath); }

    return true;
}

// Admin-only: proactively generate images for all issues missing images.
if (is_siteadmin()) {
    $allissues = $DB->get_records_sql('SELECT ci.*, c.templateid
                                         FROM {customcert_issues} ci
                                         JOIN {customcert} c ON ci.customcertid = c.id');
    foreach ($allissues as $iss) {
        $userid = (int) $iss->userid;
        $templateid = (int) $iss->templateid;
        $code = (string) $iss->code;
        if ($userid && $templateid && $code) {
            local_certimagegen_ensure_issue_image($userid, $templateid, $code);
        }
    }
}

// Helper for badge image URL.
function local_certimagegen_badge_image_url(badge $badge, context $context): moodle_url {
    return moodle_url::make_pluginfile_url($context->id, 'badges', 'badgeimage', $badge->id, '/', 'f3', false);
}

// Configs.
$sharemessage = (string) get_config('local_certimagegen', 'shareurlmessage');
$allowsection = (string) get_config('local_certimagegen', 'allowSectionCertificates');
$sectioncourseids = array_filter(array_map('intval', array_map('trim', $allowsection ? explode(',', $allowsection) : [])));

// Issued certificates for current user.
$issues_certs = $DB->get_records('customcert_issues', ['userid' => $USER->id]);
$totalissued = count($issues_certs);
$issuedperpage = 4;
$issuedpage = optional_param('issuedpage', 0, PARAM_INT);
$paged_issued = array_slice(array_values($issues_certs), $issuedpage * $issuedperpage, $issuedperpage);

// Build issued certs view model.
$issuedcerts = [];

if (!empty($paged_issued)) {
    // Get customcert module id once.
    $moduleid = (int)$DB->get_field('modules', 'id', ['name' => 'customcert'], MUST_EXIST);

    foreach ($paged_issued as $issue) {

        // Certificate instance.
        $certi = $DB->get_record('customcert', [
            'id' => $issue->customcertid
        ], '*', MUST_EXIST);

        // Course module.
        $cm = $DB->get_record('course_modules', [
            'instance' => $issue->customcertid,
            'course'   => $certi->course,
            'module'   => $moduleid
        ], '*', MUST_EXIST);

        // Course.
        $course = $DB->get_record('course', [
            'id' => $certi->course
        ], '*', MUST_EXIST);

        /* ---------------------------------------------------------
         * PREVIEW IMAGE (template image – instant)
         * --------------------------------------------------------- */
        $preview = $defaultimgurl;

        $imgdata = $DB->get_record_sql(
            "SELECT ce.*, cc.course, cc.id AS cerid
               FROM {customcert} cc
               JOIN {customcert_pages} cp ON cc.templateid = cp.templateid
               JOIN {customcert_elements} ce ON cp.id = ce.pageid
              WHERE cc.course = ? AND cc.id = ?
              LIMIT 1",
            [$course->id, $certi->id]
        );

        if ($imgdata && !empty($imgdata->data)) {
            $imgfdata = json_decode($imgdata->data);
            if ($imgfdata && !empty($imgfdata->contextid)) {
                $preview = moodle_url::make_pluginfile_url(
                    (int)$imgfdata->contextid,
                    'mod_customcert',
                    $imgfdata->filearea ?? '',
                    (int)($imgfdata->itemid ?? 0),
                    $imgfdata->filepath ?? '/',
                    $imgfdata->filename ?? ''
                )->out(false);
            }
        }

        /* ---------------------------------------------------------
         * ISSUED CERT IMAGE (lazy + adhoc task)
         * --------------------------------------------------------- */
        $issuedimageurl = new moodle_url('/local/certimagegen/image.php', [
            'issueid' => (int)$issue->id,
        ]);

        /* ---------------------------------------------------------
         * Section / course label
         * --------------------------------------------------------- */
        $sectionname = $course->fullname;

        $section = $DB->get_record_sql(
            "SELECT *
               FROM {course_sections}
              WHERE CONCAT(',', sequence, ',') LIKE ?",
            ['%,' . $cm->id . ',%']
        );

        if ($section && !empty($section->name)) {
            $sectionname = $section->name;
        }

        $courselabel = in_array((int)$course->id, $sectioncourseids, true)
            ? $sectionname
            : $course->fullname;

        /* ---------------------------------------------------------
         * FINAL VIEW MODEL
         * --------------------------------------------------------- */
        $issuedcerts[] = [
            'cmid'          => (int)$cm->id,
            'courseid'      => (int)$course->id,
            // 👇 Images
            'preview_image' => $preview,
            'issued_image'  => $issuedimageurl->out(false),
            // 👇 Text
            'course_label'  => format_string($courselabel),
            'cert_title'    => format_string($certi->name),
            // 👇 Actions
            'download_url'  => (new moodle_url('/mod/customcert/view.php', [
                'id' => $cm->id,
                'downloadown' => 1
            ]))->out(false),

            'share_url'     => (new moodle_url('/local/certimagegen/certificate.php', [
                'code' => $issue->code
            ]))->out(false),
        ];
    }
}


// Build paging bar html for issued certs.
$issuedpaginghtml = '';
if ($totalissued > $issuedperpage) {
    $baseurl = new moodle_url('/local/certimagegen/certificates.php', [
        'lockedpage' => optional_param('lockedpage', 0, PARAM_INT)
    ]);
    $issuedpaginghtml = $OUTPUT->paging_bar($totalissued, $issuedpage, $issuedperpage, $baseurl, 'issuedpage');
}

// Discover available cert activities and filter ones already issued (locked/upcoming list).
$certicourses = [];
$mycourses = enrol_get_my_courses(null, 'sortorder ASC', 0);
foreach ($mycourses as $cid => $course) {
    $modinfo = get_fast_modinfo($cid);
    foreach ($modinfo->get_cms() as $cm) {
        if ($cm->modname === 'customcert') {
            $info = new \core_availability\info_module($cm);
            $availabilityinfo = '';
            if ($info->is_available($availabilityinfo, false, $USER->id)) {
                $row = (object)[
                    'id'         => $course->id,
                    'fullname'   => $course->fullname,
                    'cmid'       => $cm->id,
                    'certid'     => $cm->instance,
                    'modulename' => $cm->name,
                ];
                $certicourses[$cm->id] = $row;
            }
        }
    }
}

// Exclude already issued (by cmid).
$issuedcmids = array_map(function($i) use ($DB) {
    $moduleid = (int) $DB->get_field('modules', 'id', ['name' => 'customcert'], MUST_EXIST);
    $certi = $DB->get_record('customcert', ['id' => $i->customcertid], '*', MUST_EXIST);
    $cm = $DB->get_record('course_modules', ['instance' => $i->customcertid, 'course' => $certi->course, 'module' => $moduleid], '*', MUST_EXIST);
    return (int)$cm->id;
}, $issues_certs);

$filteredCourses = array_filter($certicourses, function($c) use ($issuedcmids) {
    return !in_array((int)$c->cmid, $issuedcmids, true);
});

// Pagination for locked.
$totallocked = count($filteredCourses);
$lockedperpage = 4;
$lockedpage = optional_param('lockedpage', 0, PARAM_INT);
$paged_locked = array_slice(array_values($filteredCourses), $lockedpage * $lockedperpage, $lockedperpage);

// Default preview image (filearea: defaultcertimage, itemid 0).
$fs = get_file_storage();
$defaultimgurl = '';
$files = $fs->get_area_files($contextsystem->id, 'local_certimagegen', 'defaultcertimage', 0, 'itemid, filepath, filename', false);
if (!empty($files)) {
    $file = reset($files);
    $defaultimgurl = moodle_url::make_pluginfile_url(
        $file->get_contextid(),
        $file->get_component(),
        $file->get_filearea(),
        $file->get_itemid(),
        $file->get_filepath(),
        $file->get_filename()
    )->out(false);
}

// Build locked certs view model.
$lockedcerts = [];
foreach ($paged_locked as $c) {
    // Section label vs course fullname.
    $sectionname = $c->fullname;
    $section = $DB->get_record_sql("SELECT * FROM {course_sections} WHERE CONCAT(',', sequence, ',') LIKE ?", ['%,' . $c->cmid . ',%']);
    if ($section && !empty($section->name)) {
        $sectionname = $section->name;
    }
    $courselabel = in_array((int)$c->id, $sectioncourseids, true) ? $sectionname : $c->fullname;

    // Preview: try image element from template else default.
    $preview = $defaultimgurl;
    $imgdata = $DB->get_record_sql(
        "SELECT ce.*, cc.course, cc.id AS cerid
           FROM {customcert} cc
           JOIN {customcert_pages} cp ON cc.templateid = cp.templateid
           JOIN {customcert_elements} ce ON cp.id = ce.pageid
          WHERE cc.course = ? AND cc.id = ?
          LIMIT 1",
        [$c->id, $c->certid]
    );
    if ($imgdata && !empty($imgdata->data)) {
        $imgfdata = json_decode($imgdata->data);
        if ($imgfdata && !empty($imgfdata->contextid)) {
            $preview = moodle_url::make_pluginfile_url(
                (int) $imgfdata->contextid,
                'mod_customcert',
                $imgfdata->filearea ?? '',
                (int) ($imgfdata->itemid ?? 0),
                $imgfdata->filepath ?? '/',
                $imgfdata->filename ?? ''
            )->out(false);
        }
    }

    $lockedcerts[] = [
        'cmid'           => (int)$c->cmid,
        'courseid'       => (int)$c->id,
        'module_name'    => format_string($c->modulename),
        'course_label'   => format_string($courselabel),
        'preview_img_url'=> $preview,
        'download_url'   => (new moodle_url('/mod/customcert/view.php', ['id' => $c->cmid, 'downloadown' => 1]))->out(false),
    ];
}

// Locked paging bar.
$lockedpaginghtml = '';
if ($totallocked > $lockedperpage) {
    $baseurl = new moodle_url('/local/certimagegen/certificates.php', [
        'issuedpage' => $issuedpage
    ]);
    $lockedpaginghtml = $OUTPUT->paging_bar($totallocked, $lockedpage, $lockedperpage, $baseurl, 'lockedpage');
}

// Badges.
$issuedbadges = $DB->get_records('badge_issued', ['userid' => $USER->id]);
$badges = [];
if (!empty($issuedbadges)) {
    // $badgecontext = $contextsystem;
    foreach ($issuedbadges as $ib) {
        $b = new badge($ib->badgeid);
        if($b->type == 1){
            $badgecontext = $contextsystem;
        }else{
            $badgecontext = context_course::instance($b->courseid);
        }
        $badges[] = [
            'name' => format_string($b->name),
            'image_url' => local_certimagegen_badge_image_url($b, $badgecontext)->out(false),
        ];
    }
}

// Data context for template.
$data = [
    'wwwroot'            => $CFG->wwwroot,
    'share_message'      => $sharemessage,
    'my_certificate'     => get_string('my_certificate', 'local_certimagegen'),
    'download'           => get_string('download', 'local_certimagegen'),
    'issuedcerts'        => $issuedcerts,
    'issued_pagingbar'   => $issuedpaginghtml,
    'lockedcerts'        => $lockedcerts,
    'locked_pagingbar'   => $lockedpaginghtml,
    'hasbadges'          => !empty($badges),
    'badge_count_padded' => sprintf('%02d', count($badges)),
    'badges'             => $badges,
];

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_certimagegen/certificates', $data);
$PAGE->requires->js_call_amd('local_certimagegen/share', 'init');
echo $OUTPUT->footer();
