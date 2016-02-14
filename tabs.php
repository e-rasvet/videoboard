<?php // $Id: tabs.php,v 1.2 2012/03/10 22:00:00 Igor Nikulin Exp $

    $currenttab = $a;

    if (empty($videoboard)) {
        error('You cannot call this script in that way');
    }
    if (empty($currenttab)) {
        $currenttab = 'list';
    }
    if (!isset($cm)) {
        $cm = get_coursemodule_from_instance('videoboard', $videoboard->id);
    }
    if (!isset($course)) {
        $course = $DB->get_record('course', array('id' => $videoboard->course));
    }

    $tabs     = array();
    $row      = array();
    $inactive = array();

    $row[]  = new tabobject('list', new moodle_url('/mod/videoboard/view.php', array('id'=>$id)), get_string('videoboard_list', 'videoboard'));
    
    if ($videoboard->timedue == 0 || ($videoboard->timedue > 0 && time() < $videoboard->timedue) || $videoboard->preventlate == 1)
      $row[]  = new tabobject('add', new moodle_url('/mod/videoboard/view.php', array('id'=>$id, 'a'=>'add')), get_string('videoboard_add_record', 'videoboard'));
    
    
    $row[]  = new tabobject('history', new moodle_url('/mod/videoboard/viewhistory.php', array('id'=>$id ,'ids'=>$USER->id, 'a'=>'history')), get_string('videoboard_viewhistory', 'videoboard'));
    
    $contextmodule = get_context_instance(CONTEXT_MODULE, $cm->id);
    
    if (has_capability('mod/videoboard:teacher', $contextmodule))
      $row[]  = new tabobject('historybyuser', new moodle_url('/mod/videoboard/viewhistory_by_users.php', array('id'=>$id, 'a'=>'historybyuser')), get_string('videoboard_by_student', 'videoboard'));
    
    $tabs[] = $row;

    print_tabs($tabs, $currenttab, $inactive);
