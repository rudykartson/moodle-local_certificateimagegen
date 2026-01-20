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
use mod_customcert\template;

require_once("../../config.php");
global $DB, $CFG, $USER, $PAGE, $OUTPUT;
require_once($CFG->dirroot . '/lib/enrollib.php');
require_once($CFG->dirroot . '/mod/customcert/lib.php');
require_once($CFG->dirroot . '/user/profile/lib.php');
require_once($CFG->dirroot.'/lib/badgeslib.php');
require_once($CFG->libdir . '/filelib.php');
require_login();

  $issue_badges = $DB->get_records("badge_issued", ['userid' => $USER->id]);
  function print_badge_image2($badge,$context) {
    $imageurl = moodle_url::make_pluginfile_url($context->id, 'badges', 'badgeimage', $badge->id, '/', 'f3', false);
    return $imageurl;
    // $attributes = array('src' => $imageurl, 'alt' => s($badge->name), 'class' => 'activatebadge customfunbadge');
    // return html_writer::empty_tag('img', $attributes);
  }

  $issuecertificates = $DB->get_records_sql('SELECT ci.*, c.templateid FROM {customcert_issues} ci JOIN {customcert} c on ci.customcertid = c.id');

  if ($issuecertificates && is_siteadmin()) {
    foreach ($issuecertificates as $key => $icertificate) {
        $userid2      = $icertificate->userid;
        $templateid2  = $icertificate->templateid;
        $objectid2    = $icertificate->code;

        if (!$userid2 || !$templateid2 || !$objectid2) {
            echo ("Missing data for delayed_certificate_task");
            return;
        }

        $templatedata2 = $DB->get_record('customcert_templates', ['id' => $templateid2], '*', MUST_EXIST);
        $template2     = new template($templatedata2);

        $pdfpath2 = $CFG->tempdir . "/customcert_user_{$userid2}_template_{$templateid2}.pdf";
        $pdfdata2 = $template2->generate_pdf('S', $userid2, true);
        file_put_contents($pdfpath2, $pdfdata2);

        // === Configuration ===
        $context2    = \context_system::instance();
        $component2  = 'local_certimagegen';
        $filearea2   = 'content';
        $itemid2     = 0;
        $filename2   = 'certimage_' . $objectid2 . '.jpg';

        $fs2 = get_file_storage();
        $existing2 = $fs2->get_file($context2->id, $component2, $filearea2, $itemid2, '/', $filename2);

        if (!$existing2) {
              $pdftoppmPath = trim(shell_exec('command -v pdftoppm'));
              if (empty($pdftoppmPath)) {
                  throw new \Exception("pdftoppm is not installed or not in the system PATH.");
              }

            try {
                $outputBase = $CFG->tempdir . "/certimage_" . $objectid2; // no extension
                // $command = "pdftoppm -jpeg -f 1 -singlefile -jpegopt quality=10 $pdfpath $outputBase";

                $pdfEscaped = escapeshellarg($pdfpath2);
                $outputEscaped = escapeshellarg($outputBase);

                // Updated command with correct jpegopt
                $command = "$pdftoppmPath -jpeg -r 150 -f 1 -singlefile -jpegopt jpeg-quality=30 $pdfEscaped $outputEscaped";


                exec($command, $output, $return_var);

                if ($return_var !== 0) {
                    throw new \Exception("pdftoppm failed with exit code $return_var");
                }

                $imagePath = $outputBase . ".jpg"; 

                if (!file_exists($imagePath)) {
                    throw new \Exception("Expected image file not found: $imagePath");
                }

                $imagecontent = file_get_contents($imagePath);

                $filerecord2 = [
                    'contextid' => $context2->id,
                    'component' => $component2,
                    'filearea'  => $filearea2,
                    'itemid'    => $itemid2,
                    'filepath'  => '/',
                    'filename'  => $filename2
                ];

                $storedfile = $fs2->create_file_from_string($filerecord2, $imagecontent);
                if (file_exists($pdfpath2)) {
                    unlink($pdfpath2);
                }

            } catch (\Exception $e) {
                echo ("Image conversion failed: " . $e->getMessage());
            }
        }
    }
  }

$currentuser = $USER->id;

$issues_certs = $DB->get_records('customcert_issues', ['userid' => $currentuser]);
foreach ($issues_certs as $key => $value) {
    $module = $DB->get_record('modules', ['name' => 'customcert']);
    $certi = $DB->get_record('customcert', ['id' => $value->customcertid]);
    $cmtable = $DB->get_record('course_modules', ['instance' => $value->customcertid,'course' => $certi->course,'module' => $module->id]);

    $value->cmid = $cmtable->id;
    $value->courseid = $certi->course;
    $value->templateid = $certi->templateid;
    $value->certi_name = $certi->name;
    $value->certi_desc = $certi->intro;
}

$context = context_system::instance(); 
$component = 'local_certimagegen';   
$filearea  = 'content';  
$itemid    = 0;  


$data = enrol_get_my_courses($fields = null, $sort = "sortorder asc", $limit = 10, $courseids = [], $allaccessible = false, $offset = 0, $excludecourses = []);
$certicourses = [];

foreach ($data as $cid => $cname) {
    $modinfo = get_fast_modinfo($cid);

    foreach ($modinfo->get_cms() as $cm) {
        if ($cm->modname === 'customcert') {
            //--------is_available code----------
            $info = new \core_availability\info_module($cm);
            $availabilityinfo = '';
            if (!$info->is_available($availabilityinfo, false, $currentuser)) {}else{
              $certidata = clone $cname; 
              $certidata->cmid = $cm->id;
              $certidata->certid = $cm->instance;
              $certidata->modulename = $cm->name;
              $certicourses[$cm->id] = $certidata;
            } 
        }
    }
}

  if(empty($certicourses)){
      throw new \Exception('Certificate not issues for you');
  }


  $courseIdsToExclude = array_map(function($cert) {
      return $cert->cmid;
  }, $issues_certs);

  // Filter second array
  $filteredCourses = array_filter($certicourses, function($course) use ($courseIdsToExclude) {
      // return !in_array($course->id, $courseIdsToExclude);
      return !in_array($course->cmid, $courseIdsToExclude);
  });

  $sharemessage = get_config('local_certimagegen', 'shareurlmessage');

echo $OUTPUT->header();
?> 

<div class="container" id="certificate_list">
    <h2>My Certificate</h2>
    <input type="hidden" id="attachmsg" value="<?php echo $sharemessage; ?>">
    <div class="row g-3">

<?php
    $selectcourse = get_config('local_certimagegen', 'allowSectionCertificates');
    if($selectcourse){
        $coursearr = explode(",", $selectcourse);
    }else{
        $coursearr = [];
    }


    // --- Issued Certificates Pagination Setup ---
    $issuedperpage = 4;
    $issuedpage = optional_param('issuedpage', 0, PARAM_INT);
    $totalissued = count($issues_certs);
    $paged_issued = array_slice($issues_certs, $issuedpage * $issuedperpage, $issuedperpage);

    if ($paged_issued) {
        foreach ($paged_issued as $value) {
            $cmid = '';
            $modinfo = get_fast_modinfo($value->courseid);
            foreach ($modinfo->get_cms() as $cm) {
                if ($cm->modname === 'customcert') {
                    $coursemodule = $DB->get_record('course_modules', [
                        'course' => $value->courseid,
                        'module' => $cm->module,
                        'instance' => $value->customcertid
                    ]);
                    $cmid = $coursemodule->id;
                }
            }

            $filename = 'certimage_' . $value->code . '.jpg';  
            $url = moodle_url::make_pluginfile_url($context->id, $component, $filearea, $itemid, '/', $filename);
            $course = $DB->get_record('course', ['id' => $value->courseid]);


            $section = $DB->get_record_sql("SELECT * FROM mdl_course_sections WHERE CONCAT(',', sequence, ',') LIKE '%,".$cmid.",%'");
            if($section->name){
              $sectioname = $section->name;
            }else{
              $sectioname = $course->fullname;
            }

            echo '<div class="col-md-6 col-sm-12">
              <div class="certificate-card">';
                  echo '<div class="certimg">';
                  echo '<img src="'.$url.'" alt="Certificate" id="shareimg'.$cmid.'">';
                  echo '</div>';
          echo '
                <div class="course-id btn" '.$value->courseid.' >'.((in_array($value->courseid,$coursearr) ? $sectioname : $course->fullname)).'</div>
                <div class="course-title">'.$value->certi_name.'</div>
                <div class="bottombtn">  
                  <a href="../../mod/customcert/view.php?id='.$cmid.'&downloadown=1" download="certificate.png" class="btn btn-download">Download</a>
                  <input type="hidden" class="shareURL" value="'.$CFG->wwwroot.'/local/certimagegen/certificate.php?code='.$value->code.'" />
                  <button class="openShare btn btn-share">Share</button>
                </div>
              </div>
            </div>';
        }
    }

    // --- Show pagination bar for issued certificates ---
    if ($totalissued > $issuedperpage) {
        $baseurl = new moodle_url('/local/certimagegen/certificates.php');
        echo '<div class="col-12 text-center">';
        echo $OUTPUT->paging_bar($totalissued, $issuedpage, $issuedperpage, $baseurl->out(false, ['lockedpage' => optional_param('lockedpage', 0, PARAM_INT)]), 'issuedpage');
        echo '</div>';
    }
?>
    
<?php
    // --- Locked/Upcoming Certificates Pagination Setup ---
    $lockedperpage = 4;
    $lockedpage = optional_param('lockedpage', 0, PARAM_INT);
    $totallocked = count($filteredCourses);
    $paged_locked = array_slice($filteredCourses, $lockedpage * $lockedperpage, $lockedperpage);

    if ($paged_locked) {
        $fs = get_file_storage();
        $context = context_system::instance();
        $files = $fs->get_area_files($context->id, 'local_certimagegen', 'defaultcertimage', 0, 'itemid, filepath, filename', false);
        $imageurl = '';
        if (!empty($files)) {
            $file = reset($files);
            $imageurl = moodle_url::make_pluginfile_url(
                $file->get_contextid(),
                $file->get_component(),
                $file->get_filearea(),
                $file->get_itemid(), 
                $file->get_filepath(),
                $file->get_filename()
            );
        }

        foreach ($paged_locked as $value) {
            $section = $DB->get_record_sql("SELECT * FROM mdl_course_sections WHERE CONCAT(',', sequence, ',') LIKE '%,".$value->cmid.",%'");
            if($section->name){
              $sectioname = $section->name;
            }else{
              $sectioname = $value->fullname;
            }

            $imgdata = $DB->get_record_sql("SELECT ce.*, cc.course, cc.id as cerid FROM {customcert} cc JOIN {customcert_pages} cp on cc.templateid = cp.templateid JOIN {customcert_elements} ce ON cp.id = ce.pageid WHERE cc.course = ".$value->id." and cc.id =".$value->certid);
            $imgfdata = json_decode($imgdata->data);

            $timpgurl = moodle_url::make_pluginfile_url($imgfdata->contextid, 'mod_customcert', $imgfdata->filearea, $imgfdata->itemid, $imgfdata->filepath, $imgfdata->filename);

            echo '<div class="col-md-6 col-sm-12">
              <div class="certificate-card">
                <div class ="certimg" >
                  <div id="imgdata'.$value->cmid.'" style="height: 240px;">  
                    <img src="'.($timpgurl ? $timpgurl : $imageurl).'" class="lock" alt="Locked Certificate">
                    <div class="lock-overlay">
                      <img src="assets/lock.png" alt="Locked" class="lock-icon">
                    </div>
                  </div>
                </div>
                <div class="course-id btn" '.$value->id.'>'.((in_array($value->id,$coursearr) ? $sectioname : $value->fullname)).'</div>
                <div class="course-title">'.$value->modulename.'</div>
                <div class="d-flex justify-content-between">
                  <a href="#" id="downloadBtn'.$value->cmid.'" class="btn btn-download">Download</a>
                  <span style="color: #495057;">Certificate must be downloaded before it can be shared.</span>
                </div>
              </div>
            </div>';
            ?>

            <script>
                  document.addEventListener('DOMContentLoaded', function () {
                      document.getElementById('downloadBtn<?php echo $value->cmid; ?>').addEventListener('click', function(e) {
                          e.preventDefault();

                          // Start download
                          var link = document.createElement('a');
                          link.href = '../../mod/customcert/view.php?id=<?php echo $value->cmid; ?>&downloadown=1';
                          link.download = 'certificate.png';
                          document.body.appendChild(link);
                          link.click();
                          document.body.removeChild(link);

                          // Update UI if #imgdata exists
                          var imgdataContainer = document.getElementById('imgdata<?php echo $value->cmid; ?>');
                          console.log("aaaaaaa",imgdataContainer);                                                                                                                                        
                          if (imgdataContainer) {
                              imgdataContainer.innerHTML = `
                                  <div class="certimg" style="height: 240px; display: grid; justify-content: center;">
                                      <div class="loader" id="loader<?php echo $value->cmid; ?>"></div>
                                      <span>Generating image…</span>
                                  </div>
                              `;

                              // Replace with image after 30s
                              setTimeout(function() {
                                  location.reload();
                              }, 5000); // 5 sec
                          } else {
                              console.warn('Element #imgdata not found.');
                          }
                      });
                  });
              </script>

            <?php
        }
    }

    // --- Show pagination bar for locked certificates ---
    if ($totallocked > $lockedperpage) {
        $baseurl = new moodle_url('/local/certimagegen/certificates.php'); 
        echo '<div class="col-12 text-center">';
        echo $OUTPUT->paging_bar($totallocked, $lockedpage, $lockedperpage, $baseurl->out(false, ['issuedpage' => $issuedpage]), 'lockedpage');
        echo '</div>';
    }
    ?>
    </div>

  <?php if(!empty($issue_badges)){ ?>  
    <h2 class="mb-3 mt-3 fw-bold headbadge">My Badges</h2>
    <div class="row bdblock badge-section">
      
      <div class="d-flex justify-content-between align-items-center">
        <div class="badge-count">
          Number of badges earned: <span class="count"><?php echo sprintf('%02d', count($issue_badges));?></span>
        </div>
        <!-- <form method="post">
            <button type="submit" class="btn download-btn" name="downloadzip">Download All</button>
        </form> -->
        <!-- <button class="btn download-btn ">Download All</button> -->
      </div>
      
      <div class="row g-3">
        <?php 
        $badgeimages = [];
        foreach ($issue_badges as $usrr) {
            $badge = new badge($usrr->badgeid);
            $imgdata = print_badge_image2($badge, $context);
            echo '<div class="col-12 col-sm-6 col-md-3">
                    <div class="badge-box"><img src="'.$imgdata.'" alt="badges" class="customfunbadge"></div>
                </div>';
            $badgeimages[] =  $imgdata;
        }

        // if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['downloadzip'])) {
        //       echo '<pre>';
        //       print_r($badgeimages);  
        //       echo '</pre>';
        // }
        ?>
      </div>
    </div>
  <?php } ?>  
</div>



<!-- Share Popup Modal -->
<div id="shareModal" class="modal" style="background: #8f8f8fb3;">
  <div class="modal-content">
    <span class="close-btn">&times;</span>
    <h2>Share</h2>
    <div class="social-icons">
      <a id="fbook" title="Facebook" target="_blank"><img src="<?php echo $CFG->wwwroot.'/local/certimagegen/assets/fb.svg'; ?>" alt="Facebook"><span style="color:#1877f2;" >facebook<span></a>
      <a id="linked" title="LinkedIn" target="_blank"><img src="<?php echo $CFG->wwwroot.'/local/certimagegen/assets/linked.svg'; ?>" alt="LinkedIn"><span style="color:#0077b5;" >linkedIn<span></a>
      <a id="whatsapp" title="WhatsApp" target="_blank"><img src="<?php echo $CFG->wwwroot.'/local/certimagegen/assets/wapp.svg'; ?>" alt="WhatsApp"><span style="color:#50ca5e;" >whatsapp<span></a>
      <a id="twitterx" title="X" target="_blank"><img src="<?php echo $CFG->wwwroot.'/local/certimagegen/assets/twittx.jpg'; ?>" alt="X"><span style="color:black; font-weight:bold;" >X<span></a>
    </div>

    <label for="shareLink">Copy Link</label>
    <div class="copy-section">
      <input type="text" id="shareLink" value="" readonly>
      <button id="copyBtn">Copy</button>
    </div>
  </div>
</div>


<script>
    document.querySelectorAll('.openShare').forEach(function(btn) {
      btn.onclick = function () {
        const urlInput = this.closest('.bottombtn').querySelector('.shareURL');
        const url = urlInput.value;
        
        // Encode URL for safety
        const encodedUrl = encodeURIComponent(url);
        var message = '';
        const attachmsg = document.getElementById('attachmsg').value;
        if(attachmsg){
          message = attachmsg;
        }else{
          message = encodeURIComponent("Check this out:");
        }

        // Set social links
        document.getElementById('fbook').setAttribute('href', 'https://www.facebook.com/sharer.php?u=' + encodedUrl);
        document.getElementById('linked').setAttribute('href', 'https://www.linkedin.com/sharing/share-offsite/?url=' + encodedUrl);
        document.getElementById('whatsapp').setAttribute('href', 'https://wa.me/?text=' + message + '%20' + encodedUrl);
        document.getElementById('twitterx').setAttribute('href', 'https://twitter.com/share?url=' + message + '&url=' + encodedUrl);

        // Set input field for copy
        document.getElementById('shareLink').value = url;

        // Show the modal
        document.getElementById('shareModal').style.display = 'block';
      }
    });

    document.querySelector('.close-btn').onclick = function () {
      document.getElementById('shareModal').style.display = 'none';
    };

    // Copy button
    document.getElementById('copyBtn').onclick = function () {
      const linkInput = document.getElementById('shareLink');
      linkInput.select();
      linkInput.setSelectionRange(0, 99999); 
      navigator.clipboard.writeText(linkInput.value);
      // alert('Link copied to clipboard!');
    };

</script>

<?php
echo $OUTPUT->footer();
