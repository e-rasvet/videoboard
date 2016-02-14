<?php

require("../../../../config.php");
require("../../lib.php");
require("videoboard.class.php");

$id     = required_param('id', PARAM_INT);      // Course Module ID
$userid = required_param('userid', PARAM_INT);  // User ID

$PAGE->set_url('/mod/videoboard/type/online/file.php', array('id'=>$id, 'userid'=>$userid));

if (! $cm = get_coursemodule_from_id('videoboard', $id)) {
    print_error('invalidcoursemodule');
}

if (! $videoboard = $DB->get_record("videoboard", array("id"=>$cm->instance))) {
    print_error('invalidid', 'videoboard');
}

if (! $course = $DB->get_record("course", array("id"=>$videoboard->course))) {
    print_error('coursemisconf', 'videoboard');
}

if (! $user = $DB->get_record("user", array("id"=>$userid))) {
    print_error('usermisconf', 'videoboard');
}

require_login($course->id, false, $cm);

$context = get_context_instance(CONTEXT_MODULE, $cm->id);
if (($USER->id != $user->id) && !has_capability('mod/videoboard:grade', $context)) {
    print_error('cannotviewvideoboard', 'videoboard');
}

if ($videoboard->videoboardtype != 'online') {
    print_error('invalidtype', 'videoboard');
}

$videoboardinstance = new videoboard_online($cm->id, $videoboard, $cm, $course);


$PAGE->set_pagelayout('popup');
$PAGE->set_title(fullname($user,true).': '.$videoboard->name);

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

echo $OUTPUT->header();
echo $OUTPUT->box_start('generalbox boxaligcenter', 'dates');

$lists = $DB->get_records ("videoboard_files", array("userid" => $user->id), 'time DESC');


$table        = new html_table();
$table->head  = array(get_string("videoboard_list", "videoboard"));
$table->align = array ("left");
$table->width = "100%";

foreach ($lists as $list) {
  if ($cml = get_coursemodule_from_id('videoboard', $list->instance)) {
    if ($cml->course == $cm->course && $cml->instance == $cm->instance) {
      $userdata  = $DB->get_record("user", array("id" => $list->userid));
      $picture   = $OUTPUT->user_picture($userdata, array('popup' => true));
        
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
      
      $cell1 = new html_table_cell($o);
      
      $cells = array($cell1);
      
      $row = new html_table_row($cells);
      
      $table->data[] = $row;
    }
  }
}

echo html_writer::table($table);

echo html_writer::script('
$(document).ready(function() {
  $(".videoboard-youtube-poster").click(function() {
    $("#videoboard-player-"+$(this).attr("data-url")).html(\'<iframe type="text/html" width="269" height="198" src="https://www.youtube.com/embed/\'+$(this).attr("data-text")+\'" frameborder="0"></iframe>\');
  });
  
  $(".mediaelementplayer").mediaelementplayer();
});');

echo $OUTPUT->box_end();
echo $OUTPUT->close_window_button();
echo $OUTPUT->footer();

