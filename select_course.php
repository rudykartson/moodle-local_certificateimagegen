<?php
require_once(__DIR__ . '/../../config.php');
require_login();

$context = context_system::instance();
require_capability('moodle/site:config', $context);

$PAGE->set_url(new moodle_url('/local/certimagegen/select_course.php'));
$PAGE->set_context($context);
$PAGE->set_title(get_string('selectcourse', 'local_certimagegen'));
$PAGE->set_heading(get_string('selectcourse', 'local_certimagegen'));

echo $OUTPUT->header();

global $DB;

// **Process submission first**
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $selected = isset($_POST['courses']) ? $_POST['courses'] : [];
    $selectedcsv = implode(',', $selected);
    set_config('allowSectionCertificates', $selectedcsv, 'local_certimagegen');

    // Update $selectedcourses so the form shows the new selection immediately
    $selectedcourses = $selected;

    // Redirect back to settings page after short delay
    redirect(new moodle_url('/admin/settings.php', ['section' => 'local_qbsettings']), 
             get_string('changessaved'), 2);
} else {
    // If not submitted, get previously saved courses
    $selectedcourses = get_config('local_certimagegen', 'allowSectionCertificates');
    if (!empty($selectedcourses)) {
        $selectedcourses = explode(',', $selectedcourses);
    } else {
        $selectedcourses = [];
    }
}

// Get all visible courses (filter category <> 0)
$courses = $DB->get_records_select('course', 'visible = ? AND category <> ?', [1, 0], 'fullname ASC');

// Display the form
echo '<form method="post" action="">';
$scrollstyle = 'height:400px; overflow-y:auto; border:1px solid #ccc; padding:5px; margin-bottom:10px;'; // adjust height
echo '<div style="' . $scrollstyle . '">';
foreach ($courses as $course) {
    echo html_writer::checkbox(
        'courses[]',
        $course->id,
        in_array($course->id, $selectedcourses),
        $course->fullname
    );
    echo '<br>';
}
echo '</div>'; // end scrollable div
echo '<input type="submit" value="' . get_string('savechanges') . '">';
echo '</form>';

echo $OUTPUT->footer();
