<?php

    require_once("../../config.php");
    require_once("lib.php");
    
    $fileid         = optional_param('file', NULL, PARAM_INT);
    
    $file     = videoboard_getfileid($fileid);
    
    header("Content-type: audio/x-mpeg");
    
    if (isset($_SERVER['HTTP_RANGE']))  {
      videoboard_rangeDownload($file->fullpatch);
    } else {
      header("Content-Length: ".filesize($file->fullpatch));
      readfile($file->fullpatch);
    }
