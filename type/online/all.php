<?php

//===================================================
// all.php
//
// Displays a complete list of online videoboards
// for the course. Rather like what happened in
// the old Journal activity.
// Howard Miller 2008
// See MDL-14045
//===================================================

require_once("../../../../config.php");
require_once("{$CFG->dirroot}/mod/videoboard/lib.php");
require_once($CFG->libdir.'/gradelib.php');
require_once('videoboard.class.php');

// get parameter
$id = required_param('id', PARAM_INT);   // course

if (!$course = $DB->get_record('course', array('id'=>$id))) {
    print_error('invalidcourse');
}

$PAGE->set_url('/mod/videoboard/type/online/all.php', array('id'=>$id));

require_course_login($course);

// check for view capability at course level
$context = get_context_instance(CONTEXT_COURSE,$course->id);
require_capability('mod/videoboard:view',$context);

// various strings
$str = new stdClass;
$str->videoboards = get_string("modulenameplural", "videoboard");
$str->duedate = get_string('duedate','videoboard');
$str->duedateno = get_string('duedateno','videoboard');
$str->editmysubmission = get_string('editmysubmission','videoboard');
$str->emptysubmission = get_string('emptysubmission','videoboard');
$str->novideoboards = get_string('novideoboards','videoboard');
$str->onlinetext = get_string('typeonline','videoboard');
$str->submitted = get_string('submitted','videoboard');

$PAGE->navbar->add($str->videoboards, new moodle_url('/mod/videoboard/index.php', array('id'=>$id)));
$PAGE->navbar->add($str->onlinetext);

// get all the videoboards in the course
$videoboards = get_all_instances_in_course('videoboard',$course, $USER->id );

$sections = get_all_sections($course->id);

// array to hold display data
$views = array();

// loop over videoboards finding online ones
foreach( $videoboards as $videoboard ) {
    // only interested in online videoboards
    if ($videoboard->videoboardtype != 'online') {
        continue;
    }

    // check we are allowed to view this
    $context = get_context_instance(CONTEXT_MODULE, $videoboard->coursemodule);
    if (!has_capability('mod/videoboard:view',$context)) {
        continue;
    }

    // create instance of videoboard class to get
    // submitted videoboards
    $onlineinstance = new videoboard_online( $videoboard->coursemodule );
    $submitted = $onlineinstance->submittedlink(true);
    $submission = $onlineinstance->get_submission();

    // submission (if there is one)
    if (empty($submission)) {
        $submissiontext = $str->emptysubmission;
        if (!empty($videoboard->timedue)) {
            $submissiondate = "{$str->duedate} ".userdate( $videoboard->timedue );

        } else {
            $submissiondate = $str->duedateno;
        }

    } else {
        $submissiontext = format_text( $submission->data1, $submission->data2 );
        $submissiondate  = "{$str->submitted} ".userdate( $submission->timemodified );
    }

    // edit link
    $editlink = "<a href=\"{$CFG->wwwroot}/mod/videoboard/view.php?".
        "id={$videoboard->coursemodule}&amp;edit=1\">{$str->editmysubmission}</a>";

    // format options for description
    $formatoptions = new stdClass;
    $formatoptions->noclean = true;

    // object to hold display data for videoboard
    $view = new stdClass;

    // start to build view object
    $view->section = get_section_name($course, $sections[$videoboard->section]);

    $view->name = $videoboard->name;
    $view->submitted = $submitted;
    $view->description = format_module_intro('videoboard', $videoboard, $videoboard->coursemodule);
    $view->editlink = $editlink;
    $view->submissiontext = $submissiontext;
    $view->submissiondate = $submissiondate;
    $view->cm = $videoboard->coursemodule;

    $views[] = $view;
}

//===================
// DISPLAY
//===================

$PAGE->set_title($str->videoboards);
echo $OUTPUT->header();

foreach ($views as $view) {
    echo $OUTPUT->container_start('clearfix generalbox videoboard');

    // info bit
    echo $OUTPUT->heading("$view->section - $view->name", 3, 'mdl-left');
    if (!empty($view->submitted)) {
        echo '<div class="reportlink">'.$view->submitted.'</div>';
    }

    // description part
    echo '<div class="description">'.$view->description.'</div>';

    //submission part
    echo $OUTPUT->container_start('generalbox submission');
    echo '<div class="submissiondate">'.$view->submissiondate.'</div>';
    echo "<p class='no-overflow'>$view->submissiontext</p>\n";
    echo "<p>$view->editlink</p>\n";
    echo $OUTPUT->container_end();

    // feedback part
    $onlineinstance = new videoboard_online( $view->cm );
    $onlineinstance->view_feedback();

    echo $OUTPUT->container_end();
}

echo $OUTPUT->footer();