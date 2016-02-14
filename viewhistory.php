<?php  // $Id: viewhistory.php,v 1.2 2012/03/10 22:00:00 Igor Nikulin Exp $


require_once '../../config.php';
require_once ($CFG->dirroot.'/course/moodleform_mod.php');
require_once 'lib.php';
require_once ($CFG->libdir.'/gradelib.php');


$id                     = optional_param('id', 0, PARAM_INT); 
$ids                    = optional_param('ids', 0, PARAM_INT); 
$a                      = optional_param('a', 'list', PARAM_TEXT);  
    

if ($id) {
    if (! $cm = get_coursemodule_from_id('videoboard', $id)) {
        error('Course Module ID was incorrect');
    }

    if (! $course = $DB->get_record('course', array('id' => $cm->course))) {
        error('Course is misconfigured');
    }

    if (! $videoboard = $DB->get_record('videoboard', array('id' => $cm->instance))) {
        error('Course module is incorrect');
    }

} else {
    error('You must specify a course_module ID or an instance ID');
}

require_login($course, true, $cm);

$context = get_context_instance(CONTEXT_MODULE, $cm->id);


//if (videoboard_is_ios() && is_dir($CFG->dirroot.'/theme/mymobile')) {} else
  $PAGE->requires->js('/mod/videoboard/js/jquery.min.js', true);

//$PAGE->requires->js_function_call('M.util.load_flowplayer'); 
//$PAGE->requires->js('/mod/videoboard/js/ajax.js', true);

$PAGE->requires->js('/mod/videoboard/js/flowplayer.min.js', true);
$PAGE->requires->js('/mod/videoboard/js/swfobject.js', true);


$PAGE->requires->js('/mod/videoboard/js/mediaelement-and-player.min.js', true);
$PAGE->requires->css('/mod/videoboard/css/mediaelementplayer.css');

$PAGE->requires->js('/mod/videoboard/js/video.js', true);
$PAGE->requires->css('/mod/videoboard/css/video-js.css');


/// Print the page header
$strvideoboards = get_string('modulenameplural', 'videoboard');
$strvideoboard  = get_string('modulename', 'videoboard');

$PAGE->set_url('/mod/videoboard/viewhistory.php', array('id' => $id, 'ids' => $ids));
    
$title = $course->shortname . ': ' . format_string(get_string('modulename', 'videoboard'));
$PAGE->set_title($title);
$PAGE->set_heading($course->fullname);

echo $OUTPUT->header();

require_once ('tabs.php');

/// Print the main part of the page

    $table = new html_table();

    $table->head  = array(get_string("cell1::student", "videoboard"), get_string("cell2::", "videoboard"), get_string("cell3::peer", "videoboard"), get_string("cell4::teacher", "videoboard"));
    $table->align = array ("left", "center", "center", "center");
    $table->width = "100%";
        
    $lists = $DB->get_records ("videoboard_files", array("userid" => $ids), 'time DESC');

    foreach ($lists as $list) {
      if ($cml = get_coursemodule_from_id('videoboard', $list->instance)) {
        if ($cml->course == $cm->course) {
          $userdata    = $DB->get_record("user", array("id" => $list->userid));
          $picture     = $OUTPUT->user_picture($userdata, array('popup' => true));
                  
          $own = $DB->get_record("videoboard_ratings", array("fileid" => $list->id, "userid" => $list->userid));
              
          if (@empty($own->ratingrhythm)) @$own->ratingrhythm = get_string('norateyet', 'videoboard');
          if (empty($own->ratingclear))  $own->ratingclear = get_string('norateyet', 'videoboard');
          if (empty($own->ratingintonation)) $own->ratingintonation = get_string('norateyet', 'videoboard');
          if (empty($own->ratingspeed)) $own->ratingspeed = get_string('norateyet', 'videoboard');
          if (empty($own->ratingreproduction)) $own->ratingreproduction = get_string('norateyet', 'videoboard');
              
          //1-cell
          $o = "";
          $o .= html_writer::start_tag('div', array("style" => "text-align:left;margin:10px 0;"));
          $o .= html_writer::tag('span', $picture);
          $o .= html_writer::start_tag('span', array("style" => "margin: 8px;position: absolute;"));
          $o .= html_writer::link(new moodle_url('/user/view.php', array("id" => $userdata->id, "course" => $cml->course)), fullname($userdata));
          $o .= html_writer::end_tag('span');
          $o .= html_writer::end_tag('div');
          
          $o .= html_writer::tag('div', $list->summary, array('style'=>'margin:10px 0;'));
          
          $o .= html_writer::tag('div', videoboard_player($list->id));
          
          $o .= html_writer::tag('div', html_writer::tag('small', date(get_string("timeformat1", "videoboard"), $list->time)), array("style" => "float:left;"));
          
          if ($list->userid == $USER->id || has_capability('mod/videoboard:teacher', $context)) {
            if ($list->userid == $USER->id)
              $editlink   = html_writer::link(new moodle_url('/mod/videoboard/view.php', array("id" => $list->instance, "a" => "add", "fileid" => $list->id)), get_string("editlink", "videoboard"))." ";
            else
              $editlink   = "";
            
            
            if (has_capability('mod/videoboard:teacher', $context) || ($videoboard->resubmit == 1 && $list->userid == $USER->id)) { //ONLY TEACHER CAN DELETE SUBMISSION
              $deletelink = html_writer::link(new moodle_url('/mod/videoboard/view.php', array("id" => $list->instance, "act" => "deleteentry", "fileid" => $list->id)), get_string("delete", "videoboard"), array("onclick"=>"return confirm('".get_string("confim", "videoboard")."')"));
              $o .= html_writer::tag('div', html_writer::tag('small', $editlink.$deletelink, array("style" => "margin: 2px 0 0 10px;")));
            }
          }
          
          $cell1 = new html_table_cell($o);
          
          //2-cell
          $table2 = new html_table();

          $table2->head  = array(get_string("table2::cell1::pronunciation", "videoboard"), get_string("table2::cell2::fluency", "videoboard"), get_string("table2::cell3::content", "videoboard"), get_string("table2::cell4::organization", "videoboard"), get_string("table2::cell5::eye", "videoboard"));
          //$table2->align = array ("center", "center", "center", "center", "center");
          $table2->align = array ("center".get_string("table2::style", "videoboard"), "center".get_string("table2::style", "videoboard"), "center".get_string("table2::style", "videoboard"), "center".get_string("table2::style", "videoboard"), "center".get_string("table2::style", "videoboard"));
          $table2->width = "100%";
          
          $table2->data[] = array (videoboard_set_rait($list->id, 1),
                                   videoboard_set_rait($list->id, 2),
                                   videoboard_set_rait($list->id, 3),
                                   videoboard_set_rait($list->id, 4),
                                   videoboard_set_rait($list->id, 5));
          
          //----Comment Box-----/
          //if ($list->userid == $USER->id){
          $chtml = "";
          if($comments = $DB->get_records("videoboard_comments", array("fileid" => $list->id))){
            foreach($comments as $comment){
              $chtml .= html_writer::start_tag('div', array("style"=>"border:1px solid #333;margin:5px;text-align:left;padding:5px;"));
              
              $chtml .= html_writer::tag('div', $comment->summary, array('style'=>'margin:10px 0;'));
              
              if (!empty($comment->itemid))
                $chtml .= html_writer::tag('div', videoboard_player($comment->id, "videoboard_comments"));
              
              $chtml .= html_writer::tag('div', html_writer::tag('small', date(get_string("timeformat1", "videoboard"), $comment->time)), array("style" => "float:left;"));
              
              if ($comment->userid == $USER->id || has_capability('mod/videoboard:teacher', $context)) {
                $student = $DB->get_record("user", array("id" => $comment->userid));
                $studentlink = html_writer::link(new moodle_url('/user/view.php', array("id" => $student->id, "course" => $cml->course)), fullname($student));
                $chtml .= html_writer::tag('div', html_writer::tag('small', $studentlink . " " . html_writer::link(new moodle_url('/mod/videoboard/view.php', array("id" => $list->instance, "act" => "deletecomment", "fileid" => $comment->id)), get_string("delete", "videoboard"), array("onclick"=>"return confirm('".get_string("confim", "videoboard")."')")), array("style" => "margin: 2px 0 0 10px;")));
              }
              
              $chtml .= html_writer::end_tag('div');
            }
          }
          
          $addcommentlink = html_writer::tag('div', html_writer::link(new moodle_url('/mod/videoboard/view.php', array("id" => $list->instance, "a" => "add", "act" => "addcomment", "fileid" => $list->id)), get_string("addcomment", "videoboard")));
            /*
          } else
            $addcomment = "";*/
          //--------------------/
          
          $cell2 = new html_table_cell(html_writer::table($table2) . $chtml . $addcommentlink);
          
          //3-cell
          $cell3 = new html_table_cell(videoboard_set_rait($list->id, 6));
          
          //4-cell
          $cell4 = new html_table_cell(videoboard_set_rait($list->id, 7));
          
          
          $cells = array($cell1, $cell2, $cell3, $cell4);
          
          $row = new html_table_row($cells);
              
          $table->data[] = $row;
        }
      }
    }
   
    echo html_writer::table($table);
    
    
    if (isset($list->instance))
      echo html_writer::script('
 $(document).ready(function() {
  $(".videoboard_rate_box").change(function() {
    var value = $(this).val();
    var data  = $(this).attr("data-url");
    
    var e = $(this).parent();
    e.html(\'<img src="img/ajax-loader.gif" />\');
    
    $.get("ajax.php", {id: '.$list->instance.', act: "setrating", data: data, value: value}, function(data) {
      e.html(data); 
    });
  });
 });
    ');
    
    echo html_writer::script('
$(document).ready(function() {
  $(".videoboard-youtube-poster").click(function() {
    $("#videoboard-player-"+$(this).attr("data-url")).html(\'<iframe type="text/html" width="269" height="198" src="https://www.youtube.com/embed/\'+$(this).attr("data-text")+\'" frameborder="0"></iframe>\');
  });
  
  $(".mediaelementplayer").mediaelementplayer();
});');

/// Finish the page
echo $OUTPUT->footer();



