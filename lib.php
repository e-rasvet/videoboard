<?php  // $Id: lib.php,v 1.2 2012/03/10 22:00:00 Igor Nikulin Exp $

define('VIDEOBOARD_VIDEOTYPES', json_encode(array("video/quicktime", "video/mp4", "video/3gp", "video/3gpp", "video/x-ms-wmv")));
define('VIDEOBOARD_AUDIOTYPES', json_encode(array("audio/x-wav", "audio/mpeg", "audio/wav", "audio/mp4a", "audio/mp4", "audio/mp3", "audio/3gpp")));
    
    
//define('FILTER_ALL', 0);
//define('FILTER_SUBMITTED', 1);
//define('FILTER_REQUIRE_GRADING', 2);

//define('GRADEITEMNUMBER_BEFORE', 0);
//define('GRADEITEMNUMBER_AFTER', 1);

define('VIDEOBOARD_COUNT_WORDS', 1);
define('VIDEOBOARD_COUNT_LETTERS', 2);

function videoboard_add_instance($videoboard) {
    global $USER, $DB;

    $videoboard->timemodified    = time();
    $videoboard->teacher = $USER->id;
    $videoboard->id      = $DB->insert_record('videoboard', $videoboard);
    
    videoboard_grade_item_update($videoboard);
    
    return $videoboard->id;
}

function videoboard_update_instance($videoboard) {
    global $DB;

    $videoboard->timemodified = time();
    $videoboard->id   = $videoboard->instance;
    
    videoboard_grade_item_update($videoboard);

    return $DB->update_record('videoboard', $videoboard);
}


function videoboard_delete_instance($id) {
    global $DB;
    
    if (! $videoboard = $DB->get_record('videoboard', array('id' => $id))) {
        return false;
    }

    $result = true;

    if (! $DB->delete_records('videoboard', array('id' => $videoboard->id))) {
        $result = false;
    }

    return $result;
}


function videoboard_cron () {
    global $DB, $CFG;
    
    
    if ($data = $DB->get_record_sql("SELECT * FROM {videoboard_process} WHERE `status`='open' LIMIT 1")){
      $CFG->videoboard_convert = 0;
      
      if (in_array($data->type, json_decode(VIDEOBOARD_VIDEOTYPES))) 
        $CFG->videoboard_convert = $CFG->videoboard_video_convert;
      else if (in_array($data->type, json_decode(VIDEOBOARD_AUDIOTYPES)))
        $CFG->videoboard_convert = $CFG->videoboard_audio_convert;
        
//Check converting method local or mserver
      if ($CFG->videoboard_convert == 1) 
          if (strstr($CFG->videoboard_convert_url, "ffmpeg"))
              $CFG->videoboard_convert = 2;  //local
      
      

      if ($CFG->videoboard_convert == 1) {
          $from    = videoboard_getfileid($data->itemid);
          
          $add         = new stdClass;
          $add->id     = $data->id;
          $add->status = 'send';
          
          $DB->update_record("videoboard_process", $add);
          
          
          $ch = curl_init();

          if (in_array($data->type, json_decode(VIDEOBOARD_AUDIOTYPES))) 
            $datasend = array('name' => $data->name, 'mconverter_wav' => '@'.$from->fullpatch);
          if (in_array($data->type, json_decode(VIDEOBOARD_VIDEOTYPES))) 
            $datasend = array('name' => $data->name, 'mconverter_m4a' => '@'.$from->fullpatch);
          
          curl_setopt($ch, CURLOPT_URL, $CFG->videoboard_convert_url.'/send.php');
          curl_setopt($ch, CURLOPT_POST, 1);
          curl_setopt($ch, CURLOPT_POSTFIELDS, $datasend);
          curl_exec($ch);
      } else if ($CFG->videoboard_convert == 3){
          $from        = videoboard_getfileid($data->itemid);
          
          $add         = new stdClass;
          $add->id     = $data->id;
          $add->status = 'send';
          
          $DB->update_record("videoboard_process", $add);

          if (in_array($data->type, json_decode(VIDEOBOARD_VIDEOTYPES))) {
            if ($item = $DB->get_record("videoboard_files", array("itemoldid" => $data->itemid))) 
              $table = 'videoboard_files';
            else if ($item = $DB->get_record("videoboard_comments", array("itemoldid" => $data->itemid))) 
              $table = 'videoboard_comments';
          
            @set_include_path($CFG->dirroot.'/mod/videoboard/library');
            
            require_once("Zend/Gdata/ClientLogin.php");
            require_once("Zend/Gdata/HttpClient.php");
            require_once("Zend/Gdata/YouTube.php");
            require_once("Zend/Gdata/App/HttpException.php");
            require_once('Zend/Uri/Http.php');
            
            $authenticationURL= 'https://www.google.com/youtube/accounts/ClientLogin';
            $httpClient = Zend_Gdata_ClientLogin::getHttpClient(
                                                      $username = $CFG->videoboard_youtube_email,
                                                      $password = $CFG->videoboard_youtube_password,
                                                      $service = 'youtube',
                                                      $client = null,
                                                      $source = 'VideoBoard',
                                                      $loginToken = null,
                                                      $loginCaptcha = null,
                                                      $authenticationURL);
                                                      
            $yt = new Zend_Gdata_YouTube($httpClient, 'VideoBoard', NULL, $CFG->videoboard_youtube_apikey);
            
            $myVideoEntry = new Zend_Gdata_YouTube_VideoEntry();
            
/// unlisted upload
            $accessControlElement = new Zend_Gdata_App_Extension_Element(
                'yt:accessControl', 'yt', 'http://gdata.youtube.com/schemas/2007', ''
            );
            $accessControlElement->extensionAttributes = array(
                array(
              'namespaceUri' => '',
              'name' => 'action',
              'value' => 'list'
                ),
                array(
              'namespaceUri' => '',
              'name' => 'permission',
              'value' => 'denied'
              ));
         
            $myVideoEntry->extensionElements = array($accessControlElement);
            
            $filesource = $yt->newMediaFileSource($from->fullpatch);
            $filesource->setContentType($data->type);
            $filesource->setSlug('slug');
            $myVideoEntry->setMediaSource($filesource);
            
            $myVideoEntry->setVideoTitle($from->author);
            $myVideoEntry->setVideoDescription($from->author);
            $myVideoEntry->setVideoCategory('Education');
            $myVideoEntry->SetVideoTags('videoboard');
            //$myVideoEntry->setVideoDeveloperTags(array($item->id));

            //$yt->registerPackage('Zend_Gdata_Geo');
            //$yt->registerPackage('Zend_Gdata_Geo_Extension');
            //$where = $yt->newGeoRssWhere();
            //$position = $yt->newGmlPos('37.0 -122.0');
            //$where->point = $yt->newGmlPoint($position);
            //$myVideoEntry->setWhere($where);

            $uploadUrl = 'http://uploads.gdata.youtube.com/feeds/api/users/default/uploads';

            try {
              $newEntry = $yt->insertEntry($myVideoEntry, $uploadUrl, 'Zend_Gdata_YouTube_VideoEntry');
            } catch (Zend_Gdata_App_HttpException $httpException) {
              echo $httpException->getRawResponseBody();
              
              $DB->delete_records('videoboard_process', array('id' => $data->id));
            } catch (Zend_Gdata_App_Exception $e) {
              echo $e->getMessage();
              
              $DB->delete_records('videoboard_process', array('id' => $data->id));
            }
            
            $itemidyoutube = $newEntry->getVideoId();

            if (!empty($itemidyoutube)) 
              $DB->set_field($table, "itemyoutube", $itemidyoutube, array("id" => $item->id));
            
            $DB->delete_records('videoboard_process', array('id' => $data->id));
          } else {
            $DB->delete_records('videoboard_process', array('id' => $data->id));
          }
      } else if ($CFG->videoboard_convert == 2){
      
///Old method
          $DB->delete_records('videoboard_process', array('id' => $data->id));
        
          if (!$item = $DB->get_record("videoboard_files", array("itemoldid" => $data->itemid))) {
            $item = $DB->get_record("videoboard_comments", array("itemoldid" => $data->itemid));
            $table = 'videoboard_comments';
          } else
            $table = 'videoboard_files';
            
          $student = $DB->get_record("user", array("id" => $item->userid));
          
          $context = get_context_instance(CONTEXT_MODULE, $item->instance);
          
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
          
          if (in_array($data->type, json_decode(VIDEOBOARD_VIDEOTYPES))) {
            $from    = videoboard_getfileid($data->itemid);
            $to      = $CFG->dataroot."/temp/".$item->filename.".mp4";
            $toimg   = $CFG->dataroot."/temp/".$item->filename.".jpg";
            
            videoboard_runExternal("/opt/handbrake/HandBrakeCLI -Z Universal -i {$from->fullpatch} -o {$to} -w 432 -l 320", $code); 
            videoboard_runExternal("{$CFG->videoboard_convert_url} -i {$to} -f image2 -s 432x320 {$toimg}", $code); 
            
            $file_record->filename  = $item->filename.".mp4";
            $itemid = $fs->create_file_from_pathname($file_record, $to);
            
            $file_record->filename  = $item->filename.".jpg";
            $itemimgid = $fs->create_file_from_pathname($file_record, $toimg);
            
            $DB->set_field($table, "itemid", $itemid->get_id(), array("id" => $item->id));
            $DB->set_field($table, "itemimgid", $itemimgid->get_id(), array("id" => $item->id));
            
            unlink($to);
            unlink($toimg);
          } else if (in_array($data->type, json_decode(VIDEOBOARD_AUDIOTYPES))) {
            $from = videoboard_getfileid($data->itemid);
            $to   = $CFG->dataroot."/temp/".$item->filename.".mp3";
            
            videoboard_runExternal("{$CFG->videoboard_convert_url} -y -i {$from->fullpatch} -acodec libmp3lame -ab 68k -ar 44100 {$to}", $code); 
            
            $file_record->filename  = $item->filename.".mp3";
            $itemid = $fs->create_file_from_pathname($file_record, $to);
            
            $DB->set_field($table, "itemid", $itemid->get_id(), array("id" => $item->id));
            
            unlink($to);
          }
      }
    }
  
  
///Check convert server file ready
    if ($dataall = $DB->get_records_sql("SELECT * FROM {videoboard_process} WHERE `status` = 'send'")){
        foreach($dataall as $data){
          if (!$item = $DB->get_record("videoboard_files", array("itemoldid" => $data->itemid))) {
            if (!$item = $DB->get_record("videoboard_comments", array("itemoldid" => $data->itemid))) {
              if ($videoboard = $DB->get_record("videoboard", array("fileid" => $data->itemid))){
                $module   = $DB->get_record("modules", array("name" => "videoboard"));
                $instance = $DB->get_record("course_modules", array("module" => $module->id, "instance" => $videoboard->id));
              
                $item           = new stdClass;
                $item->userid   = $videoboard->teacher;
                $item->instance = $instance->id;
                $item->id       = $videoboard->id;
                $item->filename = "videoboard_".$videoboard->id;
                
                $table = 'videoboard';
              } else {
                $DB->delete_records('videoboard_process', array('id' => $data->id));
                
                return true;
              }
            } else {
              $table = 'videoboard_comments';
            }
          } else {
            $table = 'videoboard_files';
          }
            
          $student = $DB->get_record("user", array("id" => $item->userid));
          
          $context = get_context_instance(CONTEXT_MODULE, $item->instance);
          
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
          
          if (in_array($data->type, json_decode(VIDEOBOARD_VIDEOTYPES)) && $CFG->videoboard_video_convert == 1) {
            $json = json_decode(file_get_contents($CFG->videoboard_convert_url."/get.php?name={$data->name}.mp4"));
            $jsonimg = json_decode(file_get_contents($CFG->videoboard_convert_url."/get.php?name={$data->name}.jpg"));
          } else if ($CFG->videoboard_audio_convert == 1)
            $json = json_decode(file_get_contents($CFG->videoboard_convert_url."/get.php?name={$data->name}.mp3"));

          if (@!empty($json->url)){
            $DB->delete_records('videoboard_process', array('id' => $data->id));
            
            if (in_array($data->type, json_decode(VIDEOBOARD_VIDEOTYPES))) {
              $to   = $CFG->dataroot."/temp/".$item->filename.".mp4";
              file_put_contents($to, file_get_contents($json->url));
            
              $file_record->filename  = $item->filename.".mp4";

              $itemid = $fs->create_file_from_pathname($file_record, $to);
              
              $file = videoboard_getfileid($itemid->get_id());
              @chmod($file->fullpatch, 0755);
            
              $DB->set_field($table, "itemid", $itemid->get_id(), array("id" => $item->id));
              
              
              $toimg   = $CFG->dataroot."/temp/".$item->filename.".jpg";
              file_put_contents($toimg, file_get_contents($jsonimg->url));
              $file_record->filename  = $item->filename.".jpg";
              $itemid = $fs->create_file_from_pathname($file_record, $toimg);
              
              $file = videoboard_getfileid($itemid->get_id());
              @chmod($file->fullpatch, 0755);
              
              $DB->set_field($table, "itemimgid", $itemid->get_id(), array("id" => $item->id));
            } else {
              $to   = $CFG->dataroot."/temp/".$item->filename.".mp3";
              file_put_contents($to, file_get_contents($json->url));
            
              $file_record->filename  = $item->filename.".mp3";

              $itemid = $fs->create_file_from_pathname($file_record, $to);
              
              $file = videoboard_getfileid($itemid->get_id());
              @chmod($file->fullpatch, 0755);
            
              $DB->set_field($table, "itemid", $itemid->get_id(), array("id" => $item->id));
            }
            
            unlink($to);
            @unlink($toimg);
            break;
          }
        }
    }
      
      
    return true;
}



function videoboard_supports($feature) {
    switch($feature) {
        case FEATURE_GROUPS:                  return true;
        case FEATURE_GROUPINGS:               return true;
        case FEATURE_GROUPMEMBERSONLY:        return true;
        case FEATURE_MOD_INTRO:               return true;
        case FEATURE_COMPLETION_TRACKS_VIEWS: return true;
        case FEATURE_GRADE_HAS_GRADE:         return true;
        case FEATURE_GRADE_OUTCOMES:          return true;
        case FEATURE_GRADE_HAS_GRADE:         return true;
        case FEATURE_BACKUP_MOODLE2:          return false;
        case FEATURE_SHOW_DESCRIPTION:        return true;
        case FEATURE_ADVANCED_GRADING:        return true;

        default: return null;
    }
}

function videoboard_grading_areas_list(){
  return array('submission' => get_string('submissions', 'mod_videoboard'));
}

/*
function videoboard_scale_used_anywhere($scaleid) {
    global $DB;
    
    if ($scaleid and $DB->record_exists('videoboard', array('grade' => -$scaleid))) {
        return true;
    } else {
        return false;
    }
}
*/

/*
function videoboard_install() {
    return true;
}


function videoboard_uninstall() {
    return true;
}
*/

function videoboard_runExternal( $cmd, &$code ) {

   $descriptorspec = array(
       0 => array("pipe", "r"), 
       1 => array("pipe", "w"), 
       2 => array("pipe", "w") 
   );

   $pipes= array();
   $process = proc_open($cmd, $descriptorspec, $pipes);

   $output= "";

   if (!is_resource($process)) return false;

   fclose($pipes[0]);

   stream_set_blocking($pipes[1],false);
   stream_set_blocking($pipes[2],false);

   $todo= array($pipes[1],$pipes[2]);

   while( true ) {
       $read= array();
       if( !feof($pipes[1]) ) $read[]= $pipes[1];
       if( !feof($pipes[2]) ) $read[]= $pipes[2];

       if (!$read) break;

       $ready= stream_select($read, $write=NULL, $ex= NULL, 2);

       if ($ready === false) {
           break; 
       }

       foreach ($read as $r) {
           $s= fread($r,1024);
           $output.= $s;
       }
   }

   fclose($pipes[1]);
   fclose($pipes[2]);

   $code= proc_close($process);

   return $output;
}


function videoboard_sort_table_data ($data, $titlesarray, $orderby, $sort) {
    global $USER, $CFG;

    $j = 0;
    if ($sort) {
        foreach ($titlesarray as $titlesarray_) {
            if ($titlesarray_ == $sort) {
                $orderkey = $j;
            }
            $j++;
        }
    } else {
        $orderkey = 0;
    }

    $i = 0;

    foreach ($data as $datakey => $datavalue) {
        if (!is_array($datavalue[$orderkey])) {
            $key = $datavalue[$orderkey];
        }  else  {
            $key = $datavalue[$orderkey][1];
        }

        for ($j=0; $j < count($datavalue); $j++) {
            if (!is_array($datavalue[$j])) {
                $newarray[(string)$key][$i][$j] = $datavalue[$j];
            }  else  {
                $newarray[(string)$key][$i][$j] = $datavalue[$j][0];
            }
        }
        
        $i ++;
    }
    
    if (empty($orderby) || $orderby == "ASC") {
        ksort ($newarray); 
    }  else  {
        krsort ($newarray);
    }
    
    reset($newarray);
    
    foreach ($newarray as $newarray_) {
        foreach ($newarray_ as $newarray__) {
            $newarraynew = array ();
            foreach ($newarray__ as $newarray___) {
                $newarraynew[] = $newarray___;
            }
            $finaldata[] = $newarraynew;
        }
    }
    
    return $finaldata;
}



function videoboard_make_table_headers ($titlesarray, $orderby, $sort, $link) {
    global $USER, $CFG;

    if ($orderby == "ASC") {
        $columndir    = "DESC";
        $columndirimg = "down";
    } else {
        $columndir    = "ASC";
        $columndirimg = "up";
    }

    foreach ($titlesarray as $titlesarraykey => $titlesarrayvalue) {
        if ($sort != $titlesarrayvalue) {
            $columnicon = "";
        } else {
            $iconlink   = new moodle_url("/theme/image.php", array("theme" => $CFG->theme, "image" => "t/{$columndirimg}", "rev" => $CFG->themerev));
            $columnicon = " <img src=\"{$iconlink}\" alt=\"\" />";
        }
        if (!empty($titlesarrayvalue)) {
            $table->head[] = "<a href=\"".$link."&sort=$titlesarrayvalue&orderby=$columndir\">$titlesarraykey</a>$columnicon";
        } else {
            $table->head[] = "$titlesarraykey";
        } 
    }
    
    return $table->head;
}



function videoboard_set_rait ($fileid, $type) {
    global $CFG, $USER, $DB, $videoboard, $id;
    
    $list = $DB->get_record ("videoboard_files", array("id" => $fileid));
    
    $cm = get_coursemodule_from_id(NULL, $list->instance);
    
    $context = get_context_instance(CONTEXT_MODULE, $cm->id);
    
    $rate        = 0;
    $cuser       = 0;
    $rateteacher = 0;
    $cteacher    = 0;
    $deletepeer  = "";
    $deletelike  = "";
    
    if (has_capability('mod/videoboard:teacher', $context))
      $deletepeer = html_writer::tag('div', html_writer::link(new moodle_url('/mod/videoboard/deletepeers.php', array("id" => $cm->id, "a" => "delete", "fileid" => $fileid)), get_string("delete", "videoboard")));
      
    if (has_capability('mod/videoboard:teacher', $context))
      $deletelike = html_writer::tag('div', html_writer::link(new moodle_url('/mod/videoboard/deletelikes.php', array("id" => $cm->id, "a" => "delete", "fileid" => $fileid)), get_string("delete", "videoboard")));

    if ($allvoites = $DB->get_records("videoboard_ratings", array("fileid" => $fileid))) {
      foreach ($allvoites as $allvoite) {
        if (!has_capability('mod/videoboard:teacher', $context, $allvoite->userid) && !empty($allvoite->rating)) {
           $rate += $allvoite->rating;
           $cuser++;
        }
      }
      if ($cuser > 0)
        $rate = round ($rate/$cuser,1);

      foreach ($allvoites as $allvoite) {
        if (has_capability('mod/videoboard:teacher', $context, $allvoite->userid) && !empty($allvoite->rating)) {
          $rateteacher += $allvoite->rating;
          $cteacher++;
        }
      }
      if ($cteacher > 0) 
        $rateteacher = round ($rateteacher/$cteacher,1);
    }
    
    if ($cuser == 0) { 
      $rate = get_string('norateyet', 'videoboard');
      $deletepeer = "";
    }
    
    if ($cteacher == 0) $rateteacher = get_string('norateyet', 'videoboard');
    
/*
* Take from Rubtic
*/
    
    $catdata = $DB->get_record("grade_items", array("courseid" => $cm->course, "iteminstance" => $cm->instance, "itemmodule" => 'videoboard'));
    if ($grid = $DB->get_record("grade_grades", array("itemid" => $catdata->id, "userid" => $list->userid)))
      $rateteacher = round($grid->finalgrade, 1);

    $levels = array("-");
    for ($i=1; $i <= $catdata->grademax; $i++) {
        $levels[] = $i;
    }

    $levelst = array("-");
    for ($i=1; $i <= $videoboard->gradet; $i++) {
        $levelst[] = $i;
    }
    
    $o = "";
    
    $o .= html_writer::start_tag('div');
    
    if ($currentuserrate = $DB->get_record("videoboard_ratings", array("fileid" => $fileid, "userid" => $list->userid))) {
      if ($type == 1) {
        if (empty($currentuserrate->ratingrhythm)) $currentuserrate->ratingrhythm = get_string('norateyet', 'videoboard');
        $o .= html_writer::tag('small', $currentuserrate->ratingrhythm);
      }
      if ($type == 2) {
        if (empty($currentuserrate->ratingclear)) $currentuserrate->ratingclear = get_string('norateyet', 'videoboard');
        $o .= html_writer::tag('small', $currentuserrate->ratingclear);
      }
      if ($type == 3) {
        if (empty($currentuserrate->ratingintonation)) $currentuserrate->ratingintonation = get_string('norateyet', 'videoboard');
        $o .= html_writer::tag('small', $currentuserrate->ratingintonation);
      }
      if ($type == 4) {
        if (empty($currentuserrate->ratingspeed)) $currentuserrate->ratingspeed = get_string('norateyet', 'videoboard');
        $o .= html_writer::tag('small', $currentuserrate->ratingspeed);
      }
      if ($type == 5) {
        if (empty($currentuserrate->ratingreproduction)) $currentuserrate->ratingreproduction = get_string('norateyet', 'videoboard');
        $o .= html_writer::tag('small', $currentuserrate->ratingreproduction);
      }
    } else {
      if ($type < 6) {
        $o .= html_writer::tag('small', get_string('norateyet', 'videoboard'));
      }
    }
    
    if ($type == 6) {
      if($videoboard->grademethod == "like") {
        if ($crlike = $DB->count_records("videoboard_likes", array("fileid" => $fileid)))
          $o .= html_writer::tag('div', $crlike, array('class'=>'vs-like-grade')). " " . $deletelike;
      } else
        $o .= html_writer::tag('small', $rate) . " " . $deletepeer;
    }
    
    if ($type == 7) {
      if ($USER->id == $list->userid || has_capability('mod/videoboard:teacher', $context))
        $o .= html_writer::tag('small', $rateteacher);
      else
        $o .= html_writer::tag('small', get_string('private', 'videoboard'));
    }

    if ($USER->id == $list->userid && $type < 6) {
      $o .= html_writer::select($levels, 'rating', '', true, array("class" => "videoboard_rate_box", "data-url" => "{$fileid}::{$type}"));
    } else if ($USER->id != $list->userid) {
      if ($type == 6 && !has_capability('mod/videoboard:teacher', $context)) {
        if($videoboard->grademethod == "like") {
          if ($crlike = $DB->get_record("videoboard_likes", array("fileid" => $fileid, "userid" => $USER->id)))
            $o .= html_writer::link(new moodle_url('/mod/videoboard/view.php', array("id" => $id, "fileid" => $fileid, "act"=>"dellike")), html_writer::empty_tag("img", array("src" => new moodle_url('/mod/videoboard/img/flike.png'), "alt" => get_string("likethis", "videoboard"), "title" => get_string("dislike", "videoboard"), "class"=>"vs-like-dis")));
          else
            $o .= html_writer::link(new moodle_url('/mod/videoboard/view.php', array("id" => $id, "fileid" => $fileid, "act"=>"addlike")), html_writer::empty_tag("img", array("src" => new moodle_url('/mod/videoboard/img/flike.png'), "alt" => get_string("likethis", "videoboard"), "title" => get_string("like", "videoboard"))));
        } else
          $o .= html_writer::select($levels, 'rating', '', true, array("class" => "videoboard_rate_box", "data-url" => "{$fileid}::{$type}"))."";
      }

      if ($type == 7 && has_capability('mod/videoboard:teacher', $context) && $videoboard->grademethodt == "default") 
        $o .= html_writer::select($levelst, 'rating', '', true, array("class" => "videoboard_rate_box", "data-url" => "{$fileid}::{$type}"));
    }
    
    $o .= html_writer::end_tag('div');
    
    return $o;
}


function videoboard_get_pages($table, $page, $perpage) {
    global $CFG, $course;
    
    $totalcount = count ($table);
    $startrec  = $page * $perpage;
    $finishrec = $startrec + $perpage;
    
    foreach ($table as $key => $value) {
        if ($key >= $startrec && $key < $finishrec) {
            $viewtable[] = $value;
        }
    }
    
    return array($totalcount, $viewtable, $startrec, $finishrec, $page);
}


function videoboard_player($ids, $table = "videoboard_files"){
    global $DB, $CFG, $videoboard_vc, $cm;
    
    $context = get_context_instance(CONTEXT_MODULE, $cm->id);
    
    if (!isset($videoboard_vc)) $videoboard_vc = 0;
    
    $videoboard_vc++;
    
    if ($data = $DB->get_record($table, array("id" => $ids))) {
      if (!empty($data->itemyoutube)) {
        $o = videoboard_player_youtube($data->itemyoutube, $data->id);
      } else if ($data->itemid == 0) {
///Show MOV video file
        if ($itemold = $DB->get_record("files", array("id" => $data->itemoldid))) {
            if (in_array($itemold->mimetype, json_decode(VIDEOBOARD_VIDEOTYPES))) {
                $link = new moodle_url("/pluginfile.php/".$itemold->contextid."/mod_videoboard/".$ids."/".$itemold->id."/".$itemold->filename);
                
                $o = videoboard_player_video($link, $itemold->mimetype, null, $itemold->id);
            } else {
                if(!$DB->get_record("videoboard_process", array("itemid"=>$data->itemoldid)))
                  $o = get_string('pleaseerrorinprocess', 'videoboard');
                else
                  $o = get_string('pleasewaitinprocess', 'videoboard');
            }
        }
      } else {
///Show mp4 converted video file
        if ($item = $DB->get_record("files", array("id" => $data->itemid))) {
          $link = new moodle_url("/pluginfile.php/".$item->contextid."/mod_videoboard/".$ids."/".$data->itemid."/".$item->filename);

          if (in_array($item->mimetype, json_decode(VIDEOBOARD_AUDIOTYPES))) {
              $o = videoboard_player_mp3($link, $item->mimetype, $item->id);
          } else if (in_array($item->mimetype, json_decode(VIDEOBOARD_VIDEOTYPES))) {
              $itemimg = $DB->get_record("files", array("id" => $data->itemimgid));
              $linkimg = new moodle_url("/pluginfile.php/".$itemimg->contextid."/mod_videoboard/".$ids."/".$data->itemimgid);
              
              $o = videoboard_player_video($link, $item->mimetype, $linkimg, $item->id);
          }
          
        }
      }
    }

    return $o;
}


function videoboard_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload) {
    global $CFG, $DB;
    
    $id = array_shift($args);

    if ($context->contextlevel != CONTEXT_MODULE) {
        return false;
    }

    require_login($course, false, $cm);

    if (!$videoboard = $DB->get_record('videoboard', array('id'=>$cm->instance))) {
        return false;
    }
    
    $f = get_file_storage();

    if ($file_record = $DB->get_record('files', array('id'=>$id))) {
      $file = $f->get_file_instance($file_record);
      videoboard_send_stored_file($file, 86400, 0, false);  //forcedownload false
      
      //send_stored_file($file);  //forcedownload false
    } else {
      send_file_not_found();
    }
}



function videoboard_is_ios(){
  if (strstr($_SERVER['HTTP_USER_AGENT'], "iPhone") || strstr($_SERVER['HTTP_USER_AGENT'], "iPad")) 
    return true;
  else
    return false;
}

function videoboard_getfile($itemid){
  global $DB, $CFG;
  
  if ($file = $DB->get_record_sql("SELECT * FROM {files} WHERE `itemid`=? AND `filesize` != 0", array($itemid))){
    $contenthash = $file->contenthash;
    $l1 = $contenthash[0].$contenthash[1];
    $l2 = $contenthash[2].$contenthash[3];
    $filepatch = $CFG->dataroot."/filedir/$l1/$l2/$contenthash";
    
    $file->fullpatch = $filepatch;
    
    return $file;
  } else
    return false;
}

function videoboard_getfileid($itemid){
  global $DB, $CFG;
  
  if ($file = $DB->get_record("files", array("id" => $itemid))){
    $contenthash = $file->contenthash;
    $l1 = $contenthash[0].$contenthash[1];
    $l2 = $contenthash[2].$contenthash[3];
    $filepatch = $CFG->dataroot."/filedir/$l1/$l2/$contenthash";
    
    $file->fullpatch = $filepatch;
    
    return $file;
  } else
    return false;
}


function videoboard_send_stored_file($stored_file, $lifetime=86400 , $filter=0, $forcedownload=false, $filename=null, $dontdie=false) {
    global $CFG, $COURSE, $SESSION;

    if (!$stored_file or $stored_file->is_directory()) {
        // nothing to serve
        if ($dontdie) {
            return;
        }
        die;
    }

    if ($dontdie) {
        ignore_user_abort(true);
    }

    session_get_instance()->write_close(); // unlock session during fileserving

    // Use given MIME type if specified, otherwise guess it using mimeinfo.
    // IE, Konqueror and Opera open html file directly in browser from web even when directed to save it to disk :-O
    // only Firefox saves all files locally before opening when content-disposition: attachment stated
    
    $filename     = is_null($filename) ? $stored_file->get_filename() : $filename;
    $isFF         = check_browser_version('Firefox', '1.5'); // only FF > 1.5 properly tested
    $mimetype     = ($forcedownload and !$isFF) ? 'application/x-forcedownload' :
                         ($stored_file->get_mimetype() ? $stored_file->get_mimetype() : mimeinfo('type', $filename));

    $lastmodified = $stored_file->get_timemodified();
    $filesize     = $stored_file->get_filesize();

    if ($lifetime > 0 && !empty($_SERVER['HTTP_IF_MODIFIED_SINCE'])) {
        // get unixtime of request header; clip extra junk off first
        $since = strtotime(preg_replace('/;.*$/','',$_SERVER["HTTP_IF_MODIFIED_SINCE"]));
        if ($since && $since >= $lastmodified) {
            header('HTTP/1.1 304 Not Modified');
            header('Expires: '. gmdate('D, d M Y H:i:s', time() + $lifetime) .' GMT');
            header('Cache-Control: max-age='.$lifetime);
            header('Content-Type: '.$mimetype);
            if ($dontdie) {
                return;
            }
            die;
        }
    }

    //do not put '@' before the next header to detect incorrect moodle configurations,
    //error should be better than "weird" empty lines for admins/users
    header('Last-Modified: '. gmdate('D, d M Y H:i:s', $lastmodified) .' GMT');

    // if user is using IE, urlencode the filename so that multibyte file name will show up correctly on popup
    if (check_browser_version('MSIE')) {
        $filename = rawurlencode($filename);
    }

    if ($forcedownload) {
        header('Content-Disposition: attachment; filename="'.$filename.'"');
    } else {
        header('Content-Disposition: inline; filename="'.$filename.'"');
    }

        header('Cache-Control: max-age='.$lifetime);
        header('Expires: '. gmdate('D, d M Y H:i:s', time() + $lifetime) .' GMT');
        header('Pragma: ');
        header('Accept-Ranges: bytes');

        if (!empty($_SERVER['HTTP_RANGE']) && strpos($_SERVER['HTTP_RANGE'],'bytes=') !== FALSE) {
            // byteserving stuff - for acrobat reader and download accelerators
            // see: http://www.w3.org/Protocols/rfc2616/rfc2616-sec14.html#sec14.35
            // inspired by: http://www.coneural.org/florian/papers/04_byteserving.php
            $ranges = false;
            if (preg_match_all('/(\d*)-(\d*)/', $_SERVER['HTTP_RANGE'], $ranges, PREG_SET_ORDER)) {
                foreach ($ranges as $key=>$value) {
                    if ($ranges[$key][1] == '') {
                        //suffix case
                        $ranges[$key][1] = $filesize - $ranges[$key][2];
                        $ranges[$key][2] = $filesize - 1;
                    } else if ($ranges[$key][2] == '' || $ranges[$key][2] > $filesize - 1) {
                        //fix range length
                        $ranges[$key][2] = $filesize - 1;
                    }
                    if ($ranges[$key][2] != '' && $ranges[$key][2] < $ranges[$key][1]) {
                        //invalid byte-range ==> ignore header
                        $ranges = false;
                        break;
                    }
                    //prepare multipart header
                    $ranges[$key][0] =  "\r\n--".BYTESERVING_BOUNDARY."\r\nContent-Type: $mimetype\r\n";
                    $ranges[$key][0] .= "Content-Range: bytes {$ranges[$key][1]}-{$ranges[$key][2]}/$filesize\r\n\r\n";
                }
            } else {
                $ranges = false;
            }
            if ($ranges) {
                byteserving_send_file($stored_file->get_content_file_handle(), $mimetype, $ranges, $filesize);
            }
        }

    if (empty($filter)) {
        if ($mimetype == 'text/plain') {
            header('Content-Type: Text/plain; charset=utf-8'); //add encoding
        } else {
            header('Content-Type: '.$mimetype);
        }
        header('Content-Length: '.$filesize);

        //flush the buffers - save memory and disable sid rewrite
        //this also disables zlib compression
        videoboard_prepare_file_content_sending();

        // send the contents
        $stored_file->readfile();
    } else {     // Try to put the file through filters
        if ($mimetype == 'text/html') {
            $options = new stdClass();
            $options->noclean = true;
            $options->nocache = true; // temporary workaround for MDL-5136
            $text = $stored_file->get_content();
            $text = file_modify_html_header($text);
            $output = format_text($text, FORMAT_HTML, $options, $COURSE->id);

            header('Content-Length: '.strlen($output));
            header('Content-Type: text/html');

            //flush the buffers - save memory and disable sid rewrite
            //this also disables zlib compression
            videoboard_prepare_file_content_sending();

            // send the contents
            echo $output;
        } else if (($mimetype == 'text/plain') and ($filter == 1)) {
            // only filter text if filter all files is selected
            $options = new stdClass();
            $options->newlines = false;
            $options->noclean = true;
            $text = $stored_file->get_content();
            $output = '<pre>'. format_text($text, FORMAT_MOODLE, $options, $COURSE->id) .'</pre>';

            header('Content-Length: '.strlen($output));
            header('Content-Type: text/html; charset=utf-8'); //add encoding

            //flush the buffers - save memory and disable sid rewrite
            //this also disables zlib compression
            videoboard_prepare_file_content_sending();

            // send the contents
            echo $output;
        } else {    // Just send it out raw
            header('Content-Length: '.$filesize);
            header('Content-Type: '.$mimetype);

            //flush the buffers - save memory and disable sid rewrite
            //this also disables zlib compression
            videoboard_prepare_file_content_sending();

            // send the contents
            $stored_file->readfile();
        }
    }
    
    if ($dontdie) {
        return;
    }
    die; //no more chars to output!!!
}


function videoboard_prepare_file_content_sending() {
    $olddebug = error_reporting(0);

    if (ini_get_bool('zlib.output_compression')) {
        ini_set('zlib.output_compression', 'Off');
    }

    while(ob_get_level()) {
        if (!ob_end_flush()) {
            break;
        }
    }

    error_reporting($olddebug);
}


function videoboard_rangeDownload($file) {
  $fp = @fopen($file, 'rb');
 
  $size   = filesize($file); 
  $length = $size;   
  $start  = 0;   
  $end= $size - 1;   

  header("Accept-Ranges: 0-$length");

  if (isset($_SERVER['HTTP_RANGE'])) {
    $c_start = $start;
    $c_end   = $end;

    list(, $range) = explode('=', $_SERVER['HTTP_RANGE'], 2);

    if (strpos($range, ',') !== false) {
      header('HTTP/1.1 416 Requested Range Not Satisfiable');
      header("Content-Range: bytes $start-$end/$size");
      exit;
    }

    if ($range0 == '-') {
      $c_start = $size - substr($range, 1);
    } else {
      $range  = explode('-', $range);
      $c_start = $range[0];
      $c_end   = (isset($range[1]) && is_numeric($range[1])) ? $range[1] : $size;
    }

    $c_end = ($c_end > $end) ? $end : $c_end;

    if ($c_start > $c_end || $c_start > $size - 1 || $c_end >= $size) {
      header('HTTP/1.1 416 Requested Range Not Satisfiable');
      header("Content-Range: bytes $start-$end/$size");
      exit;
    }
    $start  = $c_start;
    $end= $c_end;
    $length = $end - $start + 1; 
    fseek($fp, $start);
    header('HTTP/1.1 206 Partial Content');
  }

  header("Content-Range: bytes $start-$end/$size");
  header("Content-Length: $length");
 
  $buffer = 1024 * 8;
  while(!feof($fp) && ($p = ftell($fp)) <= $end) {
    if ($p + $buffer > $end) {
      $buffer = $end - $p + 1;
    }
    set_time_limit(0); 
    echo fread($fp, $buffer);
    flush(); 
  }
 
  fclose($fp);
}




/** Include eventslib.php */
require_once($CFG->libdir.'/eventslib.php');
/** Include formslib.php */
require_once($CFG->libdir.'/formslib.php');
/** Include calendar/lib.php */
require_once($CFG->dirroot.'/calendar/lib.php');

/** videoboard_COUNT_WORDS = 1 */
define('videoboard_COUNT_WORDS', 1);
/** videoboard_COUNT_LETTERS = 2 */
define('videoboard_COUNT_LETTERS', 2);

/**
 * Standard base class for all videoboard submodules (videoboard types).
 *
 * @package   mod-videoboard
 * @copyright 1999 onwards Martin Dougiamas  {@link http://moodle.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class videoboard_base {

    const FILTER_ALL             = 0;
    const FILTER_SUBMITTED       = 1;
    const FILTER_REQUIRE_GRADING = 2;

    /** @var object */
    var $cm;
    /** @var object */
    var $course;
    /** @var stdClass */
    var $coursecontext;
    /** @var object */
    var $videoboard;
    /** @var string */
    var $strvideoboard;
    /** @var string */
    var $strvideoboards;
    /** @var string */
    var $strsubmissions;
    /** @var string */
    var $strlastmodified;
    /** @var string */
    var $pagetitle;
    /** @var bool */
    var $usehtmleditor;
    /**
     * @todo document this var
     */
    var $defaultformat;
    /**
     * @todo document this var
     */
    var $context;
    /** @var string */
    var $type;

    /**
     * Constructor for the base videoboard class
     *
     * Constructor for the base videoboard class.
     * If cmid is set create the cm, course, videoboard objects.
     * If the videoboard is hidden and the user is not a teacher then
     * this prints a page header and notice.
     *
     * @global object
     * @global object
     * @param int $cmid the current course module id - not set for new videoboards
     * @param object $videoboard usually null, but if we have it we pass it to save db access
     * @param object $cm usually null, but if we have it we pass it to save db access
     * @param object $course usually null, but if we have it we pass it to save db access
     */
    function videoboard_base($cmid='staticonly', $videoboard=NULL, $cm=NULL, $course=NULL) {
        global $COURSE, $DB;

        if ($cmid == 'staticonly') {
            //use static functions only!
            return;
        }

        global $CFG;

        if ($cm) {
            $this->cm = $cm;
        } else if (! $this->cm = get_coursemodule_from_id('videoboard', $cmid)) {
            print_error('invalidcoursemodule');
        }

        $this->context = get_context_instance(CONTEXT_MODULE, $this->cm->id);

        if ($course) {
            $this->course = $course;
        } else if ($this->cm->course == $COURSE->id) {
            $this->course = $COURSE;
        } else if (! $this->course = $DB->get_record('course', array('id'=>$this->cm->course))) {
            print_error('invalidid', 'videoboard');
        }
        $this->coursecontext = get_context_instance(CONTEXT_COURSE, $this->course->id);
        $courseshortname = format_text($this->course->shortname, true, array('context' => $this->coursecontext));

        if ($videoboard) {
            $this->videoboard = $videoboard;
        } else if (! $this->videoboard = $DB->get_record('videoboard', array('id'=>$this->cm->instance))) {
            print_error('invalidid', 'videoboard');
        }

        $this->videoboard->cmidnumber = $this->cm->idnumber; // compatibility with modedit videoboard obj
        $this->videoboard->courseid   = $this->course->id; // compatibility with modedit videoboard obj

        $this->strvideoboard = get_string('modulename', 'videoboard');
        $this->strvideoboards = get_string('modulenameplural', 'videoboard');
        $this->strsubmissions = get_string('submissions', 'videoboard');
        $this->strlastmodified = get_string('lastmodified');
        $this->pagetitle = strip_tags($courseshortname.': '.$this->strvideoboard.': '.format_string($this->videoboard->name, true, array('context' => $this->context)));

        // visibility handled by require_login() with $cm parameter
        // get current group only when really needed

    /// Set up things for a HTML editor if it's needed
        $this->defaultformat = editors_get_preferred_format();
    }

    /**
     * Display the videoboard, used by view.php
     *
     * This in turn calls the methods producing individual parts of the page
     */
    function view() {

        $context = get_context_instance(CONTEXT_MODULE,$this->cm->id);
        require_capability('mod/videoboard:view', $context);

        add_to_log($this->course->id, "videoboard", "view", "view.php?id={$this->cm->id}",
                   $this->videoboard->id, $this->cm->id);

        $this->view_header();

        $this->view_intro();

        $this->view_dates();

        $this->view_feedback();

        $this->view_footer();
    }

    /**
     * Display the header and top of a page
     *
     * (this doesn't change much for videoboard types)
     * This is used by the view() method to print the header of view.php but
     * it can be used on other pages in which case the string to denote the
     * page in the navigation trail should be passed as an argument
     *
     * @global object
     * @param string $subpage Description of subpage to be used in navigation trail
     */
    function view_header($subpage='') {
        global $CFG, $PAGE, $OUTPUT;

        if ($subpage) {
            $PAGE->navbar->add($subpage);
        }

        $PAGE->set_title($this->pagetitle);
        $PAGE->set_heading($this->course->fullname);

        echo $OUTPUT->header();

        groups_print_activity_menu($this->cm, $CFG->wwwroot . '/mod/videoboard/view.php?id=' . $this->cm->id);

        echo '<div class="reportlink">'.$this->submittedlink().'</div>';
        echo '<div class="clearer"></div>';
    }


    /**
     * Display the videoboard intro
     *
     * This will most likely be extended by videoboard type plug-ins
     * The default implementation prints the videoboard description in a box
     */
    function view_intro() {
        global $OUTPUT;
        echo $OUTPUT->box_start('generalbox boxaligncenter', 'intro');
        echo format_module_intro('videoboard', $this->videoboard, $this->cm->id);
        echo $OUTPUT->box_end();
        echo plagiarism_print_disclosure($this->cm->id);
    }

    /**
     * Display the videoboard dates
     *
     * Prints the videoboard start and end dates in a box.
     * This will be suitable for most videoboard types
     */
    function view_dates() {
        global $OUTPUT;
        if (!$this->videoboard->timeavailable && !$this->videoboard->timedue) {
            return;
        }

        echo $OUTPUT->box_start('generalbox boxaligncenter', 'dates');
        echo '<table>';
        if ($this->videoboard->timeavailable) {
            echo '<tr><td class="c0">'.get_string('availabledate','videoboard').':</td>';
            echo '    <td class="c1">'.userdate($this->videoboard->timeavailable).'</td></tr>';
        }
        if ($this->videoboard->timedue) {
            echo '<tr><td class="c0">'.get_string('duedate','videoboard').':</td>';
            echo '    <td class="c1">'.userdate($this->videoboard->timedue).'</td></tr>';
        }
        echo '</table>';
        echo $OUTPUT->box_end();
    }


    /**
     * Display the bottom and footer of a page
     *
     * This default method just prints the footer.
     * This will be suitable for most videoboard types
     */
    function view_footer() {
        global $OUTPUT;
        echo $OUTPUT->footer();
    }

    /**
     * Display the feedback to the student
     *
     * This default method prints the teacher picture and name, date when marked,
     * grade and teacher submissioncomment.
     * If advanced grading is used the method render_grade from the
     * advanced grading controller is called to display the grade.
     *
     * @global object
     * @global object
     * @global object
     * @param object $submission The submission object or NULL in which case it will be loaded
     */
    function view_feedback($submission=NULL) {
        global $USER, $CFG, $DB, $OUTPUT, $PAGE;
        require_once($CFG->libdir.'/gradelib.php');
        require_once("$CFG->dirroot/grade/grading/lib.php");

        if (!$submission) { /// Get submission for this videoboard
            $userid = $USER->id;
            $submission = $this->get_submission($userid);
        } else {
            $userid = $submission->userid;
        }
        // Check the user can submit
        $canviewfeedback = ($userid == $USER->id && has_capability('mod/videoboard:submit', $this->context, $USER->id, false));
        // If not then check if the user still has the view cap and has a previous submission
        $canviewfeedback = $canviewfeedback || (!empty($submission) && $submission->userid == $USER->id && has_capability('mod/videoboard:view', $this->context));
        // Or if user can grade (is a teacher or admin)
        $canviewfeedback = $canviewfeedback || has_capability('mod/videoboard:grade', $this->context);

        if (!$canviewfeedback) {
            // can not view or submit videoboards -> no feedback
            return;
        }

        $grading_info = grade_get_grades($this->course->id, 'mod', 'videoboard', $this->videoboard->id, $userid);
        $item = $grading_info->items[0];
        $grade = $item->grades[$userid];

        if ($grade->hidden or $grade->grade === false) { // hidden or error
            return;
        }

        if ($grade->grade === null and empty($grade->str_feedback)) {   /// Nothing to show yet
            return;
        }

        $graded_date = $grade->dategraded;
        $graded_by   = $grade->usermodified;

    /// We need the teacher info
        if (!$teacher = $DB->get_record('user', array('id'=>$graded_by))) {
            print_error('cannotfindteacher');
        }

    /// Print the feedback
        echo $OUTPUT->heading(get_string('feedbackfromteacher', 'videoboard', fullname($teacher)));

        echo '<table cellspacing="0" class="feedback">';

        echo '<tr>';
        echo '<td class="left picture">';
        if ($teacher) {
            echo $OUTPUT->user_picture($teacher);
        }
        echo '</td>';
        echo '<td class="topic">';
        echo '<div class="from">';
        if ($teacher) {
            echo '<div class="fullname">'.fullname($teacher).'</div>';
        }
        echo '<div class="time">'.userdate($graded_date).'</div>';
        echo '</div>';
        echo '</td>';
        echo '</tr>';

        echo '<tr>';
        echo '<td class="left side">&nbsp;</td>';
        echo '<td class="content">';
        $gradestr = '<div class="grade">'. get_string("grade").': '.$grade->str_long_grade. '</div>';
        if (!empty($submission) && $controller = get_grading_manager($this->context, 'mod_videoboard', 'submission')->get_active_controller()) {
            $controller->set_grade_range(make_grades_menu($this->videoboard->grade));
            echo $controller->render_grade($PAGE, $submission->id, $item, $gradestr, has_capability('mod/videoboard:grade', $this->context));
        } else {
            echo $gradestr;
        }
        echo '<div class="clearer"></div>';

        echo '<div class="comment">';
        echo $grade->str_feedback;
        echo '</div>';
        echo '</tr>';

         if ($this->type == 'uploadsingle') { //@TODO: move to overload view_feedback method in the class or is uploadsingle merging into upload?
            $responsefiles = $this->print_responsefiles($submission->userid, true);
            if (!empty($responsefiles)) {
                echo '<tr>';
                echo '<td class="left side">&nbsp;</td>';
                echo '<td class="content">';
                echo $responsefiles;
                echo '</tr>';
            }
         }

        echo '</table>';
    }

    /**
     * Returns a link with info about the state of the videoboard submissions
     *
     * This is used by view_header to put this link at the top right of the page.
     * For teachers it gives the number of submitted videoboards with a link
     * For students it gives the time of their submission.
     * This will be suitable for most videoboard types.
     *
     * @global object
     * @global object
     * @param bool $allgroup print all groups info if user can access all groups, suitable for index.php
     * @return string
     */
    function submittedlink($allgroups=false) {
        global $USER;
        global $CFG;

        $submitted = '';
        $urlbase = "{$CFG->wwwroot}/mod/videoboard/";

        $context = get_context_instance(CONTEXT_MODULE,$this->cm->id);
        if (has_capability('mod/videoboard:grade', $context)) {
            if ($allgroups and has_capability('moodle/site:accessallgroups', $context)) {
                $group = 0;
            } else {
                $group = groups_get_activity_group($this->cm);
            }
            if ($this->type == 'offline') {
                $submitted = '<a href="'.$urlbase.'submissions.php?id='.$this->cm->id.'">'.
                             get_string('viewfeedback', 'videoboard').'</a>';
            } else if ($count = $this->count_real_submissions($group)) {
                $submitted = '<a href="'.$urlbase.'submissions.php?id='.$this->cm->id.'">'.
                             get_string('viewsubmissions', 'videoboard', $count).'</a>';
            } else {
                $submitted = '<a href="'.$urlbase.'submissions.php?id='.$this->cm->id.'">'.
                             get_string('noattempts', 'videoboard').'</a>';
            }
        } else {
            if (isloggedin()) {
                if ($submission = $this->get_submission($USER->id)) {
                    // If the submission has been completed
                    if ($this->is_submitted_with_required_data($submission)) {
                        if ($submission->timemodified <= $this->videoboard->timedue || empty($this->videoboard->timedue)) {
                            $submitted = '<span class="early">'.userdate($submission->timemodified).'</span>';
                        } else {
                            $submitted = '<span class="late">'.userdate($submission->timemodified).'</span>';
                        }
                    }
                }
            }
        }

        return $submitted;
    }


    /**
     * @todo Document this function
     */
    function setup_elements(&$mform) {

    }

    /**
     * Any preprocessing needed for the settings form for
     * this videoboard type
     *
     * @param array $default_values - array to fill in with the default values
     *      in the form 'formelement' => 'value'
     * @param object $form - the form that is to be displayed
     * @return none
     */
    function form_data_preprocessing(&$default_values, $form) {
    }

    /**
     * Any extra validation checks needed for the settings
     * form for this videoboard type
     *
     * See lib/formslib.php, 'validation' function for details
     */
    function form_validation($data, $files) {
        return array();
    }

    /**
     * Create a new videoboard activity
     *
     * Given an object containing all the necessary data,
     * (defined by the form in mod_form.php) this function
     * will create a new instance and return the id number
     * of the new instance.
     * The due data is added to the calendar
     * This is common to all videoboard types.
     *
     * @global object
     * @global object
     * @param object $videoboard The data from the form on mod_form.php
     * @return int The id of the videoboard
     */
    function add_instance($videoboard) {
        global $COURSE, $DB;

        $videoboard->timemodified = time();
        $videoboard->courseid = $videoboard->course;

        $returnid = $DB->insert_record("videoboard", $videoboard);
        $videoboard->id = $returnid;

        if ($videoboard->timedue) {
            $event = new stdClass();
            $event->name        = $videoboard->name;
            $event->description = format_module_intro('videoboard', $videoboard, $videoboard->coursemodule);
            $event->courseid    = $videoboard->course;
            $event->groupid     = 0;
            $event->userid      = 0;
            $event->modulename  = 'videoboard';
            $event->instance    = $returnid;
            $event->eventtype   = 'due';
            $event->timestart   = $videoboard->timedue;
            $event->timeduration = 0;

            calendar_event::create($event);
        }

        videoboard_grade_item_update($videoboard);

        return $returnid;
    }

    /**
     * Deletes an videoboard activity
     *
     * Deletes all database records, files and calendar events for this videoboard.
     *
     * @global object
     * @global object
     * @param object $videoboard The videoboard to be deleted
     * @return boolean False indicates error
     */
    function delete_instance($videoboard) {
        global $CFG, $DB;

        $videoboard->courseid = $videoboard->course;

        $result = true;

        // now get rid of all files
        $fs = get_file_storage();
        if ($cm = get_coursemodule_from_instance('videoboard', $videoboard->id)) {
            $context = get_context_instance(CONTEXT_MODULE, $cm->id);
            $fs->delete_area_files($context->id);
        }

        if (! $DB->delete_records('videoboard_submissions', array('videoboard'=>$videoboard->id))) {
            $result = false;
        }

        if (! $DB->delete_records('event', array('modulename'=>'videoboard', 'instance'=>$videoboard->id))) {
            $result = false;
        }

        if (! $DB->delete_records('videoboard', array('id'=>$videoboard->id))) {
            $result = false;
        }
        $mod = $DB->get_field('modules','id',array('name'=>'videoboard'));

        videoboard_grade_item_delete($videoboard);

        return $result;
    }

    /**
     * Updates a new videoboard activity
     *
     * Given an object containing all the necessary data,
     * (defined by the form in mod_form.php) this function
     * will update the videoboard instance and return the id number
     * The due date is updated in the calendar
     * This is common to all videoboard types.
     *
     * @global object
     * @global object
     * @param object $videoboard The data from the form on mod_form.php
     * @return bool success
     */
    function update_instance($videoboard) {
        global $COURSE, $DB;

        $videoboard->timemodified = time();

        $videoboard->id = $videoboard->instance;
        $videoboard->courseid = $videoboard->course;

        $DB->update_record('videoboard', $videoboard);

        if ($videoboard->timedue) {
            $event = new stdClass();

            if ($event->id = $DB->get_field('event', 'id', array('modulename'=>'videoboard', 'instance'=>$videoboard->id))) {

                $event->name        = $videoboard->name;
                $event->description = format_module_intro('videoboard', $videoboard, $videoboard->coursemodule);
                $event->timestart   = $videoboard->timedue;

                $calendarevent = calendar_event::load($event->id);
                $calendarevent->update($event);
            } else {
                $event = new stdClass();
                $event->name        = $videoboard->name;
                $event->description = format_module_intro('videoboard', $videoboard, $videoboard->coursemodule);
                $event->courseid    = $videoboard->course;
                $event->groupid     = 0;
                $event->userid      = 0;
                $event->modulename  = 'videoboard';
                $event->instance    = $videoboard->id;
                $event->eventtype   = 'due';
                $event->timestart   = $videoboard->timedue;
                $event->timeduration = 0;

                calendar_event::create($event);
            }
        } else {
            $DB->delete_records('event', array('modulename'=>'videoboard', 'instance'=>$videoboard->id));
        }

        // get existing grade item
        videoboard_grade_item_update($videoboard);

        return true;
    }

    /**
     * Update grade item for this submission.
     */
    function update_grade($submission) {
        videoboard_update_grades($this->videoboard, $submission->userid);
    }

    /**
     * Top-level function for handling of submissions called by submissions.php
     *
     * This is for handling the teacher interaction with the grading interface
     * This should be suitable for most videoboard types.
     *
     * @global object
     * @param string $mode Specifies the kind of teacher interaction taking place
     */
    function submissions($mode) {
        ///The main switch is changed to facilitate
        ///1) Batch fast grading
        ///2) Skip to the next one on the popup
        ///3) Save and Skip to the next one on the popup

        //make user global so we can use the id
        global $USER, $OUTPUT, $DB, $PAGE;

        $mailinfo = optional_param('mailinfo', null, PARAM_BOOL);

        if (optional_param('next', null, PARAM_BOOL)) {
            $mode='next';
        }
        if (optional_param('saveandnext', null, PARAM_BOOL)) {
            $mode='saveandnext';
        }

        if (is_null($mailinfo)) {
            if (optional_param('sesskey', null, PARAM_BOOL)) {
                set_user_preference('videoboard_mailinfo', 0);
            } else {
                $mailinfo = get_user_preferences('videoboard_mailinfo', 0);
            }
        } else {
            set_user_preference('videoboard_mailinfo', $mailinfo);
        }

        if (!($this->validate_and_preprocess_feedback())) {
            // form was submitted ('Save' or 'Save and next' was pressed, but validation failed)
            $this->display_submission();
            return;
        }

        switch ($mode) {
            case 'grade':                         // We are in a main window grading
                if ($submission = $this->process_feedback()) {
                    $this->display_submissions(get_string('changessaved'));
                } else {
                    $this->display_submissions();
                }
                break;

            case 'single':                        // We are in a main window displaying one submission
                if ($submission = $this->process_feedback()) {
                    $this->display_submissions(get_string('changessaved'));
                } else {
                    $this->display_submission();
                }
                break;

            case 'all':                          // Main window, display everything
                $this->display_submissions();
                break;

            case 'fastgrade':
                ///do the fast grading stuff  - this process should work for all 3 subclasses
                $grading    = false;
                $commenting = false;
                $col        = false;
                if (isset($_POST['submissioncomment'])) {
                    $col = 'submissioncomment';
                    $commenting = true;
                }
                if (isset($_POST['menu'])) {
                    $col = 'menu';
                    $grading = true;
                }
                if (!$col) {
                    //both submissioncomment and grade columns collapsed..
                    $this->display_submissions();
                    break;
                }

                foreach ($_POST[$col] as $id => $unusedvalue){

                    $id = (int)$id; //clean parameter name

                    $this->process_outcomes($id);

                    if (!$submission = $this->get_submission($id)) {
                        $submission = $this->prepare_new_submission($id);
                        $newsubmission = true;
                    } else {
                        $newsubmission = false;
                    }
                    unset($submission->data1);  // Don't need to update this.
                    unset($submission->data2);  // Don't need to update this.

                    //for fast grade, we need to check if any changes take place
                    $updatedb = false;

                    if ($grading) {
                        $grade = $_POST['menu'][$id];
                        $updatedb = $updatedb || ($submission->grade != $grade);
                        $submission->grade = $grade;
                    } else {
                        if (!$newsubmission) {
                            unset($submission->grade);  // Don't need to update this.
                        }
                    }
                    if ($commenting) {
                        $commentvalue = trim($_POST['submissioncomment'][$id]);
                        $updatedb = $updatedb || ($submission->submissioncomment != $commentvalue);
                        $submission->submissioncomment = $commentvalue;
                    } else {
                        unset($submission->submissioncomment);  // Don't need to update this.
                    }

                    $submission->teacher    = $USER->id;
                    if ($updatedb) {
                        $submission->mailed = (int)(!$mailinfo);
                    }

                    $submission->timemarked = time();

                    //if it is not an update, we don't change the last modified time etc.
                    //this will also not write into database if no submissioncomment and grade is entered.

                    if ($updatedb){
                        if ($newsubmission) {
                            if (!isset($submission->submissioncomment)) {
                                $submission->submissioncomment = '';
                            }
                            $sid = $DB->insert_record('videoboard_submissions', $submission);
                            $submission->id = $sid;
                        } else {
                            $DB->update_record('videoboard_submissions', $submission);
                        }

                        // trigger grade event
                        $this->update_grade($submission);

                        //add to log only if updating
                        add_to_log($this->course->id, 'videoboard', 'update grades',
                                   'submissions.php?id='.$this->cm->id.'&user='.$submission->userid,
                                   $submission->userid, $this->cm->id);
                    }

                }

                $message = $OUTPUT->notification(get_string('changessaved'), 'notifysuccess');

                $this->display_submissions($message);
                break;


            case 'saveandnext':
                ///We are in pop up. save the current one and go to the next one.
                //first we save the current changes
                if ($submission = $this->process_feedback()) {
                    //print_heading(get_string('changessaved'));
                    //$extra_javascript = $this->update_main_listing($submission);
                }

            case 'next':
                /// We are currently in pop up, but we want to skip to next one without saving.
                ///    This turns out to be similar to a single case
                /// The URL used is for the next submission.
                $offset = required_param('offset', PARAM_INT);
                $nextid = required_param('nextid', PARAM_INT);
                $id = required_param('id', PARAM_INT);
                $filter = optional_param('filter', self::FILTER_ALL, PARAM_INT);

                if ($mode == 'next' || $filter !== self::FILTER_REQUIRE_GRADING) {
                    $offset = (int)$offset+1;
                }
                $redirect = new moodle_url('submissions.php',
                        array('id' => $id, 'offset' => $offset, 'userid' => $nextid,
                        'mode' => 'single', 'filter' => $filter));

                redirect($redirect);
                break;

            case 'singlenosave':
                $this->display_submission();
                break;

            default:
                echo "something seriously is wrong!!";
                break;
        }
    }

    /**
     * Checks if grading method allows quickgrade mode. At the moment it is hardcoded
     * that advanced grading methods do not allow quickgrade.
     *
     * videoboard type plugins are not allowed to override this method
     *
     * @return boolean
     */
    public final function quickgrade_mode_allowed() {
        global $CFG;
        require_once("$CFG->dirroot/grade/grading/lib.php");
        if ($controller = get_grading_manager($this->context, 'mod_videoboard', 'submission')->get_active_controller()) {
            return false;
        }
        return true;
    }

    /**
     * Helper method updating the listing on the main script from popup using javascript
     *
     * @global object
     * @global object
     * @param $submission object The submission whose data is to be updated on the main page
     */
    function update_main_listing($submission) {
        global $SESSION, $CFG, $OUTPUT;

        $output = '';

        $perpage = get_user_preferences('videoboard_perpage', 10);

        $quickgrade = get_user_preferences('videoboard_quickgrade', 0) && $this->quickgrade_mode_allowed();

        /// Run some Javascript to try and update the parent page
        $output .= '<script type="text/javascript">'."\n<!--\n";
        if (empty($SESSION->flextable['mod-videoboard-submissions']->collapse['submissioncomment'])) {
            if ($quickgrade){
                $output.= 'opener.document.getElementById("submissioncomment'.$submission->userid.'").value="'
                .trim($submission->submissioncomment).'";'."\n";
             } else {
                $output.= 'opener.document.getElementById("com'.$submission->userid.
                '").innerHTML="'.shorten_text(trim(strip_tags($submission->submissioncomment)), 15)."\";\n";
            }
        }

        if (empty($SESSION->flextable['mod-videoboard-submissions']->collapse['grade'])) {
            //echo optional_param('menuindex');
            if ($quickgrade){
                $output.= 'opener.document.getElementById("menumenu'.$submission->userid.
                '").selectedIndex="'.optional_param('menuindex', 0, PARAM_INT).'";'."\n";
            } else {
                $output.= 'opener.document.getElementById("g'.$submission->userid.'").innerHTML="'.
                $this->display_grade($submission->grade)."\";\n";
            }
        }
        //need to add student's videoboards in there too.
        if (empty($SESSION->flextable['mod-videoboard-submissions']->collapse['timemodified']) &&
            $submission->timemodified) {
            $output.= 'opener.document.getElementById("ts'.$submission->userid.
                 '").innerHTML="'.addslashes_js($this->print_student_answer($submission->userid)).userdate($submission->timemodified)."\";\n";
        }

        if (empty($SESSION->flextable['mod-videoboard-submissions']->collapse['timemarked']) &&
            $submission->timemarked) {
            $output.= 'opener.document.getElementById("tt'.$submission->userid.
                 '").innerHTML="'.userdate($submission->timemarked)."\";\n";
        }

        if (empty($SESSION->flextable['mod-videoboard-submissions']->collapse['status'])) {
            $output.= 'opener.document.getElementById("up'.$submission->userid.'").className="s1";';
            $buttontext = get_string('update');
            $url = new moodle_url('/mod/videoboard/submissions.php', array(
                    'id' => $this->cm->id,
                    'userid' => $submission->userid,
                    'mode' => 'single',
                    'offset' => (optional_param('offset', '', PARAM_INT)-1)));
            $button = $OUTPUT->action_link($url, $buttontext, new popup_action('click', $url, 'grade'.$submission->userid, array('height' => 450, 'width' => 700)), array('ttile'=>$buttontext));

            $output .= 'opener.document.getElementById("up'.$submission->userid.'").innerHTML="'.addslashes_js($button).'";';
        }

        $grading_info = grade_get_grades($this->course->id, 'mod', 'videoboard', $this->videoboard->id, $submission->userid);

        if (empty($SESSION->flextable['mod-videoboard-submissions']->collapse['finalgrade'])) {
            $output.= 'opener.document.getElementById("finalgrade_'.$submission->userid.
            '").innerHTML="'.$grading_info->items[0]->grades[$submission->userid]->str_grade.'";'."\n";
        }

        if (!empty($CFG->enableoutcomes) and empty($SESSION->flextable['mod-videoboard-submissions']->collapse['outcome'])) {

            if (!empty($grading_info->outcomes)) {
                foreach($grading_info->outcomes as $n=>$outcome) {
                    if ($outcome->grades[$submission->userid]->locked) {
                        continue;
                    }

                    if ($quickgrade){
                        $output.= 'opener.document.getElementById("outcome_'.$n.'_'.$submission->userid.
                        '").selectedIndex="'.$outcome->grades[$submission->userid]->grade.'";'."\n";

                    } else {
                        $options = make_grades_menu(-$outcome->scaleid);
                        $options[0] = get_string('nooutcome', 'grades');
                        $output.= 'opener.document.getElementById("outcome_'.$n.'_'.$submission->userid.'").innerHTML="'.$options[$outcome->grades[$submission->userid]->grade]."\";\n";
                    }

                }
            }
        }

        $output .= "\n-->\n</script>";
        return $output;
    }

    /**
     *  Return a grade in user-friendly form, whether it's a scale or not
     *
     * @global object
     * @param mixed $grade
     * @return string User-friendly representation of grade
     */
    function display_grade($grade) {
        global $DB;

        static $scalegrades = array();   // Cache scales for each videoboard - they might have different scales!!

        if ($this->videoboard->grade >= 0) {    // Normal number
            if ($grade == -1) {
                return '-';
            } else {
                return $grade.' / '.$this->videoboard->grade;
            }

        } else {                                // Scale
            if (empty($scalegrades[$this->videoboard->id])) {
                if ($scale = $DB->get_record('scale', array('id'=>-($this->videoboard->grade)))) {
                    $scalegrades[$this->videoboard->id] = make_menu_from_list($scale->scale);
                } else {
                    return '-';
                }
            }
            if (isset($scalegrades[$this->videoboard->id][$grade])) {
                return $scalegrades[$this->videoboard->id][$grade];
            }
            return '-';
        }
    }

    /**
     *  Display a single submission, ready for grading on a popup window
     *
     * This default method prints the teacher info and submissioncomment box at the top and
     * the student info and submission at the bottom.
     * This method also fetches the necessary data in order to be able to
     * provide a "Next submission" button.
     * Calls preprocess_submission() to give videoboard type plug-ins a chance
     * to process submissions before they are graded
     * This method gets its arguments from the page parameters userid and offset
     *
     * @global object
     * @global object
     * @param string $extra_javascript
     */
    function display_submission($offset=-1,$userid =-1, $display=true) {
        global $CFG, $DB, $PAGE, $OUTPUT, $USER;
        require_once($CFG->libdir.'/gradelib.php');
        require_once($CFG->libdir.'/tablelib.php');
        require_once("$CFG->dirroot/repository/lib.php");
        require_once("$CFG->dirroot/grade/grading/lib.php");
        if ($userid==-1) {
            $userid = required_param('userid', PARAM_INT);
        }
        if ($offset==-1) {
            $offset = required_param('offset', PARAM_INT);//offset for where to start looking for student.
        }
        $filter = optional_param('filter', 0, PARAM_INT);

        if (!$user = $DB->get_record('user', array('id'=>$userid))) {
            print_error('nousers');
        }

        if (!$submission = $this->get_submission($user->id)) {
            $submission = $this->prepare_new_submission($userid);
        }
        if ($submission->timemodified > $submission->timemarked) {
            $subtype = 'videoboardnew';
        } else {
            $subtype = 'videoboardold';
        }

        $grading_info = grade_get_grades($this->course->id, 'mod', 'videoboard', $this->videoboard->id, array($user->id));
        $gradingdisabled = $grading_info->items[0]->grades[$userid]->locked || $grading_info->items[0]->grades[$userid]->overridden;

    /// construct SQL, using current offset to find the data of the next student
        $course     = $this->course;
        $videoboard = $this->videoboard;
        $cm         = $this->cm;
        $context    = get_context_instance(CONTEXT_MODULE, $cm->id);

        //reset filter to all for offline videoboard
        if ($videoboard->videoboardtype == 'offline' && $filter == self::FILTER_SUBMITTED) {
            $filter = self::FILTER_ALL;
        }
        /// Get all ppl that can submit videoboards

        $currentgroup = groups_get_activity_group($cm);
        $users = get_enrolled_users($context, 'mod/videoboard:submit', $currentgroup, 'u.id');
        if ($users) {
            $users = array_keys($users);
            // if groupmembersonly used, remove users who are not in any group
            if (!empty($CFG->enablegroupmembersonly) and $cm->groupmembersonly) {
                if ($groupingusers = groups_get_grouping_members($cm->groupingid, 'u.id', 'u.id')) {
                    $users = array_intersect($users, array_keys($groupingusers));
                }
            }
        }

        $nextid = 0;
        $where = '';
        if($filter == self::FILTER_SUBMITTED) {
            $where .= 's.timemodified > 0 AND ';
        } else if($filter == self::FILTER_REQUIRE_GRADING) {
            $where .= 's.timemarked < s.timemodified AND ';
        }

        if ($users) {
            $userfields = user_picture::fields('u', array('lastaccess'));
            $select = "SELECT $userfields,
                              s.id AS submissionid, s.grade, s.submissioncomment,
                              s.timemodified, s.timemarked,
                              CASE WHEN s.timemarked > 0 AND s.timemarked >= s.timemodified THEN 1
                                   ELSE 0 END AS status ";

            $sql = 'FROM {user} u '.
                   'LEFT JOIN {videoboard_submissions} s ON u.id = s.userid
                   AND s.videoboard = '.$this->videoboard->id.' '.
                   'WHERE '.$where.'u.id IN ('.implode(',', $users).') ';

            if ($sort = flexible_table::get_sort_for_table('mod-videoboard-submissions')) {
                $sort = 'ORDER BY '.$sort.' ';
            }
            $auser = $DB->get_records_sql($select.$sql.$sort, null, $offset, 2);

            if (is_array($auser) && count($auser)>1) {
                $nextuser = next($auser);
                $nextid = $nextuser->id;
            }
        }

        if ($submission->teacher) {
            $teacher = $DB->get_record('user', array('id'=>$submission->teacher));
        } else {
            global $USER;
            $teacher = $USER;
        }

        $this->preprocess_submission($submission);

        $mformdata = new stdClass();
        $mformdata->context = $this->context;
        $mformdata->maxbytes = $this->course->maxbytes;
        $mformdata->courseid = $this->course->id;
        $mformdata->teacher = $teacher;
        $mformdata->videoboard = $videoboard;
        $mformdata->submission = $submission;
        $mformdata->lateness = $this->display_lateness($submission->timemodified);
        $mformdata->auser = $auser;
        $mformdata->user = $user;
        $mformdata->offset = $offset;
        $mformdata->userid = $userid;
        $mformdata->cm = $this->cm;
        $mformdata->grading_info = $grading_info;
        $mformdata->enableoutcomes = $CFG->enableoutcomes;
        $mformdata->grade = $this->videoboard->grade;
        $mformdata->gradingdisabled = $gradingdisabled;
        $mformdata->nextid = $nextid;
        $mformdata->submissioncomment= $submission->submissioncomment;
        $mformdata->submissioncommentformat= FORMAT_HTML;
        $mformdata->submission_content= $this->print_user_files($user->id,true);
        $mformdata->filter = $filter;
        $mformdata->mailinfo = get_user_preferences('videoboard_mailinfo', 0);
         if ($videoboard->videoboardtype == 'upload') {
            $mformdata->fileui_options = array('subdirs'=>1, 'maxbytes'=>$videoboard->maxbytes, 'maxfiles'=>$videoboard->var1, 'accepted_types'=>'*', 'return_types'=>FILE_INTERNAL);
        } elseif ($videoboard->videoboardtype == 'uploadsingle') {
            $mformdata->fileui_options = array('subdirs'=>0, 'maxbytes'=>$CFG->userquota, 'maxfiles'=>1, 'accepted_types'=>'*', 'return_types'=>FILE_INTERNAL);
        }
        $advancedgradingwarning = false;
        $gradingmanager = get_grading_manager($this->context, 'mod_videoboard', 'submission');
        if ($gradingmethod = $gradingmanager->get_active_method()) {
            $controller = $gradingmanager->get_controller($gradingmethod);
            if ($controller->is_form_available()) {
                $itemid = null;
                if (!empty($submission->id)) {
                    $itemid = $submission->id;
                }
                if ($gradingdisabled && $itemid) {
                    $mformdata->advancedgradinginstance = $controller->get_current_instance($USER->id, $itemid);
                } else if (!$gradingdisabled) {
                    $instanceid = optional_param('advancedgradinginstanceid', 0, PARAM_INT);
                    $mformdata->advancedgradinginstance = $controller->get_or_create_instance($instanceid, $USER->id, $itemid);
                }
            } else {
                $advancedgradingwarning = $controller->form_unavailable_notification();
            }
        }

        $submitform = new mod_videoboard_grading_form( null, $mformdata );

         if (!$display) {
            $ret_data = new stdClass();
            $ret_data->mform = $submitform;
            if (isset($mformdata->fileui_options)) {
                $ret_data->fileui_options = $mformdata->fileui_options;
            }
            return $ret_data;
        }

        if ($submitform->is_cancelled()) {
            redirect('submissions.php?id='.$this->cm->id);
        }

        $submitform->set_data($mformdata);

        $PAGE->set_title($this->course->fullname . ': ' .get_string('feedback', 'videoboard').' - '.fullname($user, true));
        $PAGE->set_heading($this->course->fullname);
        $PAGE->navbar->add(get_string('submissions', 'videoboard'), new moodle_url('/mod/videoboard/submissions.php', array('id'=>$cm->id)));
        $PAGE->navbar->add(fullname($user, true));

        echo $OUTPUT->header();
        echo $OUTPUT->heading(get_string('feedback', 'videoboard').': '.fullname($user, true));

        // display mform here...
        if ($advancedgradingwarning) {
            echo $OUTPUT->notification($advancedgradingwarning, 'error');
        }
        $submitform->display();

        $customfeedback = $this->custom_feedbackform($submission, true);
        if (!empty($customfeedback)) {
            echo $customfeedback;
        }

        echo $OUTPUT->footer();
    }

    /**
     *  Preprocess submission before grading
     *
     * Called by display_submission()
     * The default type does nothing here.
     *
     * @param object $submission The submission object
     */
    function preprocess_submission(&$submission) {
    }

    /**
     *  Display all the submissions ready for grading
     *
     * @global object
     * @global object
     * @global object
     * @global object
     * @param string $message
     * @return bool|void
     */
    function display_submissions($message='') {
        global $CFG, $DB, $USER, $DB, $OUTPUT, $PAGE;
        require_once($CFG->libdir.'/gradelib.php');

        /* first we check to see if the form has just been submitted
         * to request user_preference updates
         */

       $filters = array(self::FILTER_ALL             => get_string('all'),
                        self::FILTER_REQUIRE_GRADING => get_string('requiregrading', 'videoboard'));

        $updatepref = optional_param('updatepref', 0, PARAM_BOOL);
        if ($updatepref) {
            $perpage = optional_param('perpage', 10, PARAM_INT);
            $perpage = ($perpage <= 0) ? 10 : $perpage ;
            $filter = optional_param('filter', 0, PARAM_INT);
            set_user_preference('videoboard_perpage', $perpage);
            set_user_preference('videoboard_quickgrade', optional_param('quickgrade', 0, PARAM_BOOL));
            set_user_preference('videoboard_filter', $filter);
        }

        /* next we get perpage and quickgrade (allow quick grade) params
         * from database
         */
        $perpage    = get_user_preferences('videoboard_perpage', 10);
        $quickgrade = get_user_preferences('videoboard_quickgrade', 0) && $this->quickgrade_mode_allowed();
        $filter = get_user_preferences('videoboard_filter', 0);
        $grading_info = grade_get_grades($this->course->id, 'mod', 'videoboard', $this->videoboard->id);

        if (!empty($CFG->enableoutcomes) and !empty($grading_info->outcomes)) {
            $uses_outcomes = true;
        } else {
            $uses_outcomes = false;
        }

        $page    = optional_param('page', 0, PARAM_INT);
        $strsaveallfeedback = get_string('saveallfeedback', 'videoboard');

    /// Some shortcuts to make the code read better

        $course     = $this->course;
        $videoboard = $this->videoboard;
        $cm         = $this->cm;
        $hassubmission = false;

        // reset filter to all for offline videoboard only.
        if ($videoboard->videoboardtype == 'offline') {
            if ($filter == self::FILTER_SUBMITTED) {
                $filter = self::FILTER_ALL;
            }
        } else {
            $filters[self::FILTER_SUBMITTED] = get_string('submitted', 'videoboard');
        }

        $tabindex = 1; //tabindex for quick grading tabbing; Not working for dropdowns yet
        add_to_log($course->id, 'videoboard', 'view submission', 'submissions.php?id='.$this->cm->id, $this->videoboard->id, $this->cm->id);

        $PAGE->set_title(format_string($this->videoboard->name,true));
        $PAGE->set_heading($this->course->fullname);
        echo $OUTPUT->header();

        echo '<div class="usersubmissions">';

        //hook to allow plagiarism plugins to update status/print links.
        echo plagiarism_update_status($this->course, $this->cm);

        $course_context = get_context_instance(CONTEXT_COURSE, $course->id);
        if (has_capability('gradereport/grader:view', $course_context) && has_capability('moodle/grade:viewall', $course_context)) {
            echo '<div class="allcoursegrades"><a href="' . $CFG->wwwroot . '/grade/report/grader/index.php?id=' . $course->id . '">'
                . get_string('seeallcoursegrades', 'grades') . '</a></div>';
        }

        if (!empty($message)) {
            echo $message;   // display messages here if any
        }

        $context = get_context_instance(CONTEXT_MODULE, $cm->id);

    /// Check to see if groups are being used in this videoboard

        /// find out current groups mode
        $groupmode = groups_get_activity_groupmode($cm);
        $currentgroup = groups_get_activity_group($cm, true);
        groups_print_activity_menu($cm, $CFG->wwwroot . '/mod/videoboard/submissions.php?id=' . $this->cm->id);

        /// Print quickgrade form around the table
        if ($quickgrade) {
            $formattrs = array();
            $formattrs['action'] = new moodle_url('/mod/videoboard/submissions.php');
            $formattrs['id'] = 'fastg';
            $formattrs['method'] = 'post';

            echo html_writer::start_tag('form', $formattrs);
            echo html_writer::empty_tag('input', array('type'=>'hidden', 'name'=>'id',      'value'=> $this->cm->id));
            echo html_writer::empty_tag('input', array('type'=>'hidden', 'name'=>'mode',    'value'=> 'fastgrade'));
            echo html_writer::empty_tag('input', array('type'=>'hidden', 'name'=>'page',    'value'=> $page));
            echo html_writer::empty_tag('input', array('type'=>'hidden', 'name'=>'sesskey', 'value'=> sesskey()));
        }

        /// Get all ppl that are allowed to submit videoboards
        list($esql, $params) = get_enrolled_sql($context, 'mod/videoboard:submit', $currentgroup);

        if ($filter == self::FILTER_ALL) {
            $sql = "SELECT u.id FROM {user} u ".
                   "LEFT JOIN ($esql) eu ON eu.id=u.id ".
                   "WHERE u.deleted = 0 AND eu.id=u.id ";
        } else {
            $wherefilter = ' AND s.videoboard = '. $this->videoboard->id;
            $videoboardsubmission = "LEFT JOIN {videoboard_submissions} s ON (u.id = s.userid) ";
            if($filter == self::FILTER_SUBMITTED) {
                $wherefilter .= ' AND s.timemodified > 0 ';
            } else if($filter == self::FILTER_REQUIRE_GRADING && $videoboard->videoboardtype != 'offline') {
                $wherefilter .= ' AND s.timemarked < s.timemodified ';
            } else { // require grading for offline videoboard
                $videoboardsubmission = "";
                $wherefilter = "";
            }

            $sql = "SELECT u.id FROM {user} u ".
                   "LEFT JOIN ($esql) eu ON eu.id=u.id ".
                   $videoboardsubmission.
                   "WHERE u.deleted = 0 AND eu.id=u.id ".
                   $wherefilter;
        }

        $users = $DB->get_records_sql($sql, $params);
        if (!empty($users)) {
            if($videoboard->videoboardtype == 'offline' && $filter == self::FILTER_REQUIRE_GRADING) {
                //remove users who has submitted their videoboard
                foreach ($this->get_submissions() as $submission) {
                    if (array_key_exists($submission->userid, $users)) {
                        unset($users[$submission->userid]);
                    }
                }
            }
            $users = array_keys($users);
        }

        // if groupmembersonly used, remove users who are not in any group
        if ($users and !empty($CFG->enablegroupmembersonly) and $cm->groupmembersonly) {
            if ($groupingusers = groups_get_grouping_members($cm->groupingid, 'u.id', 'u.id')) {
                $users = array_intersect($users, array_keys($groupingusers));
            }
        }

        $extrafields = get_extra_user_fields($context);
        $tablecolumns = array_merge(array('picture', 'fullname'), $extrafields,
                array('grade', 'submissioncomment', 'timemodified', 'timemarked', 'status', 'finalgrade'));
        if ($uses_outcomes) {
            $tablecolumns[] = 'outcome'; // no sorting based on outcomes column
        }

        $extrafieldnames = array();
        foreach ($extrafields as $field) {
            $extrafieldnames[] = get_user_field_name($field);
        }
        $tableheaders = array_merge(
                array('', get_string('fullnameuser')),
                $extrafieldnames,
                array(
                    get_string('grade'),
                    get_string('comment', 'videoboard'),
                    get_string('lastmodified').' ('.get_string('submission', 'videoboard').')',
                    get_string('lastmodified').' ('.get_string('grade').')',
                    get_string('status'),
                    get_string('finalgrade', 'grades'),
                ));
        if ($uses_outcomes) {
            $tableheaders[] = get_string('outcome', 'grades');
        }

        require_once($CFG->libdir.'/tablelib.php');
        $table = new flexible_table('mod-videoboard-submissions');

        $table->define_columns($tablecolumns);
        $table->define_headers($tableheaders);
        $table->define_baseurl($CFG->wwwroot.'/mod/videoboard/submissions.php?id='.$this->cm->id.'&amp;currentgroup='.$currentgroup);

        $table->sortable(true, 'lastname');//sorted by lastname by default
        $table->collapsible(true);
        $table->initialbars(true);

        $table->column_suppress('picture');
        $table->column_suppress('fullname');

        $table->column_class('picture', 'picture');
        $table->column_class('fullname', 'fullname');
        foreach ($extrafields as $field) {
            $table->column_class($field, $field);
        }
        $table->column_class('grade', 'grade');
        $table->column_class('submissioncomment', 'comment');
        $table->column_class('timemodified', 'timemodified');
        $table->column_class('timemarked', 'timemarked');
        $table->column_class('status', 'status');
        $table->column_class('finalgrade', 'finalgrade');
        if ($uses_outcomes) {
            $table->column_class('outcome', 'outcome');
        }

        $table->set_attribute('cellspacing', '0');
        $table->set_attribute('id', 'attempts');
        $table->set_attribute('class', 'submissions');
        $table->set_attribute('width', '100%');

        $table->no_sorting('finalgrade');
        $table->no_sorting('outcome');

        // Start working -- this is necessary as soon as the niceties are over
        $table->setup();

        /// Construct the SQL
        list($where, $params) = $table->get_sql_where();
        if ($where) {
            $where .= ' AND ';
        }

        if ($filter == self::FILTER_SUBMITTED) {
           $where .= 's.timemodified > 0 AND ';
        } else if($filter == self::FILTER_REQUIRE_GRADING) {
            $where = '';
            if ($videoboard->videoboardtype != 'offline') {
               $where .= 's.timemarked < s.timemodified AND ';
            }
        }

        if ($sort = $table->get_sql_sort()) {
            $sort = ' ORDER BY '.$sort;
        }

        $ufields = user_picture::fields('u', $extrafields);
        if (!empty($users)) {
            $select = "SELECT $ufields,
                              s.id AS submissionid, s.grade, s.submissioncomment,
                              s.timemodified, s.timemarked,
                              CASE WHEN s.timemarked > 0 AND s.timemarked >= s.timemodified THEN 1
                                   ELSE 0 END AS status ";

            $sql = 'FROM {user} u '.
                   'LEFT JOIN {videoboard_submissions} s ON u.id = s.userid
                    AND s.videoboard = '.$this->videoboard->id.' '.
                   'WHERE '.$where.'u.id IN ('.implode(',',$users).') ';

            $ausers = $DB->get_records_sql($select.$sql.$sort, $params, $table->get_page_start(), $table->get_page_size());

            $table->pagesize($perpage, count($users));

            ///offset used to calculate index of student in that particular query, needed for the pop up to know who's next
            $offset = $page * $perpage;
            $strupdate = get_string('update');
            $strgrade  = get_string('grade');
            $strview  = get_string('view');
            $grademenu = make_grades_menu($this->videoboard->grade);

            if ($ausers !== false) {
                $grading_info = grade_get_grades($this->course->id, 'mod', 'videoboard', $this->videoboard->id, array_keys($ausers));
                $endposition = $offset + $perpage;
                $currentposition = 0;
                foreach ($ausers as $auser) {
                    if ($currentposition == $offset && $offset < $endposition) {
                        $rowclass = null;
                        $final_grade = $grading_info->items[0]->grades[$auser->id];
                        $grademax = $grading_info->items[0]->grademax;
                        $final_grade->formatted_grade = round($final_grade->grade,2) .' / ' . round($grademax,2);
                        $locked_overridden = 'locked';
                        if ($final_grade->overridden) {
                            $locked_overridden = 'overridden';
                        }

                        // TODO add here code if advanced grading grade must be reviewed => $auser->status=0

                        $picture = $OUTPUT->user_picture($auser);

                        if (empty($auser->submissionid)) {
                            $auser->grade = -1; //no submission yet
                        }

                        if (!empty($auser->submissionid)) {
                            $hassubmission = true;
                        ///Prints student answer and student modified date
                        ///attach file or print link to student answer, depending on the type of the videoboard.
                        ///Refer to print_student_answer in inherited classes.
                            if ($auser->timemodified > 0) {
                                $studentmodifiedcontent = $this->print_student_answer($auser->id)
                                        . userdate($auser->timemodified);
                                if ($videoboard->timedue && $auser->timemodified > $videoboard->timedue) {
                                    $studentmodifiedcontent .= videoboard_display_lateness($auser->timemodified, $videoboard->timedue);
                                    $rowclass = 'late';
                                }
                            } else {
                                $studentmodifiedcontent = '&nbsp;';
                            }
                            $studentmodified = html_writer::tag('div', $studentmodifiedcontent, array('id' => 'ts' . $auser->id));
                        ///Print grade, dropdown or text
                            if ($auser->timemarked > 0) {
                                $teachermodified = '<div id="tt'.$auser->id.'">'.userdate($auser->timemarked).'</div>';

                                if ($final_grade->locked or $final_grade->overridden) {
                                    $grade = '<div id="g'.$auser->id.'" class="'. $locked_overridden .'">'.$final_grade->formatted_grade.'</div>';
                                } else if ($quickgrade) {
                                    $attributes = array();
                                    $attributes['tabindex'] = $tabindex++;
                                    $menu = html_writer::select(make_grades_menu($this->videoboard->grade), 'menu['.$auser->id.']', $auser->grade, array(-1=>get_string('nograde')), $attributes);
                                    $grade = '<div id="g'.$auser->id.'">'. $menu .'</div>';
                                } else {
                                    $grade = '<div id="g'.$auser->id.'">'.$this->display_grade($auser->grade).'</div>';
                                }

                            } else {
                                $teachermodified = '<div id="tt'.$auser->id.'">&nbsp;</div>';
                                if ($final_grade->locked or $final_grade->overridden) {
                                    $grade = '<div id="g'.$auser->id.'" class="'. $locked_overridden .'">'.$final_grade->formatted_grade.'</div>';
                                } else if ($quickgrade) {
                                    $attributes = array();
                                    $attributes['tabindex'] = $tabindex++;
                                    $menu = html_writer::select(make_grades_menu($this->videoboard->grade), 'menu['.$auser->id.']', $auser->grade, array(-1=>get_string('nograde')), $attributes);
                                    $grade = '<div id="g'.$auser->id.'">'.$menu.'</div>';
                                } else {
                                    $grade = '<div id="g'.$auser->id.'">'.$this->display_grade($auser->grade).'</div>';
                                }
                            }
                        ///Print Comment
                            if ($final_grade->locked or $final_grade->overridden) {
                                $comment = '<div id="com'.$auser->id.'">'.shorten_text(strip_tags($final_grade->str_feedback),15).'</div>';

                            } else if ($quickgrade) {
                                $comment = '<div id="com'.$auser->id.'">'
                                         . '<textarea tabindex="'.$tabindex++.'" name="submissioncomment['.$auser->id.']" id="submissioncomment'
                                         . $auser->id.'" rows="2" cols="20">'.($auser->submissioncomment).'</textarea></div>';
                            } else {
                                $comment = '<div id="com'.$auser->id.'">'.shorten_text(strip_tags($auser->submissioncomment),15).'</div>';
                            }
                        } else {
                            $studentmodified = '<div id="ts'.$auser->id.'">&nbsp;</div>';
                            $teachermodified = '<div id="tt'.$auser->id.'">&nbsp;</div>';
                            $status          = '<div id="st'.$auser->id.'">&nbsp;</div>';

                            if ($final_grade->locked or $final_grade->overridden) {
                                $grade = '<div id="g'.$auser->id.'">'.$final_grade->formatted_grade . '</div>';
                                $hassubmission = true;
                            } else if ($quickgrade) {   // allow editing
                                $attributes = array();
                                $attributes['tabindex'] = $tabindex++;
                                $menu = html_writer::select(make_grades_menu($this->videoboard->grade), 'menu['.$auser->id.']', $auser->grade, array(-1=>get_string('nograde')), $attributes);
                                $grade = '<div id="g'.$auser->id.'">'.$menu.'</div>';
                                $hassubmission = true;
                            } else {
                                $grade = '<div id="g'.$auser->id.'">-</div>';
                            }

                            if ($final_grade->locked or $final_grade->overridden) {
                                $comment = '<div id="com'.$auser->id.'">'.$final_grade->str_feedback.'</div>';
                            } else if ($quickgrade) {
                                $comment = '<div id="com'.$auser->id.'">'
                                         . '<textarea tabindex="'.$tabindex++.'" name="submissioncomment['.$auser->id.']" id="submissioncomment'
                                         . $auser->id.'" rows="2" cols="20">'.($auser->submissioncomment).'</textarea></div>';
                            } else {
                                $comment = '<div id="com'.$auser->id.'">&nbsp;</div>';
                            }
                        }

                        if (empty($auser->status)) { /// Confirm we have exclusively 0 or 1
                            $auser->status = 0;
                        } else {
                            $auser->status = 1;
                        }

                        $buttontext = ($auser->status == 1) ? $strupdate : $strgrade;
                        if ($final_grade->locked or $final_grade->overridden) {
                            $buttontext = $strview;
                        }

                        ///No more buttons, we use popups ;-).
                        $popup_url = '/mod/videoboard/submissions.php?id='.$this->cm->id
                                   . '&amp;userid='.$auser->id.'&amp;mode=single'.'&amp;filter='.$filter.'&amp;offset='.$offset++;

                        $button = $OUTPUT->action_link($popup_url, $buttontext);

                        $status  = '<div id="up'.$auser->id.'" class="s'.$auser->status.'">'.$button.'</div>';

                        $finalgrade = '<span id="finalgrade_'.$auser->id.'">'.$final_grade->str_grade.'</span>';

                        $outcomes = '';

                        if ($uses_outcomes) {

                            foreach($grading_info->outcomes as $n=>$outcome) {
                                $outcomes .= '<div class="outcome"><label>'.$outcome->name.'</label>';
                                $options = make_grades_menu(-$outcome->scaleid);

                                if ($outcome->grades[$auser->id]->locked or !$quickgrade) {
                                    $options[0] = get_string('nooutcome', 'grades');
                                    $outcomes .= ': <span id="outcome_'.$n.'_'.$auser->id.'">'.$options[$outcome->grades[$auser->id]->grade].'</span>';
                                } else {
                                    $attributes = array();
                                    $attributes['tabindex'] = $tabindex++;
                                    $attributes['id'] = 'outcome_'.$n.'_'.$auser->id;
                                    $outcomes .= ' '.html_writer::select($options, 'outcome_'.$n.'['.$auser->id.']', $outcome->grades[$auser->id]->grade, array(0=>get_string('nooutcome', 'grades')), $attributes);
                                }
                                $outcomes .= '</div>';
                            }
                        }

                        $userlink = '<a href="' . $CFG->wwwroot . '/user/view.php?id=' . $auser->id . '&amp;course=' . $course->id . '">' . fullname($auser, has_capability('moodle/site:viewfullnames', $this->context)) . '</a>';
                        $extradata = array();
                        foreach ($extrafields as $field) {
                            $extradata[] = $auser->{$field};
                        }
                        $row = array_merge(array($picture, $userlink), $extradata,
                                array($grade, $comment, $studentmodified, $teachermodified,
                                $status, $finalgrade));
                        if ($uses_outcomes) {
                            $row[] = $outcomes;
                        }
                        $table->add_data($row, $rowclass);
                    }
                    $currentposition++;
                }
                if ($hassubmission && ($this->videoboard->videoboardtype=='upload' || $this->videoboard->videoboardtype=='online' || $this->videoboard->videoboardtype=='uploadsingle')) { //TODO: this is an ugly hack, where is the plugin spirit? (skodak)
                    echo html_writer::start_tag('div', array('class' => 'mod-videoboard-download-link'));
                    echo html_writer::link(new moodle_url('/mod/videoboard/submissions.php', array('id' => $this->cm->id, 'download' => 'zip')), get_string('downloadall', 'videoboard'));
                    echo html_writer::end_tag('div');
                }
                $table->print_html();  /// Print the whole table
            } else {
                if ($filter == self::FILTER_SUBMITTED) {
                    echo html_writer::tag('div', get_string('nosubmisson', 'videoboard'), array('class'=>'nosubmisson'));
                } else if ($filter == self::FILTER_REQUIRE_GRADING) {
                    echo html_writer::tag('div', get_string('norequiregrading', 'videoboard'), array('class'=>'norequiregrading'));
                }
            }
        }

        /// Print quickgrade form around the table
        if ($quickgrade && $table->started_output && !empty($users)){
            $mailinfopref = false;
            if (get_user_preferences('videoboard_mailinfo', 1)) {
                $mailinfopref = true;
            }
            $emailnotification =  html_writer::checkbox('mailinfo', 1, $mailinfopref, get_string('enablenotification','videoboard'));

            $emailnotification .= $OUTPUT->help_icon('enablenotification', 'videoboard');
            echo html_writer::tag('div', $emailnotification, array('class'=>'emailnotification'));

            $savefeedback = html_writer::empty_tag('input', array('type'=>'submit', 'name'=>'fastg', 'value'=>get_string('saveallfeedback', 'videoboard')));
            echo html_writer::tag('div', $savefeedback, array('class'=>'fastgbutton'));

            echo html_writer::end_tag('form');
        } else if ($quickgrade) {
            echo html_writer::end_tag('form');
        }

        echo '</div>';
        /// End of fast grading form

        /// Mini form for setting user preference

        $formaction = new moodle_url('/mod/videoboard/submissions.php', array('id'=>$this->cm->id));
        $mform = new MoodleQuickForm('optionspref', 'post', $formaction, '', array('class'=>'optionspref'));

        $mform->addElement('hidden', 'updatepref');
        $mform->setDefault('updatepref', 1);
        $mform->addElement('header', 'qgprefs', get_string('optionalsettings', 'videoboard'));
        $mform->addElement('select', 'filter', get_string('show'),  $filters);

        $mform->setDefault('filter', $filter);

        $mform->addElement('text', 'perpage', get_string('pagesize', 'videoboard'), array('size'=>1));
        $mform->setDefault('perpage', $perpage);

        if ($this->quickgrade_mode_allowed()) {
            $mform->addElement('checkbox', 'quickgrade', get_string('quickgrade','videoboard'));
            $mform->setDefault('quickgrade', $quickgrade);
            $mform->addHelpButton('quickgrade', 'quickgrade', 'videoboard');
        }

        $mform->addElement('submit', 'savepreferences', get_string('savepreferences'));

        $mform->display();

        echo $OUTPUT->footer();
    }

    /**
     * If the form was cancelled ('Cancel' or 'Next' was pressed), call cancel method
     * from advanced grading (if applicable) and returns true
     * If the form was submitted, validates it and returns false if validation did not pass.
     * If validation passes, preprocess advanced grading (if applicable) and returns true.
     *
     * Note to the developers: This is NOT the correct way to implement advanced grading
     * in grading form. The videoboard grading was written long time ago and unfortunately
     * does not fully use the mforms. Usually function is_validated() is called to
     * validate the form and get_data() is called to get the data from the form.
     *
     * Here we have to push the calculated grade to $_POST['xgrade'] because further processing
     * of the form gets the data not from form->get_data(), but from $_POST (using statement
     * like  $feedback = data_submitted() )
     */
    protected function validate_and_preprocess_feedback() {
        global $USER, $CFG;
        require_once($CFG->libdir.'/gradelib.php');
        if (!($feedback = data_submitted()) || !isset($feedback->userid) || !isset($feedback->offset)) {
            return true;      // No incoming data, nothing to validate
        }
        $userid = required_param('userid', PARAM_INT);
        $offset = required_param('offset', PARAM_INT);
        $gradinginfo = grade_get_grades($this->course->id, 'mod', 'videoboard', $this->videoboard->id, array($userid));
        $gradingdisabled = $gradinginfo->items[0]->grades[$userid]->locked || $gradinginfo->items[0]->grades[$userid]->overridden;
        if ($gradingdisabled) {
            return true;
        }
        $submissiondata = $this->display_submission($offset, $userid, false);
        $mform = $submissiondata->mform;
        $gradinginstance = $mform->use_advanced_grading();
        if (optional_param('cancel', false, PARAM_BOOL) || optional_param('next', false, PARAM_BOOL)) {
            // form was cancelled
            if ($gradinginstance) {
                $gradinginstance->cancel();
            }
        } else if ($mform->is_submitted()) {
            // form was submitted (= a submit button other than 'cancel' or 'next' has been clicked)
            if (!$mform->is_validated()) {
                return false;
            }
            // preprocess advanced grading here
            if ($gradinginstance) {
                $data = $mform->get_data();
                // create submission if it did not exist yet because we need submission->id for storing the grading instance
                $submission = $this->get_submission($userid, true);
                $_POST['xgrade'] = $gradinginstance->submit_and_get_grade($data->advancedgrading, $submission->id);
            }
        }
        return true;
    }

    /**
     *  Process teacher feedback submission
     *
     * This is called by submissions() when a grading even has taken place.
     * It gets its data from the submitted form.
     *
     * @global object
     * @global object
     * @global object
     * @return object|bool The updated submission object or false
     */
    function process_feedback($formdata=null) {
        global $CFG, $USER, $DB;
        require_once($CFG->libdir.'/gradelib.php');

        if (!$feedback = data_submitted() or !confirm_sesskey()) {      // No incoming data?
            return false;
        }

        ///For save and next, we need to know the userid to save, and the userid to go
        ///We use a new hidden field in the form, and set it to -1. If it's set, we use this
        ///as the userid to store
        if ((int)$feedback->saveuserid !== -1){
            $feedback->userid = $feedback->saveuserid;
        }

        if (!empty($feedback->cancel)) {          // User hit cancel button
            return false;
        }

        $grading_info = grade_get_grades($this->course->id, 'mod', 'videoboard', $this->videoboard->id, $feedback->userid);

        // store outcomes if needed
        $this->process_outcomes($feedback->userid);

        $submission = $this->get_submission($feedback->userid, true);  // Get or make one

        if (!($grading_info->items[0]->grades[$feedback->userid]->locked ||
            $grading_info->items[0]->grades[$feedback->userid]->overridden) ) {

            $submission->grade      = $feedback->xgrade;
            $submission->submissioncomment    = $feedback->submissioncomment_editor['text'];
            $submission->teacher    = $USER->id;
            $mailinfo = get_user_preferences('videoboard_mailinfo', 0);
            if (!$mailinfo) {
                $submission->mailed = 1;       // treat as already mailed
            } else {
                $submission->mailed = 0;       // Make sure mail goes out (again, even)
            }
            $submission->timemarked = time();

            unset($submission->data1);  // Don't need to update this.
            unset($submission->data2);  // Don't need to update this.

            if (empty($submission->timemodified)) {   // eg for offline videoboards
                // $submission->timemodified = time();
            }

            $DB->update_record('videoboard_submissions', $submission);

            // triger grade event
            $this->update_grade($submission);

            add_to_log($this->course->id, 'videoboard', 'update grades',
                       'submissions.php?id='.$this->cm->id.'&user='.$feedback->userid, $feedback->userid, $this->cm->id);
             if (!is_null($formdata)) {
                    if ($this->type == 'upload' || $this->type == 'uploadsingle') {
                        $mformdata = $formdata->mform->get_data();
                        $mformdata = file_postupdate_standard_filemanager($mformdata, 'files', $formdata->fileui_options, $this->context, 'mod_videoboard', 'response', $submission->id);
                    }
             }
        }

        return $submission;

    }

    function process_outcomes($userid) {
        global $CFG, $USER;

        if (empty($CFG->enableoutcomes)) {
            return;
        }

        require_once($CFG->libdir.'/gradelib.php');

        if (!$formdata = data_submitted() or !confirm_sesskey()) {
            return;
        }

        $data = array();
        $grading_info = grade_get_grades($this->course->id, 'mod', 'videoboard', $this->videoboard->id, $userid);

        if (!empty($grading_info->outcomes)) {
            foreach($grading_info->outcomes as $n=>$old) {
                $name = 'outcome_'.$n;
                if (isset($formdata->{$name}[$userid]) and $old->grades[$userid]->grade != $formdata->{$name}[$userid]) {
                    $data[$n] = $formdata->{$name}[$userid];
                }
            }
        }
        if (count($data) > 0) {
            grade_update_outcomes('mod/videoboard', $this->course->id, 'mod', 'videoboard', $this->videoboard->id, $userid, $data);
        }

    }

    /**
     * Load the submission object for a particular user
     *
     * @global object
     * @global object
     * @param $userid int The id of the user whose submission we want or 0 in which case USER->id is used
     * @param $createnew boolean optional Defaults to false. If set to true a new submission object will be created in the database
     * @param bool $teachermodified student submission set if false
     * @return object The submission
     */
    function get_submission($userid=0, $createnew=false, $teachermodified=false) {
        global $USER, $DB;

        if (empty($userid)) {
            $userid = $USER->id;
        }

        $submission = $DB->get_record('videoboard_submissions', array('videoboard'=>$this->videoboard->id, 'userid'=>$userid));

        if ($submission || !$createnew) {
            return $submission;
        }
        $newsubmission = $this->prepare_new_submission($userid, $teachermodified);
        $DB->insert_record("videoboard_submissions", $newsubmission);

        return $DB->get_record('videoboard_submissions', array('videoboard'=>$this->videoboard->id, 'userid'=>$userid));
    }

    /**
     * Check the given submission is complete. Preliminary rows are often created in the videoboard_submissions
     * table before a submission actually takes place. This function checks to see if the given submission has actually
     * been submitted.
     *
     * @param  stdClass $submission The submission we want to check for completion
     * @return bool                 Indicates if the submission was found to be complete
     */
    public function is_submitted_with_required_data($submission) {
        return $submission->timemodified;
    }

    /**
     * Instantiates a new submission object for a given user
     *
     * Sets the videoboard, userid and times, everything else is set to default values.
     *
     * @param int $userid The userid for which we want a submission object
     * @param bool $teachermodified student submission set if false
     * @return object The submission
     */
    function prepare_new_submission($userid, $teachermodified=false) {
        $submission = new stdClass();
        $submission->videoboard   = $this->videoboard->id;
        $submission->userid       = $userid;
        $submission->timecreated = time();
        // teachers should not be modifying modified date, except offline videoboards
        if ($teachermodified) {
            $submission->timemodified = 0;
        } else {
            $submission->timemodified = $submission->timecreated;
        }
        $submission->numfiles     = 0;
        $submission->data1        = '';
        $submission->data2        = '';
        $submission->grade        = -1;
        $submission->submissioncomment      = '';
        $submission->format       = 0;
        $submission->teacher      = 0;
        $submission->timemarked   = 0;
        $submission->mailed       = 0;
        return $submission;
    }

    /**
     * Return all videoboard submissions by ENROLLED students (even empty)
     *
     * @param string $sort optional field names for the ORDER BY in the sql query
     * @param string $dir optional specifying the sort direction, defaults to DESC
     * @return array The submission objects indexed by id
     */
    function get_submissions($sort='', $dir='DESC') {
        return videoboard_get_all_submissions($this->videoboard, $sort, $dir);
    }

    /**
     * Counts all complete (real) videoboard submissions by enrolled students
     *
     * @param  int $groupid (optional) If nonzero then count is restricted to this group
     * @return int          The number of submissions
     */
    function count_real_submissions($groupid=0) {
        global $CFG;
        global $DB;

        // Grab the context assocated with our course module
        $context = get_context_instance(CONTEXT_MODULE, $this->cm->id);

        // Get ids of users enrolled in the given course.
        list($enroledsql, $params) = get_enrolled_sql($context, 'mod/videoboard:view', $groupid);
        $params['videoboardid'] = $this->cm->instance;

        // Get ids of users enrolled in the given course.
        return $DB->count_records_sql("SELECT COUNT('x')
                                         FROM {videoboard_submissions} s
                                    LEFT JOIN {videoboard} a ON a.id = s.videoboard
                                   INNER JOIN ($enroledsql) u ON u.id = s.userid
                                        WHERE s.videoboard = :videoboardid AND
                                              s.timemodified > 0", $params);
    }

    /**
     * Alerts teachers by email of new or changed videoboards that need grading
     *
     * First checks whether the option to email teachers is set for this videoboard.
     * Sends an email to ALL teachers in the course (or in the group if using separate groups).
     * Uses the methods email_teachers_text() and email_teachers_html() to construct the content.
     *
     * @global object
     * @global object
     * @param $submission object The submission that has changed
     * @return void
     */
    function email_teachers($submission) {
        global $CFG, $DB;

        if (empty($this->videoboard->emailteachers)) {          // No need to do anything
            return;
        }

        $user = $DB->get_record('user', array('id'=>$submission->userid));

        if ($teachers = $this->get_graders($user)) {

            $strvideoboards = get_string('modulenameplural', 'videoboard');
            $strvideoboard  = get_string('modulename', 'videoboard');
            $strsubmitted  = get_string('submitted', 'videoboard');

            foreach ($teachers as $teacher) {
                $info = new stdClass();
                $info->username = fullname($user, true);
                $info->videoboard = format_string($this->videoboard->name,true);
                $info->url = $CFG->wwwroot.'/mod/videoboard/submissions.php?id='.$this->cm->id;
                $info->timeupdated = userdate($submission->timemodified, '%c', $teacher->timezone);

                $postsubject = $strsubmitted.': '.$info->username.' -> '.$this->videoboard->name;
                $posttext = $this->email_teachers_text($info);
                $posthtml = ($teacher->mailformat == 1) ? $this->email_teachers_html($info) : '';

                $eventdata = new stdClass();
                $eventdata->modulename       = 'videoboard';
                $eventdata->userfrom         = $user;
                $eventdata->userto           = $teacher;
                $eventdata->subject          = $postsubject;
                $eventdata->fullmessage      = $posttext;
                $eventdata->fullmessageformat = FORMAT_PLAIN;
                $eventdata->fullmessagehtml  = $posthtml;
                $eventdata->smallmessage     = $postsubject;

                $eventdata->name            = 'videoboard_updates';
                $eventdata->component       = 'mod_videoboard';
                $eventdata->notification    = 1;
                $eventdata->contexturl      = $info->url;
                $eventdata->contexturlname  = $info->videoboard;

                message_send($eventdata);
            }
        }
    }

    /**
     * @param string $filearea
     * @param array $args
     * @return bool
     */
    function send_file($filearea, $args) {
        debugging('plugin does not implement file sending', DEBUG_DEVELOPER);
        return false;
    }

    /**
     * Returns a list of teachers that should be grading given submission
     *
     * @param object $user
     * @return array
     */
    function get_graders($user) {
        //potential graders
        $potgraders = get_users_by_capability($this->context, 'mod/videoboard:grade', '', '', '', '', '', '', false, false);

        $graders = array();
        if (groups_get_activity_groupmode($this->cm) == SEPARATEGROUPS) {   // Separate groups are being used
            if ($groups = groups_get_all_groups($this->course->id, $user->id)) {  // Try to find all groups
                foreach ($groups as $group) {
                    foreach ($potgraders as $t) {
                        if ($t->id == $user->id) {
                            continue; // do not send self
                        }
                        if (groups_is_member($group->id, $t->id)) {
                            $graders[$t->id] = $t;
                        }
                    }
                }
            } else {
                // user not in group, try to find graders without group
                foreach ($potgraders as $t) {
                    if ($t->id == $user->id) {
                        continue; // do not send self
                    }
                    if (!groups_get_all_groups($this->course->id, $t->id)) { //ugly hack
                        $graders[$t->id] = $t;
                    }
                }
            }
        } else {
            foreach ($potgraders as $t) {
                if ($t->id == $user->id) {
                    continue; // do not send self
                }
                $graders[$t->id] = $t;
            }
        }
        return $graders;
    }

    /**
     * Creates the text content for emails to teachers
     *
     * @param $info object The info used by the 'emailteachermail' language string
     * @return string
     */
    function email_teachers_text($info) {
        $posttext  = format_string($this->course->shortname, true, array('context' => $this->coursecontext)).' -> '.
                     $this->strvideoboards.' -> '.
                     format_string($this->videoboard->name, true, array('context' => $this->context))."\n";
        $posttext .= '---------------------------------------------------------------------'."\n";
        $posttext .= get_string("emailteachermail", "videoboard", $info)."\n";
        $posttext .= "\n---------------------------------------------------------------------\n";
        return $posttext;
    }

     /**
     * Creates the html content for emails to teachers
     *
     * @param $info object The info used by the 'emailteachermailhtml' language string
     * @return string
     */
    function email_teachers_html($info) {
        global $CFG;
        $posthtml  = '<p><font face="sans-serif">'.
                     '<a href="'.$CFG->wwwroot.'/course/view.php?id='.$this->course->id.'">'.format_string($this->course->shortname, true, array('context' => $this->coursecontext)).'</a> ->'.
                     '<a href="'.$CFG->wwwroot.'/mod/videoboard/index.php?id='.$this->course->id.'">'.$this->strvideoboards.'</a> ->'.
                     '<a href="'.$CFG->wwwroot.'/mod/videoboard/view.php?id='.$this->cm->id.'">'.format_string($this->videoboard->name, true, array('context' => $this->context)).'</a></font></p>';
        $posthtml .= '<hr /><font face="sans-serif">';
        $posthtml .= '<p>'.get_string('emailteachermailhtml', 'videoboard', $info).'</p>';
        $posthtml .= '</font><hr />';
        return $posthtml;
    }

    /**
     * Produces a list of links to the files uploaded by a user
     *
     * @param $userid int optional id of the user. If 0 then $USER->id is used.
     * @param $return boolean optional defaults to false. If true the list is returned rather than printed
     * @return string optional
     */
    function print_user_files($userid=0, $return=false) {
        global $CFG, $USER, $OUTPUT;

        if (!$userid) {
            if (!isloggedin()) {
                return '';
            }
            $userid = $USER->id;
        }

        $output = '';

        $submission = $this->get_submission($userid);
        if (!$submission) {
            return $output;
        }

        $fs = get_file_storage();
        $files = $fs->get_area_files($this->context->id, 'mod_videoboard', 'submission', $submission->id, "timemodified", false);
        if (!empty($files)) {
            require_once($CFG->dirroot . '/mod/videoboard/locallib.php');
            if ($CFG->enableportfolios) {
                require_once($CFG->libdir.'/portfoliolib.php');
                $button = new portfolio_add_button();
            }
            foreach ($files as $file) {
                $filename = $file->get_filename();
                $mimetype = $file->get_mimetype();
                $path = file_encode_url($CFG->wwwroot.'/pluginfile.php', '/'.$this->context->id.'/mod_videoboard/submission/'.$submission->id.'/'.$filename);
                $output .= '<a href="'.$path.'" ><img src="'.$OUTPUT->pix_url(file_mimetype_icon($mimetype)).'" class="icon" alt="'.$mimetype.'" />'.s($filename).'</a>';
                if ($CFG->enableportfolios && $this->portfolio_exportable() && has_capability('mod/videoboard:exportownsubmission', $this->context)) {
                    $button->set_callback_options('videoboard_portfolio_caller', array('id' => $this->cm->id, 'submissionid' => $submission->id, 'fileid' => $file->get_id()), '/mod/videoboard/locallib.php');
                    $button->set_format_by_file($file);
                    $output .= $button->to_html(PORTFOLIO_ADD_ICON_LINK);
                }

                if ($CFG->enableplagiarism) {
                    require_once($CFG->libdir.'/plagiarismlib.php');
                    $output .= plagiarism_get_links(array('userid'=>$userid, 'file'=>$file, 'cmid'=>$this->cm->id, 'course'=>$this->course, 'videoboard'=>$this->videoboard));
                    $output .= '<br />';
                }
            }
            if ($CFG->enableportfolios && count($files) > 1  && $this->portfolio_exportable() && has_capability('mod/videoboard:exportownsubmission', $this->context)) {
                $button->set_callback_options('videoboard_portfolio_caller', array('id' => $this->cm->id, 'submissionid' => $submission->id), '/mod/videoboard/locallib.php');
                $output .= '<br />'  . $button->to_html(PORTFOLIO_ADD_TEXT_LINK);
            }
        }

        $output = '<div class="files">'.$output.'</div>';

        if ($return) {
            return $output;
        }
        echo $output;
    }

    /**
     * Count the files uploaded by a given user
     *
     * @param $itemid int The submission's id as the file's itemid.
     * @return int
     */
    function count_user_files($itemid) {
        $fs = get_file_storage();
        $files = $fs->get_area_files($this->context->id, 'mod_videoboard', 'submission', $itemid, "id", false);
        return count($files);
    }

    /**
     * Returns true if the student is allowed to submit
     *
     * Checks that the videoboard has started and, if the option to prevent late
     * submissions is set, also checks that the videoboard has not yet closed.
     * @return boolean
     */
    function isopen() {
        $time = time();
        if ($this->videoboard->preventlate && $this->videoboard->timedue) {
            return ($this->videoboard->timeavailable <= $time && $time <= $this->videoboard->timedue);
        } else {
            return ($this->videoboard->timeavailable <= $time);
        }
    }


    /**
     * Return true if is set description is hidden till available date
     *
     * This is needed by calendar so that hidden descriptions do not
     * come up in upcoming events.
     *
     * Check that description is hidden till available date
     * By default return false
     * videoboards types should implement this method if needed
     * @return boolen
     */
    function description_is_hidden() {
        return false;
    }

    /**
     * Return an outline of the user's interaction with the videoboard
     *
     * The default method prints the grade and timemodified
     * @param $grade object
     * @return object with properties ->info and ->time
     */
    function user_outline($grade) {

        $result = new stdClass();
        $result->info = get_string('grade').': '.$grade->str_long_grade;
        $result->time = $grade->dategraded;
        return $result;
    }

    /**
     * Print complete information about the user's interaction with the videoboard
     *
     * @param $user object
     */
    function user_complete($user, $grade=null) {
        global $OUTPUT;

        if ($submission = $this->get_submission($user->id)) {

            $fs = get_file_storage();

            if ($files = $fs->get_area_files($this->context->id, 'mod_videoboard', 'submission', $submission->id, "timemodified", false)) {
                $countfiles = count($files)." ".get_string("uploadedfiles", "videoboard");
                foreach ($files as $file) {
                    $countfiles .= "; ".$file->get_filename();
                }
            }

            echo $OUTPUT->box_start();
            echo get_string("lastmodified").": ";
            echo userdate($submission->timemodified);
            echo $this->display_lateness($submission->timemodified);

            $this->print_user_files($user->id);

            echo '<br />';

            $this->view_feedback($submission);

            echo $OUTPUT->box_end();

        } else {
            if ($grade) {
                echo $OUTPUT->container(get_string('grade').': '.$grade->str_long_grade);
                if ($grade->str_feedback) {
                    echo $OUTPUT->container(get_string('feedback').': '.$grade->str_feedback);
                }
            }
            print_string("notsubmittedyet", "videoboard");
        }
    }

    /**
     * Return a string indicating how late a submission is
     *
     * @param $timesubmitted int
     * @return string
     */
    function display_lateness($timesubmitted) {
        return videoboard_display_lateness($timesubmitted, $this->videoboard->timedue);
    }

    /**
     * Empty method stub for all delete actions.
     */
    function delete() {
        //nothing by default
        redirect('view.php?id='.$this->cm->id);
    }

    /**
     * Empty custom feedback grading form.
     */
    function custom_feedbackform($submission, $return=false) {
        //nothing by default
        return '';
    }

    /**
     * Add a get_coursemodule_info function in case any videoboard type wants to add 'extra' information
     * for the course (see resource).
     *
     * Given a course_module object, this function returns any "extra" information that may be needed
     * when printing this activity in a course listing.  See get_array_of_activities() in course/lib.php.
     *
     * @param $coursemodule object The coursemodule object (record).
     * @return cached_cm_info Object used to customise appearance on course page
     */
    function get_coursemodule_info($coursemodule) {
        return null;
    }

    /**
     * Plugin cron method - do not use $this here, create new videoboard instances if needed.
     * @return void
     */
    function cron() {
        //no plugin cron by default - override if needed
    }

    /**
     * Reset all submissions
     */
    function reset_userdata($data) {
        global $CFG, $DB;

        if (!$DB->count_records('videoboard', array('course'=>$data->courseid, 'videoboardtype'=>$this->type))) {
            return array(); // no videoboards of this type present
        }

        $componentstr = get_string('modulenameplural', 'videoboard');
        $status = array();

        $typestr = get_string('type'.$this->type, 'videoboard');
        // ugly hack to support pluggable videoboard type titles...
        if($typestr === '[[type'.$this->type.']]'){
            $typestr = get_string('type'.$this->type, 'videoboard_'.$this->type);
        }

        if (!empty($data->reset_videoboard_submissions)) {
            $videoboardssql = "SELECT a.id
                                 FROM {videoboard} a
                                WHERE a.course=? AND a.videoboardtype=?";
            $params = array($data->courseid, $this->type);

            // now get rid of all submissions and responses
            $fs = get_file_storage();
            if ($videoboards = $DB->get_records_sql($videoboardssql, $params)) {
                foreach ($videoboards as $videoboardid=>$unused) {
                    if (!$cm = get_coursemodule_from_instance('videoboard', $videoboardid)) {
                        continue;
                    }
                    $context = get_context_instance(CONTEXT_MODULE, $cm->id);
                    $fs->delete_area_files($context->id, 'mod_videoboard', 'submission');
                    $fs->delete_area_files($context->id, 'mod_videoboard', 'response');
                }
            }

            $DB->delete_records_select('videoboard_submissions', "videoboard IN ($videoboardssql)", $params);

            $status[] = array('component'=>$componentstr, 'item'=>get_string('deleteallsubmissions','videoboard').': '.$typestr, 'error'=>false);

            if (empty($data->reset_gradebook_grades)) {
                // remove all grades from gradebook
                videoboard_reset_gradebook($data->courseid, $this->type);
            }
        }

        /// updating dates - shift may be negative too
        if ($data->timeshift) {
            shift_course_mod_dates('videoboard', array('timedue', 'timeavailable'), $data->timeshift, $data->courseid);
            $status[] = array('component'=>$componentstr, 'item'=>get_string('datechanged').': '.$typestr, 'error'=>false);
        }

        return $status;
    }


    function portfolio_exportable() {
        return false;
    }

    /**
     * base implementation for backing up subtype specific information
     * for one single module
     *
     * @param filehandle $bf file handle for xml file to write to
     * @param mixed $preferences the complete backup preference object
     *
     * @return boolean
     *
     * @static
     */
    static function backup_one_mod($bf, $preferences, $videoboard) {
        return true;
    }

    /**
     * base implementation for backing up subtype specific information
     * for one single submission
     *
     * @param filehandle $bf file handle for xml file to write to
     * @param mixed $preferences the complete backup preference object
     * @param object $submission the videoboard submission db record
     *
     * @return boolean
     *
     * @static
     */
    static function backup_one_submission($bf, $preferences, $videoboard, $submission) {
        return true;
    }

    /**
     * base implementation for restoring subtype specific information
     * for one single module
     *
     * @param array  $info the array representing the xml
     * @param object $restore the restore preferences
     *
     * @return boolean
     *
     * @static
     */
    static function restore_one_mod($info, $restore, $videoboard) {
        return true;
    }

    /**
     * base implementation for restoring subtype specific information
     * for one single submission
     *
     * @param object $submission the newly created submission
     * @param array  $info the array representing the xml
     * @param object $restore the restore preferences
     *
     * @return boolean
     *
     * @static
     */
    static function restore_one_submission($info, $restore, $videoboard, $submission) {
        return true;
    }

} ////// End of the videoboard_base class


class mod_videoboard_grading_form extends moodleform {
    /** @var stores the advaned grading instance (if used in grading) */
    private $advancegradinginstance;

    function definition() {
        global $OUTPUT;
        $mform =& $this->_form;

        if (isset($this->_customdata->advancedgradinginstance)) {
            $this->use_advanced_grading($this->_customdata->advancedgradinginstance);
        }

        $formattr = $mform->getAttributes();
        $formattr['id'] = 'submitform';
        $mform->setAttributes($formattr);
        // hidden params
        $mform->addElement('hidden', 'offset', ($this->_customdata->offset+1));
        $mform->setType('offset', PARAM_INT);
        $mform->addElement('hidden', 'userid', $this->_customdata->userid);
        $mform->setType('userid', PARAM_INT);
        $mform->addElement('hidden', 'nextid', $this->_customdata->nextid);
        $mform->setType('nextid', PARAM_INT);
        $mform->addElement('hidden', 'id', $this->_customdata->cm->id);
        $mform->setType('id', PARAM_INT);
        $mform->addElement('hidden', 'sesskey', sesskey());
        $mform->setType('sesskey', PARAM_ALPHANUM);
        $mform->addElement('hidden', 'mode', 'grade');
        $mform->setType('mode', PARAM_TEXT);
        $mform->addElement('hidden', 'menuindex', "0");
        $mform->setType('menuindex', PARAM_INT);
        $mform->addElement('hidden', 'saveuserid', "-1");
        $mform->setType('saveuserid', PARAM_INT);
        $mform->addElement('hidden', 'filter', "0");
        $mform->setType('filter', PARAM_INT);

        $mform->addElement('static', 'picture', $OUTPUT->user_picture($this->_customdata->user),
                                                fullname($this->_customdata->user, true) . '<br/>' .
                                                userdate($this->_customdata->submission->timemodified) .
                                                $this->_customdata->lateness );

        $this->add_submission_content();
        $this->add_grades_section();

        $this->add_feedback_section();

        if ($this->_customdata->submission->timemarked) {
            $datestring = userdate($this->_customdata->submission->timemarked)."&nbsp; (".format_time(time() - $this->_customdata->submission->timemarked).")";
            $mform->addElement('header', 'Last Grade', get_string('lastgrade', 'videoboard'));
            $mform->addElement('static', 'picture', $OUTPUT->user_picture($this->_customdata->teacher) ,
                                                    fullname($this->_customdata->teacher,true).
                                                    '<br/>'.$datestring);
        }
        // buttons
        $this->add_action_buttons();

    }

    /**
     * Gets or sets the instance for advanced grading
     *
     * @param gradingform_instance $gradinginstance
     */
    public function use_advanced_grading($gradinginstance = false) {
        if ($gradinginstance !== false) {
            $this->advancegradinginstance = $gradinginstance;
        }
        return $this->advancegradinginstance;
    }

    function add_grades_section() {
        global $CFG;
        $mform =& $this->_form;
        $attributes = array();
        if ($this->_customdata->gradingdisabled) {
            $attributes['disabled'] ='disabled';
        }

        $mform->addElement('header', 'Grades', get_string('grades', 'grades'));

        $grademenu = make_grades_menu($this->_customdata->videoboard->grade);
        if ($gradinginstance = $this->use_advanced_grading()) {
            $gradinginstance->get_controller()->set_grade_range($grademenu);
            $gradingelement = $mform->addElement('grading', 'advancedgrading', get_string('grade').':', array('gradinginstance' => $gradinginstance));
            if ($this->_customdata->gradingdisabled) {
                $gradingelement->freeze();
            } else {
                $mform->addElement('hidden', 'advancedgradinginstanceid', $gradinginstance->get_id());
            }
        } else {
            // use simple direct grading
            $grademenu['-1'] = get_string('nograde');

            $mform->addElement('select', 'xgrade', get_string('grade').':', $grademenu, $attributes);
            $mform->setDefault('xgrade', $this->_customdata->submission->grade ); //@fixme some bug when element called 'grade' makes it break
            $mform->setType('xgrade', PARAM_INT);
        }

        if (!empty($this->_customdata->enableoutcomes)) {
            foreach($this->_customdata->grading_info->outcomes as $n=>$outcome) {
                $options = make_grades_menu(-$outcome->scaleid);
                if ($outcome->grades[$this->_customdata->submission->userid]->locked) {
                    $options[0] = get_string('nooutcome', 'grades');
                    $mform->addElement('static', 'outcome_'.$n.'['.$this->_customdata->userid.']', $outcome->name.':',
                            $options[$outcome->grades[$this->_customdata->submission->userid]->grade]);
                } else {
                    $options[''] = get_string('nooutcome', 'grades');
                    $attributes = array('id' => 'menuoutcome_'.$n );
                    $mform->addElement('select', 'outcome_'.$n.'['.$this->_customdata->userid.']', $outcome->name.':', $options, $attributes );
                    $mform->setType('outcome_'.$n.'['.$this->_customdata->userid.']', PARAM_INT);
                    $mform->setDefault('outcome_'.$n.'['.$this->_customdata->userid.']', $outcome->grades[$this->_customdata->submission->userid]->grade );
                }
            }
        }
        $course_context = get_context_instance(CONTEXT_MODULE , $this->_customdata->cm->id);
        if (has_capability('gradereport/grader:view', $course_context) && has_capability('moodle/grade:viewall', $course_context)) {
            $grade = '<a href="'.$CFG->wwwroot.'/grade/report/grader/index.php?id='. $this->_customdata->courseid .'" >'.
                        $this->_customdata->grading_info->items[0]->grades[$this->_customdata->userid]->str_grade . '</a>';
        }else{
            $grade = $this->_customdata->grading_info->items[0]->grades[$this->_customdata->userid]->str_grade;
        }
        $mform->addElement('static', 'finalgrade', get_string('currentgrade', 'videoboard').':' ,$grade);
        $mform->setType('finalgrade', PARAM_INT);
    }

    /**
     *
     * @global core_renderer $OUTPUT
     */
    function add_feedback_section() {
        global $OUTPUT;
        $mform =& $this->_form;
        $mform->addElement('header', 'Feed Back', get_string('feedback', 'grades'));

        if ($this->_customdata->gradingdisabled) {
            $mform->addElement('static', 'disabledfeedback', $this->_customdata->grading_info->items[0]->grades[$this->_customdata->userid]->str_feedback );
        } else {
            // visible elements

            $mform->addElement('editor', 'submissioncomment_editor', get_string('feedback', 'videoboard').':', null, $this->get_editor_options() );
            $mform->setType('submissioncomment_editor', PARAM_RAW); // to be cleaned before display
            $mform->setDefault('submissioncomment_editor', $this->_customdata->submission->submissioncomment);
            //$mform->addRule('submissioncomment', get_string('required'), 'required', null, 'client');
            switch ($this->_customdata->videoboard->videoboardtype) {
                case 'upload' :
                case 'uploadsingle' :
                    $mform->addElement('filemanager', 'files_filemanager', get_string('responsefiles', 'videoboard'). ':', null, $this->_customdata->fileui_options);
                    break;
                default :
                    break;
            }
            $mform->addElement('hidden', 'mailinfo_h', "0");
            $mform->setType('mailinfo_h', PARAM_INT);
            $mform->addElement('checkbox', 'mailinfo',get_string('enablenotification','videoboard').
            $OUTPUT->help_icon('enablenotification', 'videoboard') .':' );
            $mform->setType('mailinfo', PARAM_INT);
        }
    }

    function add_action_buttons($cancel = true, $submitlabel = NULL) {
        $mform =& $this->_form;
        //if there are more to be graded.
        if ($this->_customdata->nextid>0) {
            $buttonarray=array();
            $buttonarray[] = &$mform->createElement('submit', 'submitbutton', get_string('savechanges'));
            //@todo: fix accessibility: javascript dependency not necessary
            $buttonarray[] = &$mform->createElement('submit', 'saveandnext', get_string('saveandnext'));
            $buttonarray[] = &$mform->createElement('submit', 'next', get_string('next'));
            $buttonarray[] = &$mform->createElement('cancel');
        } else {
            $buttonarray=array();
            $buttonarray[] = &$mform->createElement('submit', 'submitbutton', get_string('savechanges'));
            $buttonarray[] = &$mform->createElement('cancel');
        }
        $mform->addGroup($buttonarray, 'grading_buttonar', '', array(' '), false);
        $mform->closeHeaderBefore('grading_buttonar');
        $mform->setType('grading_buttonar', PARAM_RAW);
    }

    function add_submission_content() {
        $mform =& $this->_form;
        $mform->addElement('header', 'Submission', get_string('submission', 'videoboard'));
        $mform->addElement('static', '', '' , $this->_customdata->submission_content );
    }

    protected function get_editor_options() {
        $editoroptions = array();
        $editoroptions['component'] = 'mod_videoboard';
        $editoroptions['filearea'] = 'feedback';
        $editoroptions['noclean'] = false;
        $editoroptions['maxfiles'] = 0; //TODO: no files for now, we need to first implement videoboard_feedback area, integration with gradebook, files support in quickgrading, etc. (skodak)
        $editoroptions['maxbytes'] = $this->_customdata->maxbytes;
        $editoroptions['context'] = $this->_customdata->context;
        return $editoroptions;
    }

    public function set_data($data) {
        $editoroptions = $this->get_editor_options();
        if (!isset($data->text)) {
            $data->text = '';
        }
        if (!isset($data->format)) {
            $data->textformat = FORMAT_HTML;
        } else {
            $data->textformat = $data->format;
        }

        if (!empty($this->_customdata->submission->id)) {
            $itemid = $this->_customdata->submission->id;
        } else {
            $itemid = null;
        }

        switch ($this->_customdata->videoboard->videoboardtype) {
                case 'upload' :
                case 'uploadsingle' :
                    $data = file_prepare_standard_filemanager($data, 'files', $editoroptions, $this->_customdata->context, 'mod_videoboard', 'response', $itemid);
                    break;
                default :
                    break;
        }

        $data = file_prepare_standard_editor($data, 'submissioncomment', $editoroptions, $this->_customdata->context, $editoroptions['component'], $editoroptions['filearea'], $itemid);
        return parent::set_data($data);
    }

    public function get_data() {
        $data = parent::get_data();

        if (!empty($this->_customdata->submission->id)) {
            $itemid = $this->_customdata->submission->id;
        } else {
            $itemid = null; //TODO: this is wrong, itemid MUST be known when saving files!! (skodak)
        }

        if ($data) {
            $editoroptions = $this->get_editor_options();
            switch ($this->_customdata->videoboard->videoboardtype) {
                case 'upload' :
                case 'uploadsingle' :
                    $data = file_postupdate_standard_filemanager($data, 'files', $editoroptions, $this->_customdata->context, 'mod_videoboard', 'response', $itemid);
                    break;
                default :
                    break;
            }
            $data = file_postupdate_standard_editor($data, 'submissioncomment', $editoroptions, $this->_customdata->context, $editoroptions['component'], $editoroptions['filearea'], $itemid);
        }

        if ($this->use_advanced_grading() && !isset($data->advancedgrading)) {
            $data->advancedgrading = null;
        }

        return $data;
    }
}




function videoboard_user_outline($course, $user, $mod, $videoboard) {
    global $CFG;

    require_once("$CFG->libdir/gradelib.php");
    require_once("$CFG->dirroot/mod/videoboard/type/$videoboard->videoboardtype/videoboard.class.php");
    $videoboardclass = "videoboard_$videoboard->videoboardtype";
    $ass = new $videoboardclass($mod->id, $videoboard, $mod, $course);
    $grades = grade_get_grades($course->id, 'mod', 'videoboard', $videoboard->id, $user->id);
    if (!empty($grades->items[0]->grades)) {
        return $ass->user_outline(reset($grades->items[0]->grades));
    } else {
        return null;
    }
}

/**
 * Prints the complete info about a user's interaction with an videoboard
 *
 * This is done by calling the user_complete() method of the videoboard type class
 */
function videoboard_user_complete($course, $user, $mod, $videoboard) {
    global $CFG;

    require_once("$CFG->libdir/gradelib.php");
    require_once("$CFG->dirroot/mod/videoboard/type/$videoboard->videoboardtype/videoboard.class.php");
    $videoboardclass = "videoboard_$videoboard->videoboardtype";
    $ass = new $videoboardclass($mod->id, $videoboard, $mod, $course);
    $grades = grade_get_grades($course->id, 'mod', 'videoboard', $videoboard->id, $user->id);
    if (empty($grades->items[0]->grades)) {
        $grade = false;
    } else {
        $grade = reset($grades->items[0]->grades);
    }
    return $ass->user_complete($user, $grade);
}


/**
 * Return grade for given user or all users.
 *
 * @param int $videoboardid id of videoboard
 * @param int $userid optional user id, 0 means all users
 * @return array array of grades, false if none
 */
function videoboard_get_user_grades($videoboard, $userid=0) {
    global $CFG, $DB;

    if ($userid) {
        $user = "AND u.id = :userid";
        $params = array('userid'=>$userid);
    } else {
        $user = "";
    }
    $params['aid'] = $videoboard->id;

    $sql = "SELECT u.id, u.id AS userid, s.grade AS rawgrade, s.submissioncomment AS feedback, s.format AS feedbackformat,
                   s.teacher AS usermodified, s.timemarked AS dategraded, s.timemodified AS datesubmitted
              FROM {user} u, {videoboard_submissions} s
             WHERE u.id = s.userid AND s.videoboard = :aid
                   $user";

    return $DB->get_records_sql($sql, $params);
}

/**
 * Update activity grades
 *
 * @param object $videoboard
 * @param int $userid specific user only, 0 means all
 */
function videoboard_update_grades($videoboard, $userid=0, $nullifnone=true) {
    global $CFG, $DB;
    require_once($CFG->libdir.'/gradelib.php');

    if ($videoboard->grade == 0) {
        videoboard_grade_item_update($videoboard);

    } else if ($grades = videoboard_get_user_grades($videoboard, $userid)) {
        foreach($grades as $k=>$v) {
            if ($v->rawgrade == -1) {
                $grades[$k]->rawgrade = null;
            }
        }
        videoboard_grade_item_update($videoboard, $grades);

    } else {
        videoboard_grade_item_update($videoboard);
    }
}

/**
 * Update all grades in gradebook.
 */
function videoboard_upgrade_grades() {
    global $DB;

    $sql = "SELECT COUNT('x')
              FROM {videoboard} a, {course_modules} cm, {modules} m
             WHERE m.name='videoboard' AND m.id=cm.module AND cm.instance=a.id";
    $count = $DB->count_records_sql($sql);

    $sql = "SELECT a.*, cm.idnumber AS cmidnumber, a.course AS courseid
              FROM {videoboard} a, {course_modules} cm, {modules} m
             WHERE m.name='videoboard' AND m.id=cm.module AND cm.instance=a.id";
    $rs = $DB->get_recordset_sql($sql);
    if ($rs->valid()) {
        // too much debug output
        $pbar = new progress_bar('videoboardupgradegrades', 500, true);
        $i=0;
        foreach ($rs as $videoboard) {
            $i++;
            upgrade_set_timeout(60*5); // set up timeout, may also abort execution
            videoboard_update_grades($videoboard);
            $pbar->update($i, $count, "Updating videoboard grades ($i/$count).");
        }
        upgrade_set_timeout(); // reset to default timeout
    }
    $rs->close();
}

/**
 * Create grade item for given videoboard
 *
 * @param object $videoboard object with extra cmidnumber
 * @param mixed optional array/object of grade(s); 'reset' means reset grades in gradebook
 * @return int 0 if ok, error code otherwise
 */
 
function videoboard_grade_item_update($videoboard, $grades=NULL) {
    global $CFG;
    require_once($CFG->libdir.'/gradelib.php');

    if (!isset($videoboard->courseid)) {
        $videoboard->courseid = $videoboard->course;
    }

    $params = array('itemname'=>$videoboard->name, 'idnumber'=>$videoboard->cmidnumber);

    if ($videoboard->grade > 0) {
        $params['gradetype'] = GRADE_TYPE_VALUE;
        $params['grademax']  = $videoboard->grade;
        $params['grademin']  = 0;

    } else if ($videoboard->grade < 0) {
        $params['gradetype'] = GRADE_TYPE_SCALE;
        $params['scaleid']   = -$videoboard->grade;

    } else {
        $params['gradetype'] = GRADE_TYPE_TEXT; // allow text comments only
    }

    if ($grades  === 'reset') {
        $params['reset'] = true;
        $grades = NULL;
    }

    return grade_update('mod/videoboard', $videoboard->courseid, 'mod', 'videoboard', $videoboard->id, 0, $grades, $params);
}


/**
 * Delete grade item for given videoboard
 *
 * @param object $videoboard object
 * @return object videoboard
 */
function videoboard_grade_item_delete($videoboard) {
    global $CFG;
    require_once($CFG->libdir.'/gradelib.php');

    if (!isset($videoboard->courseid)) {
        $videoboard->courseid = $videoboard->course;
    }

    return grade_update('mod/videoboard', $videoboard->courseid, 'mod', 'videoboard', $videoboard->id, 0, NULL, array('deleted'=>1));
}

/**
 * Returns the users with data in one videoboard (students and teachers)
 *
 * @todo: deprecated - to be deleted in 2.2
 *
 * @param $videoboardid int
 * @return array of user objects
 */
function videoboard_get_participants($videoboardid) {
    global $CFG, $DB;

    //Get students
    $students = $DB->get_records_sql("SELECT DISTINCT u.id, u.id
                                        FROM {user} u,
                                             {videoboard_submissions} a
                                       WHERE a.videoboard = ? and
                                             u.id = a.userid", array($videoboardid));
    //Get teachers
    $teachers = $DB->get_records_sql("SELECT DISTINCT u.id, u.id
                                        FROM {user} u,
                                             {videoboard_submissions} a
                                       WHERE a.videoboard = ? and
                                             u.id = a.teacher", array($videoboardid));

    //Add teachers to students
    if ($teachers) {
        foreach ($teachers as $teacher) {
            $students[$teacher->id] = $teacher;
        }
    }
    //Return students array (it contains an array of unique users)
    return ($students);
}

/**
 * Serves videoboard submissions and other files.
 *
 * @param object $course
 * @param object $cm
 * @param object $context
 * @param string $filearea
 * @param array $args
 * @param bool $forcedownload
 * @return bool false if file not found, does not return if found - just send the file
 */
 /*
function videoboard_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload) {
    global $CFG, $DB;

    if ($context->contextlevel != CONTEXT_MODULE) {
        return false;
    }

    require_login($course, false, $cm);

    if (!$videoboard = $DB->get_record('videoboard', array('id'=>$cm->instance))) {
        return false;
    }

    require_once($CFG->dirroot.'/mod/videoboard/type/'.$videoboard->videoboardtype.'/videoboard.class.php');
    $videoboardclass = 'videoboard_'.$videoboard->videoboardtype;
    $videoboardinstance = new $videoboardclass($cm->id, $videoboard, $cm, $course);

    return $videoboardinstance->send_file($filearea, $args);
}
*/
/**
 * Checks if a scale is being used by an videoboard
 *
 * This is used by the backup code to decide whether to back up a scale
 * @param $videoboardid int
 * @param $scaleid int
 * @return boolean True if the scale is used by the videoboard
 */
function videoboard_scale_used($videoboardid, $scaleid) {
    global $DB;

    $return = false;

    $rec = $DB->get_record('videoboard', array('id'=>$videoboardid,'grade'=>-$scaleid));

    if (!empty($rec) && !empty($scaleid)) {
        $return = true;
    }

    return $return;
}

/**
 * Checks if scale is being used by any instance of videoboard
 *
 * This is used to find out if scale used anywhere
 * @param $scaleid int
 * @return boolean True if the scale is used by any videoboard
 */
function videoboard_scale_used_anywhere($scaleid) {
    global $DB;

    if ($scaleid and $DB->record_exists('videoboard', array('grade'=>-$scaleid))) {
        return true;
    } else {
        return false;
    }
}

/**
 * Make sure up-to-date events are created for all videoboard instances
 *
 * This standard function will check all instances of this module
 * and make sure there are up-to-date events created for each of them.
 * If courseid = 0, then every videoboard event in the site is checked, else
 * only videoboard events belonging to the course specified are checked.
 * This function is used, in its new format, by restore_refresh_events()
 *
 * @param $courseid int optional If zero then all videoboards for all courses are covered
 * @return boolean Always returns true
 */
function videoboard_refresh_events($courseid = 0) {
    global $DB;

    if ($courseid == 0) {
        if (! $videoboards = $DB->get_records("videoboard")) {
            return true;
        }
    } else {
        if (! $videoboards = $DB->get_records("videoboard", array("course"=>$courseid))) {
            return true;
        }
    }
    $moduleid = $DB->get_field('modules', 'id', array('name'=>'videoboard'));

    foreach ($videoboards as $videoboard) {
        $cm = get_coursemodule_from_id('videoboard', $videoboard->id);
        $event = new stdClass();
        $event->name        = $videoboard->name;
        $event->description = format_module_intro('videoboard', $videoboard, $cm->id);
        $event->timestart   = $videoboard->timedue;

        if ($event->id = $DB->get_field('event', 'id', array('modulename'=>'videoboard', 'instance'=>$videoboard->id))) {
            update_event($event);

        } else {
            $event->courseid    = $videoboard->course;
            $event->groupid     = 0;
            $event->userid      = 0;
            $event->modulename  = 'videoboard';
            $event->instance    = $videoboard->id;
            $event->eventtype   = 'due';
            $event->timeduration = 0;
            $event->visible     = $DB->get_field('course_modules', 'visible', array('module'=>$moduleid, 'instance'=>$videoboard->id));
            add_event($event);
        }

    }
    return true;
}

/**
 * Print recent activity from all videoboards in a given course
 *
 * This is used by the recent activity block
 */
function videoboard_print_recent_activity($course, $viewfullnames, $timestart) {
    global $CFG, $USER, $DB, $OUTPUT;

    // do not use log table if possible, it may be huge

    if (!$submissions = $DB->get_records_sql("SELECT asb.id, asb.timemodified, cm.id AS cmid, asb.userid,
                                                     u.firstname, u.lastname, u.email, u.picture
                                                FROM {videoboard_submissions} asb
                                                     JOIN {videoboard} a      ON a.id = asb.videoboard
                                                     JOIN {course_modules} cm ON cm.instance = a.id
                                                     JOIN {modules} md        ON md.id = cm.module
                                                     JOIN {user} u            ON u.id = asb.userid
                                               WHERE asb.timemodified > ? AND
                                                     a.course = ? AND
                                                     md.name = 'videoboard'
                                            ORDER BY asb.timemodified ASC", array($timestart, $course->id))) {
         return false;
    }

    $modinfo =& get_fast_modinfo($course); // reference needed because we might load the groups
    $show    = array();
    $grader  = array();

    foreach($submissions as $submission) {
        if (!array_key_exists($submission->cmid, $modinfo->cms)) {
            continue;
        }
        $cm = $modinfo->cms[$submission->cmid];
        if (!$cm->uservisible) {
            continue;
        }
        if ($submission->userid == $USER->id) {
            $show[] = $submission;
            continue;
        }

        // the act of sumbitting of videoboard may be considered private - only graders will see it if specified
        if (empty($CFG->videoboard_showrecentsubmissions)) {
            if (!array_key_exists($cm->id, $grader)) {
                $grader[$cm->id] = has_capability('moodle/grade:viewall', get_context_instance(CONTEXT_MODULE, $cm->id));
            }
            if (!$grader[$cm->id]) {
                continue;
            }
        }

        $groupmode = groups_get_activity_groupmode($cm, $course);

        if ($groupmode == SEPARATEGROUPS and !has_capability('moodle/site:accessallgroups', get_context_instance(CONTEXT_MODULE, $cm->id))) {
            if (isguestuser()) {
                // shortcut - guest user does not belong into any group
                continue;
            }

            if (is_null($modinfo->groups)) {
                $modinfo->groups = groups_get_user_groups($course->id); // load all my groups and cache it in modinfo
            }

            // this will be slow - show only users that share group with me in this cm
            if (empty($modinfo->groups[$cm->id])) {
                continue;
            }
            $usersgroups =  groups_get_all_groups($course->id, $submission->userid, $cm->groupingid);
            if (is_array($usersgroups)) {
                $usersgroups = array_keys($usersgroups);
                $intersect = array_intersect($usersgroups, $modinfo->groups[$cm->id]);
                if (empty($intersect)) {
                    continue;
                }
            }
        }
        $show[] = $submission;
    }

    if (empty($show)) {
        return false;
    }

    echo $OUTPUT->heading(get_string('newsubmissions', 'videoboard').':', 3);

    foreach ($show as $submission) {
        $cm = $modinfo->cms[$submission->cmid];
        $link = $CFG->wwwroot.'/mod/videoboard/view.php?id='.$cm->id;
        print_recent_activity_note($submission->timemodified, $submission, $cm->name, $link, false, $viewfullnames);
    }

    return true;
}


/**
 * Returns all videoboards since a given time in specified forum.
 */
function videoboard_get_recent_mod_activity(&$activities, &$index, $timestart, $courseid, $cmid, $userid=0, $groupid=0)  {
    global $CFG, $COURSE, $USER, $DB;

    if ($COURSE->id == $courseid) {
        $course = $COURSE;
    } else {
        $course = $DB->get_record('course', array('id'=>$courseid));
    }

    $modinfo =& get_fast_modinfo($course);

    $cm = $modinfo->cms[$cmid];

    $params = array();
    if ($userid) {
        $userselect = "AND u.id = :userid";
        $params['userid'] = $userid;
    } else {
        $userselect = "";
    }

    if ($groupid) {
        $groupselect = "AND gm.groupid = :groupid";
        $groupjoin   = "JOIN {groups_members} gm ON  gm.userid=u.id";
        $params['groupid'] = $groupid;
    } else {
        $groupselect = "";
        $groupjoin   = "";
    }

    $params['cminstance'] = $cm->instance;
    $params['timestart'] = $timestart;

    $userfields = user_picture::fields('u', null, 'userid');

    if (!$submissions = $DB->get_records_sql("SELECT asb.id, asb.timemodified,
                                                     $userfields
                                                FROM {videoboard_submissions} asb
                                                JOIN {videoboard} a      ON a.id = asb.videoboard
                                                JOIN {user} u            ON u.id = asb.userid
                                          $groupjoin
                                               WHERE asb.timemodified > :timestart AND a.id = :cminstance
                                                     $userselect $groupselect
                                            ORDER BY asb.timemodified ASC", $params)) {
         return;
    }

    $groupmode       = groups_get_activity_groupmode($cm, $course);
    $cm_context      = get_context_instance(CONTEXT_MODULE, $cm->id);
    $grader          = has_capability('moodle/grade:viewall', $cm_context);
    $accessallgroups = has_capability('moodle/site:accessallgroups', $cm_context);
    $viewfullnames   = has_capability('moodle/site:viewfullnames', $cm_context);

    if (is_null($modinfo->groups)) {
        $modinfo->groups = groups_get_user_groups($course->id); // load all my groups and cache it in modinfo
    }

    $show = array();

    foreach($submissions as $submission) {
        if ($submission->userid == $USER->id) {
            $show[] = $submission;
            continue;
        }
        // the act of submitting of videoboard may be considered private - only graders will see it if specified
        if (empty($CFG->videoboard_showrecentsubmissions)) {
            if (!$grader) {
                continue;
            }
        }

        if ($groupmode == SEPARATEGROUPS and !$accessallgroups) {
            if (isguestuser()) {
                // shortcut - guest user does not belong into any group
                continue;
            }

            // this will be slow - show only users that share group with me in this cm
            if (empty($modinfo->groups[$cm->id])) {
                continue;
            }
            $usersgroups = groups_get_all_groups($course->id, $cm->userid, $cm->groupingid);
            if (is_array($usersgroups)) {
                $usersgroups = array_keys($usersgroups);
                $intersect = array_intersect($usersgroups, $modinfo->groups[$cm->id]);
                if (empty($intersect)) {
                    continue;
                }
            }
        }
        $show[] = $submission;
    }

    if (empty($show)) {
        return;
    }

    if ($grader) {
        require_once($CFG->libdir.'/gradelib.php');
        $userids = array();
        foreach ($show as $id=>$submission) {
            $userids[] = $submission->userid;

        }
        $grades = grade_get_grades($courseid, 'mod', 'videoboard', $cm->instance, $userids);
    }

    $aname = format_string($cm->name,true);
    foreach ($show as $submission) {
        $tmpactivity = new stdClass();

        $tmpactivity->type         = 'videoboard';
        $tmpactivity->cmid         = $cm->id;
        $tmpactivity->name         = $aname;
        $tmpactivity->sectionnum   = $cm->sectionnum;
        $tmpactivity->timestamp    = $submission->timemodified;

        if ($grader) {
            $tmpactivity->grade = $grades->items[0]->grades[$submission->userid]->str_long_grade;
        }

        $userfields = explode(',', user_picture::fields());
        foreach ($userfields as $userfield) {
            if ($userfield == 'id') {
                $tmpactivity->user->{$userfield} = $submission->userid; // aliased in SQL above
            } else {
                $tmpactivity->user->{$userfield} = $submission->{$userfield};
            }
        }
        $tmpactivity->user->fullname = fullname($submission, $viewfullnames);

        $activities[$index++] = $tmpactivity;
    }

    return;
}

/**
 * Print recent activity from all videoboards in a given course
 *
 * This is used by course/recent.php
 */
function videoboard_print_recent_mod_activity($activity, $courseid, $detail, $modnames)  {
    global $CFG, $OUTPUT;

    echo '<table border="0" cellpadding="3" cellspacing="0" class="videoboard-recent">';

    echo "<tr><td class=\"userpicture\" valign=\"top\">";
    echo $OUTPUT->user_picture($activity->user);
    echo "</td><td>";

    if ($detail) {
        $modname = $modnames[$activity->type];
        echo '<div class="title">';
        echo "<img src=\"" . $OUTPUT->pix_url('icon', 'videoboard') . "\" ".
             "class=\"icon\" alt=\"$modname\">";
        echo "<a href=\"$CFG->wwwroot/mod/videoboard/view.php?id={$activity->cmid}\">{$activity->name}</a>";
        echo '</div>';
    }

    if (isset($activity->grade)) {
        echo '<div class="grade">';
        echo get_string('grade').': ';
        echo $activity->grade;
        echo '</div>';
    }

    echo '<div class="user">';
    echo "<a href=\"$CFG->wwwroot/user/view.php?id={$activity->user->id}&amp;course=$courseid\">"
         ."{$activity->user->fullname}</a>  - ".userdate($activity->timestamp);
    echo '</div>';

    echo "</td></tr></table>";
}


function videoboard_display_lateness($timesubmitted, $timedue) {
    if (!$timedue) {
        return '';
    }
    $time = $timedue - $timesubmitted;
    if ($time < 0) {
        $timetext = get_string('late', 'videoboard', format_time($time));
        return ' (<span class="late">'.$timetext.'</span>)';
    } else {
        $timetext = get_string('early', 'videoboard', format_time($time));
        return ' (<span class="early">'.$timetext.'</span>)';
    }
}


function videoboard_get_all_submissions($videoboard, $sort="", $dir="DESC") {
/// Return all videoboard submissions by ENROLLED students (even empty)
    global $CFG, $DB;

    if ($sort == "lastname" or $sort == "firstname") {
        $sort = "u.$sort $dir";
    } else if (empty($sort)) {
        $sort = "a.timemodified DESC";
    } else {
        $sort = "a.$sort $dir";
    }

    /* not sure this is needed at all since videoboard already has a course define, so this join?
    $select = "s.course = '$videoboard->course' AND";
    if ($videoboard->course == SITEID) {
        $select = '';
    }*/

    return $DB->get_records_sql("SELECT a.*
                                   FROM {videoboard_submissions} a, {user} u
                                  WHERE u.id = a.userid
                                        AND a.videoboard = ?
                               ORDER BY $sort", array($videoboard->id));

}


function videoboard_pack_files($filesforzipping) {
        global $CFG;
        //create path for new zip file.
        $tempzip = tempnam($CFG->tempdir.'/', 'videoboard_');
        //zip files
        $zipper = new zip_packer();
        if ($zipper->archive_to_pathname($filesforzipping, $tempzip)) {
            return $tempzip;
        }
        return false;
}


function videoboard_view_dates() {
    global $OUTPUT, $videoboard;
    if (!$videoboard->timeavailable && !$videoboard->timedue) {
        return;
    }

    echo $OUTPUT->box_start('generalbox boxaligncenter', 'dates');
    echo '<table>';
    if ($videoboard->timeavailable) {
        echo '<tr><td class="c0">'.get_string('availabledate','videoboard').':</td>';
        echo '    <td class="c1">'.userdate($videoboard->timeavailable).'</td></tr>';
    }
    if ($videoboard->timedue) {
        echo '<tr><td class="c0">'.get_string('duedate','videoboard').':</td>';
        echo '    <td class="c1">'.userdate($videoboard->timedue).'</td></tr>';
    }
    echo '</table>';
    echo $OUTPUT->box_end();
}


function videoboard_player_video($link, $mime = 'video/mp4', $poster = null, $ids = 0){
    global $OUTPUT, $videoboard, $CFG;
    
    $swfflashmediaelement = new moodle_url('/mod/videoboard/swf/flashmediaelement.swf');
    $flowplayer    = new moodle_url("/mod/videoboard/js/flowplayer-3.2.7.swf");
    
    if ($mime != 'video/mp4') {
        $mime = 'video/mp4';
        
        $player_html5  = '<video width="269" height="198" id="videoboard-player-'.$ids.'" src="'.$link.'" type="'.$mime.'" controls="controls"></video>';
        
        $player_html5_videojs = '<video id="videoboard-player-'.$ids.'" class="video-js vjs-default-skin" controls preload="auto" width="269" height="198" data-setup=\'{"example_option":true}\'> <source src="'.$link.'" type="'.$mime.'" /> </video>';
        
        $player_flash  = html_writer::script('var fn = function() {var att = { data:"'.$swfflashmediaelement.'", width:"269", height:"198" };var par = { flashvars:"controls=true&file='.$link.'" };var id = "videoboard-player-'.$ids.'";var myObject = swfobject.createSWF(att, par, id);};swfobject.addDomLoadEvent(fn);');
        $player_flash .= '<div id="videoboard-player-'.$ids.'"><a href="'.$link.'">audio</a></div>';
        
        $browser = videoboard_get_browser();
        
        if (videoboard_is_ios()) {
            return $player_html5;
        } else if ($browser == 'firefox'){
            return $player_html5_videojs;
        } else if ($browser == 'msie'){
            return $player_flash;
        } else if ($browser == 'chrome'){
            return $player_html5_videojs;
        } else {
            return $player_html5;
        }
    } else {
        $player_flowplayer  = "";
        $player_flowplayer .= html_writer::start_tag('a', array("id" => "videoboard-player-{$ids}", "style" => "display:block;width:269px;height:198px;background: url('".$poster."') no-repeat 0 0;", "href" => $link));
        $player_flowplayer .= html_writer::empty_tag('img', array("src" => new moodle_url("/mod/videoboard/img/playlayer.png"), "alt" => get_string("video", "videoboard"), "width" => 269, "height" => 198));
        $player_flowplayer .= html_writer::end_tag('a');
        $player_flowplayer .= html_writer::script('flowplayer("videoboard-player-'.$ids.'", "'.$flowplayer.'");');
        
        $player_flash  = html_writer::script('var fn = function() {var att = { data:"'.$swfflashmediaelement.'", width:"269", height:"198" };var par = { flashvars:"controls=true&file='.urlencode($link).'&poster='.urlencode($poster).'" };var id = "videoboard-player-'.$ids.'";var myObject = swfobject.createSWF(att, par, id);};swfobject.addDomLoadEvent(fn);');
        $player_flash .= '<div id="videoboard-player-'.$ids.'"><a href="'.$link.'">video</a></div>';
        
        if (!empty($poster)) $poster = 'poster="'.$poster.'"';

        $player_html5 = '<video width="269" height="198" id="videoboard-player-'.$ids.'" src="'.$link.'" type="'.$mime.'" controls="controls" '.$poster.'></video>';
        $player_html5_mediaelementplayer = '<video width="269" height="198" id="videoboard-player-'.$ids.'" src="'.$link.'" type="'.$mime.'" class="mediaelementplayer" controls="controls" '.$poster.'></video>';
        
        $player_html5_videojs = '<video id="videoboard-player-'.$ids.'" controls class="video-js vjs-default-skin" data-setup=\'{"example_option":true}\' preload="auto" width="269" height="198" '.$poster.'> <source src="'.$link.'" type="'.$mime.'" /> </video>';
        
        $browser = videoboard_get_browser();
        
        if (videoboard_is_ios()) {
            return $player_html5;
        } else if ($browser == 'firefox'){
            return $player_flash;
        } else if ($browser == 'msie'){
            return $player_html5_mediaelementplayer;
        } else if ($browser == 'chrome'){
            return $player_html5_mediaelementplayer;
        } else {
            return $player_html5;
        }
    }
}


function videoboard_player_mp3($link, $mime = 'audio/mp3', $ids = 0){
    global $OUTPUT, $videoboard, $CFG;
        
    $player_html5  = "";
    $player_html5 .= html_writer::start_tag('div', array("id" => "html5-player-".$ids));
    $player_html5 .= html_writer::start_tag('audio', array("id" => "html5-audioplayer-".$ids, "controls" => "controls", "src" => $link));
    $player_html5 .= html_writer::link($link, get_string("audio", "videoboard"));
    $player_html5 .= html_writer::end_tag('audio');
    $player_html5 .= html_writer::end_tag('div');
        
        
    $player_flash  = "";
    $player_flash .= html_writer::script('var fn = function() {var att = { data:"'.(new moodle_url("/mod/videoboard/js/mp3player.swf")).'", width:"90", height:"15" };var par = { flashvars:"src='.$link.'" };var id = "videoboard-player-'.$ids.'";var myObject = swfobject.createSWF(att, par, id);};swfobject.addDomLoadEvent(fn);');
    $player_flash .= '<div id="videoboard-player-'.$ids.'"><a href="'.$link.'">audio</a></div>';
                
        
    $browser = videoboard_get_browser();
        
    if (videoboard_is_ios()) {
        return $player_html5;
    } else if ($browser == 'firefox'){
        return $player_flash;
    } else if ($browser == 'msie'){
        return $player_flash;
    } else if ($browser == 'chrome'){
        return $player_flash;
    } else {
        return $player_html5;
    }
}


function videoboard_player_youtube($embed, $ids){
    return '<div id="videoboard-player-'.$ids.'" style="cursor: pointer;">
<img src="http://i.ytimg.com/vi/'.$embed.'/0.jpg" class="videoboard-youtube-poster" data-url="'.$ids.'" data-text="'.$embed.'" style="width: 269px; height:198px" />
</div>
';
}


function videoboard_get_browser(){
    if(strpos($_SERVER['HTTP_USER_AGENT'], 'Android') !== FALSE) 
        return 'android';
    //elseif(strpos($_SERVER['HTTP_USER_AGENT'], 'MSIE') !== FALSE) 
    elseif(preg_match('~MSIE|Internet Explorer~i', $_SERVER['HTTP_USER_AGENT']) || (strpos($_SERVER['HTTP_USER_AGENT'], 'Trident/7.0; rv:11.0') !== false))
        return 'msie';
    elseif(strpos($_SERVER['HTTP_USER_AGENT'], 'Firefox') !== FALSE) 
        return 'firefox';
    elseif(strpos($_SERVER['HTTP_USER_AGENT'], 'Chrome') !== FALSE) 
        return 'chrome';
    elseif(strpos($_SERVER['HTTP_USER_AGENT'], 'iPhone') !== FALSE || strpos($_SERVER['HTTP_USER_AGENT'], 'iPad') !== FALSE) 
        return 'mobileios';
    elseif(strpos($_SERVER['HTTP_USER_AGENT'], 'Safari') !== FALSE) 
        return 'safari';
    else 
        return 'other';
}



