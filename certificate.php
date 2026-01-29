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

  require_once("../../config.php");
  global $DB, $CFG, $USER, $PAGE, $OUTPUT;
  require_once($CFG->libdir.'/filelib.php');
  require_once($CFG->dirroot . '/mod/customcert/lib.php');
  $code = required_param('code', PARAM_TEXT);

  $currenturl = $PAGE->url->out(false);
  
  $theme = $PAGE->theme;
  $logo = $theme->setting_file_url('logo', 'logo');
  if (!$logo) {
      $logo = $theme->setting_file_url('logo_white', 'logo_white');
  }
  $logourl = $logo ?: '';

  if(!$DB->record_exists('customcert_issues', ['code' => $code], '*', MUST_EXIST)){
      throw new moodle_exception('Certificate not issues for you');
  }
  $issues_cert = $DB->get_record('customcert_issues', ['code' => $code], '*', MUST_EXIST);
  $certi = $DB->get_record('customcert', ['id' => $issues_cert->customcertid], '*', MUST_EXIST);
  $userid = $issues_cert->userid;
  $objectid = $issues_cert->code;
  $courseid = $certi->course;
  $user = $DB->get_record('user', ['id' => $userid]);
  $activity = $DB->get_record('customcert', ['course' => $courseid]);
  $course = $DB->get_record('course', ['id' => $courseid]);

  $context = context_system::instance(); 
  $component = 'local_certimagegen';   
  $filearea  = 'content';  
  $itemid    = 0;         
  $filename  = 'certimage_'.$objectid.'.jpg';  

  function coursecolor($courseid) {
      $basecolors = ['#81ecec', '#74b9ff', '#a29bfe', '#dfe6e9', '#00b894', '#0984e3', '#b2bec3', '#fdcb6e', '#fd79a8', '#6c5ce7'];
      $color = $basecolors[$courseid % 10];
      return $color;
  }
  function getcourse_image($courseid) {
      global $DB, $CFG;
      require_once($CFG->dirroot. '/course/classes/list_element.php');
      $course = $DB->get_record('course', array('id' => $courseid));
      $course = new core_course_list_element($course);
      foreach ($course->get_course_overviewfiles() as $file) {
          $isimage = $file->is_valid_image();
          $imageurl = file_encode_url("$CFG->wwwroot/pluginfile.php", '/'. $file->get_contextid(). '/'. $file->get_component(). '/'. $file->get_filearea(). $file->get_filepath(). $file->get_filename(), !$isimage);
          return $imageurl;
      }
      if(empty($imageurl)){
          $color = coursecolor($course->id);
          $pattern = new \core_geopattern();
          $pattern->setColor($color);
          $pattern->patternbyid($courseid);
          $classes = 'coursepattern';
          $imageurl = $pattern->datauri();
      }
      return $imageurl;
  }


  $url = moodle_url::make_pluginfile_url(
      $context->id,
      $component,
      $filearea,
      $itemid,
      '/',
      $filename
  );


  // Get text from config.
  $colorcode = get_config('local_certimagegen', 'defaultcertcolorborder');
  $sharemessage = get_config('local_certimagegen', 'shareurlmessage');
  $customtext = get_config('local_certimagegen', 'customtextheadcontent');
  $customcardtext = get_config('local_certimagegen', 'customtextbodycontent');
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
  $customcardtextlines = array_filter(array_map('trim', preg_split("/\r\n|\n|\r/", $customcardtextcontent)));

  $cmid = '';
  $modinfo = get_fast_modinfo($courseid);
  foreach ($modinfo->get_cms() as $cm) {
    if($cm->modname === 'customcert'){
      $coursemodule = $DB->get_record('course_modules', ['course' => $courseid, 'module' => $cm->module, 'instance' => $activity->id]);
      $cmid = $coursemodule->id;
    }
  }

     echo '<head>
     <meta property="og:title" content="'. $sharemessage .'" />
     <meta property="og:image" content="'. $url .'" />
     <meta property="og:url" content="'. $currenturl .'" />
        <meta property="og:type" content="certificate" />
        <meta property="og:description" content="Certified Background Verification Professional (CBVP) certificate issued by BGV Academy, recognizing completion of professional training in global background screening—one of its kind certification in India" />
        <meta property="og:logo" content="'.$logourl.'" />
     
   </head>';
  echo $OUTPUT->header();
?>

<body>
  <div class="container py-5" id="sharecertpage" style="background-color: #f5f7fb; border-radius: 10px;">
    <h2 class="section-title text-primary"><?php echo $customtextlines[0]; ?></h2>
    <?php
      foreach ($customtextlines as $index => $line) {
        if ($index === 0) {
          continue;
        }
        echo '<div class="section-title-desc" >'.$line.'</div>';
      } 
    ?>

    <div class="row g-4">
      <!-- Certificate Card -->
      <div class="col-md-7">
        <div class="certificate-card p-3" style="border: solid 10px <?php echo $colorcode; ?>">
          <img src="<?php echo $url; ?>" alt="<?php echo $code; ?>" class="certificate-img">
        </div>
      </div>

      <!-- Course Card -->
      <?php
      $selectcourse = get_config('local_certimagegen', 'allowSectionCertificates');
      if($selectcourse){
          $coursearr = explode(",", $selectcourse);
      }else{
          $coursearr = [];
      }
      
      $section = $DB->get_record_sql("SELECT * FROM mdl_course_sections WHERE CONCAT(',', sequence, ',') LIKE '%,".$cmid.",%'");
      if($section->name){
        $sectioname = $section->name;
      }else{
        $sectioname = $course->fullname;
      }
      
      ?> 
      <div class="col-md-5">
        <div class="course-card text-center">
          <img src="<?php echo getcourse_image($courseid); ?>" alt="<?php echo $code; ?>" class="course-image">
          <div class="cardhead">
            <h5 class="course-title"><?php echo ((in_array($courseid, $coursearr) ? $sectioname : $course->fullname)); ?></h5>
            <?php //echo $customcardtextlines[0]; 
                  echo $course->summary;  ?>
          <div>
          <ul class="text-start">
            <?php
              foreach ($customcardtextlines as $index => $line) {
                if ($index === 0) {
                  continue;
                }
                echo '<li>'.str_replace(['<p>', '</p>'], '', $line).'</li>';
              }
            ?>
          </ul>
          <div class="d-flex justify-content-left align-items-left mt-3">
            <a href="../../mod/customcert/view.php?id=<?php echo $cmid; ?>&downloadown=1" download="certificate.png" class="btn btn-download">Download</a>
          </div>
        </div>
      </div>
    </div>
  </div>
</body>
<?php

echo $OUTPUT->footer();