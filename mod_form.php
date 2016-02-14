<?php //$Id: mod_form.php,v 1.2 2012/03/10 22:00:00 Igor Nikulin Exp $

if (!defined('MOODLE_INTERNAL')) {
    die('Direct access to this script is forbidden.');  
}

require_once($CFG->dirroot . '/course/moodleform_mod.php');


class mod_videoboard_mod_form extends moodleform_mod {
    function definition() {
        global $COURSE, $CFG, $form, $USER;
        $mform    =& $this->_form;

        $fmstime = time();

//-------------------------------------------------------------------------------
        $mform->addElement('header', 'general', get_string('general', 'form'));
        $mform->addElement('text', 'name', get_string('name'), array('size'=>'64'));
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');
        $this->add_intro_editor(true, get_string('intro', 'videoboard'));
        
        
        $mform->addElement('header', 'typedesc', get_string("participantsgrading", 'videoboard'));
        
        $mform->addElement('select', 'grade', get_string('grade'), array('1'=>'1', '2'=>'2', '3'=>'3', '4'=>'4', '5'=>'5'));
        $mform->setDefault('grade', 5);
        
        $mform->addElement('select', 'grademethod', get_string('grademethod', "videoboard"), array('default'=>get_string('default', "videoboard"), 'like'=>get_string('thisnewlike', "videoboard")));
        $mform->setDefault('grademethod', 'default');
        
        
        $mform->addElement('header', 'typedesc', get_string("teachergrading", 'videoboard'));
        
        $mform->addElement('select', 'gradet', get_string('grade'), array('1'=>'1', '2'=>'2', '3'=>'3', '4'=>'4', '5'=>'5'));
        $mform->setDefault('gradet', 5);
        
        $mform->addElement('select', 'grademethodt', get_string('grademethod', "videoboard"), array('default'=>get_string('default', "videoboard"), 'rubrics'=>get_string('rubrics', "videoboard")));
        $mform->setDefault('grademethodt', 'default');
        
//-------------------------------------------------------------------------------
        $mform->addElement('header', 'typedesc', get_string("typeupload", 'videoboard'));
        $ynoptions = array( 0 => get_string('no'), 1 => get_string('yes'));
        
        if (!isset($CFG->assignment_maxbytes))
            $CFG->assignment_maxbytes = 10485760;

        $choices = get_max_upload_sizes($CFG->maxbytes, $COURSE->maxbytes);
        $choices[0] = get_string('courseuploadlimit', 'videoboard') . ' ('.display_size($COURSE->maxbytes).')';
        $mform->addElement('select', 'maxbytes', get_string('maximumsize', 'videoboard'), $choices);
        $mform->setDefault('maxbytes', $CFG->assignment_maxbytes);

        $mform->addElement('select', 'resubmit', get_string('allowdeleting', 'videoboard'), $ynoptions);
        $mform->addHelpButton('resubmit', 'allowdeleting', 'videoboard');
        $mform->setDefault('resubmit', 0);
        $mform->setDefault('maxbytes', 10485760);
        
//-------------------------------------------------------------------------------
        $this->standard_coursemodule_elements();
//-------------------------------------------------------------------------------
        $this->add_action_buttons();
    }
}
