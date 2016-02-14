<?php

    require_once("../../config.php");
    require_once("lib.php");
    require_once("classAudioFile.php");
    
    $id                     = optional_param('id', 0, PARAM_INT);
    $uid                    = optional_param('uid', 0, PARAM_INT);
    $time                   = optional_param('time', 0, PARAM_INT);
    
    if ($id) {
        if (! $cm = $DB->get_record("course_modules", array("id"=> $id))) {
            error("Course Module ID was incorrect");
        }
    
        if (! $course = $DB->get_record("course", array("id"=> $cm->course))) {
            error("Course is misconfigured");
        }
    
        if (! $videoboard = $DB->get_record("videoboard", array("id"=> $cm->instance))) {
            error("Course module is incorrect");
        }
    } else {
        if (! $videoboard = $DB->get_record("videoboard", array("id"=> $a))) {
            error("Course module is incorrect");
        }
        if (! $course = $DB->get_record("course", array("id"=> $videoboard->course))) {
            error("Course is misconfigured");
        }
        if (! $cm = get_coursemodule_from_instance("videoboard", $videoboard->id, $course->id)) {
            error("Course Module ID was incorrect");
        }
    }
    
    $linktofile = $CFG->wwwroot.'/mod/videoboard/file.php?file='.$videoboard->fileid;
    $file       = videoboard_getfileid($videoboard->fileid);
    
    $AF = new AudioFile;
    if (is_file($file->fullpatch)) {
      $AF->loadFile($file->fullpatch);
      $duration = round($AF->wave_length);
      
      if (empty($duration)) {
        $m = new mp3file($file->fullpatch);
        $a = $m->get_metadata();
        $duration = $a['Length'];
      }
    }
    
    if ($uid)
      $USER = $DB->get_record("user", array("id"=> $uid));
    
    if (empty($time))
      $time = time();
    
    $json = array(
      "play"     => $linktofile,
      "title"    => $videoboard->name,
      "descr"    => strip_tags($videoboard->intro),
      "type"     => 'videoboard',
      "id"       => $id,
      "cid"      => $course->id,
      "uid"      => $USER->id,
      "filename" => str_replace(" ", "_", $USER->username)."_".date("Ymd_Hi", $time),
      "duration" => $duration
    );
    
    echo json_encode($json);
    
