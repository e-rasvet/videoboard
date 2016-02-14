<?php //$Id: mobileupload.php,v 1.2 2012/03/10 22:00:00 Igor Nikulin Exp $

    require_once("../../config.php");
    require_once("lib.php");
    
    $aid                    = optional_param('id', 0, PARAM_INT);
    $cid                    = optional_param('cid', 0, PARAM_INT);
    $filename               = optional_param('filename', 0, PARAM_TEXT);
    
    
    $shotfilename = str_replace(array(".m4a", ".mp3"), "", $filename);
    
    $item = $DB->get_record("videoboard_files", array("filename" => $shotfilename));
    
    $student = $DB->get_record("user", array("id" => $item->userid));
    
    $context = get_context_instance(CONTEXT_MODULE, $aid);
    
    $fs = get_file_storage();
        
      $file_record = new stdClass;
      $file_record->component = 'mod_videoboard';
      $file_record->contextid = $context->id;
      $file_record->userid    = $item->userid;
      $file_record->filearea  = 'private';
      $file_record->filepath  = "/";
      $file_record->itemid    = $item->id;
      $file_record->license   = $CFG->sitedefaultlicense;
      $file_record->author    = fullname($student);
      $file_record->source    = '';
     
    if ($_FILES['media']['tmp_name']) {
      if (strstr($filename, ".m4a")) {   //---IT IS MOV
        $file_record->filename  = $shotfilename.".mov";
        $itemid = $fs->create_file_from_pathname($file_record, $_FILES['media']['tmp_name']);
        
        $DB->set_field("videoboard_files", "itemoldid", $itemid->get_id(), array("id" => $item->id));
      } else {   //---IT IS M4A
        $file_record->filename  = $shotfilename.".m4a";
        $itemid = $fs->create_file_from_pathname($file_record, $_FILES['media']['tmp_name']);
        
        $DB->set_field("videoboard_files", "itemoldid", $itemid->get_id(), array("id" => $item->id));
      }
      
      $add         = new stdClass;
      $add->itemid = $itemid->get_id();
      $add->type   = mimeinfo('type', $file_record->filename);
      $add->status = 'open';
      $add->name   = md5($CFG->wwwroot.'_'.time());
      $add->time   = time();
        
      $DB->insert_record("videoboard_process", $add);
    }
     