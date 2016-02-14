<?php  // $Id: view.php,v 1.2 2012/03/10 22:00:00 Igor Nikulin Exp $


require_once '../../config.php';
require_once 'lib.php';


$id                     = optional_param('id', 0, PARAM_INT); 
$ids                    = optional_param('ids', 0, PARAM_INT); 
$a                      = optional_param('a', 'list', PARAM_TEXT);  
$fileid                 = optional_param('fileid', 0, PARAM_INT);
    
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

add_to_log($course->id, "videoboard", "deletepeers", "deletepeers.php?id=$cm->id", "$videoboard->id");


if (!empty($ids)) {
    $DB->set_field("videoboard_ratings", "rating", 0, array("id" => $ids));
    
    $c = 0;
    
    if (!$lists = $DB->get_records_sql ("SELECT id, rating FROM {videoboard_ratings} WHERE fileid = ? AND rating != 0 ORDER BY time DESC", array($fileid))) {
      foreach ($lists as $list) {
        if (!has_capability('mod/videoboard:teacher', $context, $list->userid)) 
          $c++;
      }
    }
    
    if ($c == 0)
      redirect("view.php?id={$cm->id}", get_string('noevaluetions', 'videoboard'));
}


/// Print the page header
$strvideoboards = get_string('modulenameplural', 'videoboard');
$strvideoboard  = get_string('modulename', 'videoboard');

$PAGE->set_url('/mod/videoboard/view.php', array('id' => $id));
    
$title = $course->shortname . ': ' . format_string($videoboard->name);
$PAGE->set_title($title);
$PAGE->set_heading($course->fullname);

echo $OUTPUT->header();

/// Print the main part of the page

echo html_writer::link(new moodle_url('/mod/videoboard/view.php', array("id" => $cm->id)), get_string("back", "videoboard"));

$table = new html_table();
$table->width = "100%";
$table->head  = array(get_string("table3::cell1::student", "videoboard"), get_string("table3::cell2::peer", "videoboard"), get_string("table3::cell2", "videoboard"));
$table->align = array ("left", "center", "center");

$lists = $DB->get_records_sql ("SELECT id, rating, userid FROM {videoboard_ratings} WHERE fileid = ? AND rating != 0 ORDER BY time DESC", array($fileid));
            
foreach ($lists as $list) {
    $userdata  = $DB->get_record("user", array("id" => $list->userid));
    $picture   = $OUTPUT->user_picture($userdata, array('popup' => true));
    $student   = html_writer::link(new moodle_url('/user/view.php', array("id" => $userdata->id, "course" => $cm->course)), fullname($userdata));
    
    $cell1     = new html_table_cell($picture . " " . $student);
    $cell2     = new html_table_cell($list->rating);
    $cell3     = new html_table_cell(html_writer::link(new moodle_url('/mod/videoboard/deletepeers.php', array("id" => $id, "a" => "delete", "fileid" => $fileid, "ids" => $list->id)), get_string("delete", "videoboard"), array("onclick"=>"return confirm('".get_string("confim", "videoboard")."')")));
    
    $cells = array($cell1, $cell2, $cell3);
    
    $row = new html_table_row($cells);
    
    if (!has_capability('mod/videoboard:teacher', $context, $list->userid)) 
      $table->data[] = $row;
}

echo html_writer::table($table);

/// Finish the page
echo $OUTPUT->footer();



