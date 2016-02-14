<?php

defined('MOODLE_INTERNAL') || die;

if ($ADMIN->fulltree) {
    require_once($CFG->dirroot.'/mod/videoboard/lib.php');

    if (isset($CFG->maxbytes)) {
        $settings->add(new admin_setting_configselect('videoboard_maxbytes', get_string('maximumsize', 'videoboard'),
                           get_string('settings_descr_videoboard_maxbytes', 'videoboard'), 1048576, get_max_upload_sizes($CFG->maxbytes)));
    }

    $options = array(VIDEOBOARD_COUNT_WORDS   => trim(get_string('numwords', '', '?')),
                     VIDEOBOARD_COUNT_LETTERS => trim(get_string('numletters', '', '?')));
    $settings->add(new admin_setting_configselect('videoboard_itemstocount', get_string('itemstocount', 'videoboard'),
                       get_string('settings_descr_videoboard_itemstocount', 'videoboard'), VIDEOBOARD_COUNT_WORDS, $options));

    $settings->add(new admin_setting_configcheckbox('videoboard_showrecentsubmissions', get_string('showrecentsubmissions', 'videoboard'),
                       get_string('configshowrecentsubmissions', 'videoboard'), 1));
                       
    // Converting method
    $options = array();
    $options[1] = get_string('usemediaconvert', 'videoboard');
    //$options[2] = get_string('usethisserver', 'videoboard');
    $options[3] = get_string('useyoutube', 'videoboard');
    $options[4] = get_string('noconversionfiles', 'videoboard');
    
    $settings->add(new admin_setting_configselect('videoboard_video_convert',
            get_string('convertmethodvideo', 'videoboard'), get_string('descrforconvertingvideo', 'videoboard'), 1, $options));
    
    $options = array();
    $options[1] = get_string('usemediaconvert', 'videoboard');
    //$options[2] = get_string('usethisserver', 'videoboard');
    $options[4] = get_string('noconversionfiles', 'videoboard');
    
    $settings->add(new admin_setting_configselect('videoboard_audio_convert',
            get_string('convertmethodaudio', 'videoboard'), get_string('descrforconvertingaudio', 'videoboard'), 1, $options));
            
    // Converting url
    $settings->add(new admin_setting_configtext('videoboard_convert_url',
            get_string('converturl', 'videoboard'), get_string('descrforconvertingurl', 'videoboard'), '', PARAM_URL));
            
    // YouTube email
    $settings->add(new admin_setting_configtext('videoboard_youtube_email',
            get_string('youtube_email', 'videoboard'), get_string('descrforyoutube_email', 'videoboard'), '', PARAM_EMAIL));
            
    // YouTube password
    $settings->add(new admin_setting_configtext('videoboard_youtube_password',
            get_string('youtube_password', 'videoboard'), get_string('descrforyoutube_password', 'videoboard'), '', PARAM_TEXT));
            
    // YouTube ApiKey
    $settings->add(new admin_setting_configtext('videoboard_youtube_apikey',
            get_string('youtube_apikey', 'videoboard'), get_string('descrforyoutube_apikey', 'videoboard'), '', PARAM_TEXT));
}
