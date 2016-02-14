<?php

require_once("../../config.php");
require_once("lib.php");
require_once($CFG->libdir.'/plagiarismlib.php');

$id   = optional_param('id', 0, PARAM_INT);          // Course module ID
$a    = optional_param('a', 0, PARAM_INT);           // videoboard ID
$mode = optional_param('mode', 'all', PARAM_ALPHA);  // What mode are we in?
$download = optional_param('download' , 'none', PARAM_ALPHA); //ZIP download asked for?

$url = new moodle_url('/mod/videoboard/submissions.php');
if ($id) {
    if (! $cm = get_coursemodule_from_id('videoboard', $id)) {
        print_error('invalidcoursemodule');
    }

    if (! $videoboard = $DB->get_record("videoboard", array("id"=>$cm->instance))) {
        print_error('invalidid', 'videoboard');
    }

    if (! $course = $DB->get_record("course", array("id"=>$videoboard->course))) {
        print_error('coursemisconf', 'videoboard');
    }
    $url->param('id', $id);
} else {
    if (!$videoboard = $DB->get_record("videoboard", array("id"=>$a))) {
        print_error('invalidcoursemodule');
    }
    if (! $course = $DB->get_record("course", array("id"=>$videoboard->course))) {
        print_error('coursemisconf', 'videoboard');
    }
    if (! $cm = get_coursemodule_from_instance("videoboard", $videoboard->id, $course->id)) {
        print_error('invalidcoursemodule');
    }
    $url->param('a', $a);
}

if ($mode !== 'all') {
    $url->param('mode', $mode);
}
$PAGE->set_url($url);
require_login($course->id, false, $cm);

/*
* If is student
*/

if (!has_capability('mod/videoboard:grade', get_context_instance(CONTEXT_MODULE, $cm->id))) {
  $url = new moodle_url('/mod/videoboard/viewrubric.php', array("id"=>$id));
  header('Location: '.$url);
  die();
}



$PAGE->requires->js('/mod/videoboard/videoboard.js');

/// Load up the required videoboard code
require($CFG->dirroot.'/mod/videoboard/type/'.$videoboard->videoboardtype.'/videoboard.class.php');
$videoboardclass = 'videoboard_'.$videoboard->videoboardtype;
$videoboardinstance = new $videoboardclass($cm->id, $videoboard, $cm, $course);

if($download == "zip") {
    $videoboardinstance->download_submissions();
} else {
    $videoboardinstance->submissions($mode);   // Display or process the submissions
}