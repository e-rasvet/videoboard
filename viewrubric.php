<?php

require_once("../../config.php");
require_once("lib.php");
require_once($CFG->libdir . '/completionlib.php');
require_once($CFG->libdir . '/plagiarismlib.php');

$id = optional_param('id', 0, PARAM_INT);  // Course Module ID
$a  = optional_param('a', 0, PARAM_INT);   // videoboard ID

$url = new moodle_url('/mod/videoboard/view.php');
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
        print_error('invalidid', 'videoboard');
    }
    if (! $course = $DB->get_record("course", array("id"=>$videoboard->course))) {
        print_error('coursemisconf', 'videoboard');
    }
    if (! $cm = get_coursemodule_from_instance("videoboard", $videoboard->id, $course->id)) {
        print_error('invalidcoursemodule');
    }
    $url->param('a', $a);
}

$PAGE->set_url($url);
require_login($course, true, $cm);

$PAGE->requires->js('/mod/videoboard/videoboard.js');

require ("$CFG->dirroot/mod/videoboard/type/$videoboard->videoboardtype/videoboard.class.php");
$videoboardclass = "videoboard_$videoboard->videoboardtype";
$videoboardinstance = new $videoboardclass($cm->id, $videoboard, $cm, $course);

/// Mark as viewed
$completion=new completion_info($course);
$completion->set_module_viewed($cm);

$videoboardinstance->view();   // Actually display the videoboard!