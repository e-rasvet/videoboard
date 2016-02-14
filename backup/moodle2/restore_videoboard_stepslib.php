<?php

/**
 * Structure step to restore one videoboard activity
 */
class restore_videoboard_activity_structure_step extends restore_activity_structure_step {
 
    protected function define_structure() {
 
        $paths = array();

        $paths[] = new restore_path_element('videoboard', '/activity/videoboard');
        $paths[] = new restore_path_element('videoboard_files', '/activity/videoboard/files');
        $paths[] = new restore_path_element('videoboard_ratings', '/activity/videoboard/ratings');
        $paths[] = new restore_path_element('videoboard_comments', '/activity/videoboard/comments');
        $paths[] = new restore_path_element('videoboard_process', '/activity/videoboard/process');
 
        // Return the paths wrapped into standard activity structure
        return $this->prepare_activity_structure($paths);
    }
 
    protected function process_videoboard($data) {
        global $DB;
  
        $data = (object)$data;
        $oldid = $data->id;
        $data->course = $this->get_courseid();

        $data->timeopen = $this->apply_date_offset($data->timeopen);
        $data->timeclose = $this->apply_date_offset($data->timeclose);
        $data->introformat = $this->apply_date_offset($data->introformat);
        $data->timemodified = $this->apply_date_offset($data->timemodified);
        $data->embedvideo = $this->apply_date_offset($data->embedvideo);
        $data->recordtype = $this->apply_date_offset($data->recordtype);
        $data->teacher = $this->get_mappingid('user', $data->teacher);
 
        // insert the videoboard record
        $newitemid = $DB->insert_record('videoboard', $data);
        // immediately after inserting "activity" record, call this
        $this->apply_activity_instance($newitemid);
    }
    
    protected function process_attendanceslip_files($data) {
        global $DB;
 
        $data = (object)$data;
        $oldid = $data->id;
 
        $data->instance = $this->get_new_parentid('videoboard');
        
        $data->userid = $this->get_mappingid('user', $data->userid);
        $data->summary = $this->apply_date_offset($data->summary);
        
        if (!empty($this->get_mappingid('files', $data->itemoldid)))
          $data->itemoldid = $this->get_mappingid('files', $data->itemoldid); 
        else
          $data->itemoldid = $this->apply_date_offset($data->itemoldid);
        
        if (!empty($this->get_mappingid('files', $data->itemid)))
          $data->itemid = get_mappingid('files', $data->itemid); 
        else
          $data->itemid = $this->apply_date_offset($data->itemid);
        
        if (!empty($this->get_mappingid('files', $data->itemimgid)))
          $data->itemimgid = $this->get_mappingid('files', $data->itemimgid); 
        else
          $data->itemimgid = $this->apply_date_offset($data->itemimgid);
          
        $data->filename = $this->apply_date_offset($data->filename);
        $data->time = $this->apply_date_offset($data->time);
 
        $newitemid = $DB->insert_record('videoboard_files', $data);
        $this->set_mapping('videoboard_files', $oldid, $newitemid);
    }
    
    protected function process_attendanceslip_ratings($data) {
        global $DB;
 
        $data = (object)$data;
        $oldid = $data->id;
 
        $data->fileid = $this->get_new_parentid('videoboard_files');
        
        $data->userid = $this->get_mappingid('user', $data->userid);
        $data->rating = $this->apply_date_offset($data->rating);
        $data->ratingrhythm = $this->apply_date_offset($data->ratingrhythm);
        $data->ratingclear = $this->apply_date_offset($data->ratingclear);
        $data->ratingintonation = $this->apply_date_offset($data->ratingintonation);
        $data->ratingspeed = $this->apply_date_offset($data->ratingspeed);
        $data->ratingreproduction = $this->apply_date_offset($data->ratingreproduction);
        $data->summary = $this->apply_date_offset($data->summary);
        $data->time = $this->apply_date_offset($data->time);
        
        $newitemid = $DB->insert_record('videoboard_ratings', $data);
        $this->set_mapping('videoboard_ratings', $oldid, $newitemid);
    }
    
    protected function process_attendanceslip_comments($data) {
        global $DB;
 
        $data = (object)$data;
        $oldid = $data->id;
 
        $data->instance = $this->get_new_parentid('videoboard');
        
        $data->userid = $this->get_mappingid('user', $data->userid);
        $data->fileid = $this->get_new_parentid('videoboard_files');
        $data->summary = $this->apply_date_offset($data->summary);
        
        if (!empty($this->get_mappingid('files', $data->itemoldid)))
          $data->itemoldid = $this->get_mappingid('files', $data->itemoldid); 
        else
          $data->itemoldid = $this->apply_date_offset($data->itemoldid);
        
        if (!empty($this->get_mappingid('files', $data->itemid)))
          $data->itemid = get_mappingid('files', $data->itemid); 
        else
          $data->itemid = $this->apply_date_offset($data->itemid);
        
        if (!empty($this->get_mappingid('files', $data->itemimgid)))
          $data->itemimgid = $this->get_mappingid('files', $data->itemimgid); 
        else
          $data->itemimgid = $this->apply_date_offset($data->itemimgid);
          
        $data->filename = $this->apply_date_offset($data->filename);
        $data->time = $this->apply_date_offset($data->time);
 
        $newitemid = $DB->insert_record('videoboard_comments', $data);
        $this->set_mapping('videoboard_comments', $oldid, $newitemid);
    }
    
    
    protected function process_attendanceslip_process($data) {
        global $DB;
 
        $data = (object)$data;
        $oldid = $data->id;
 
        $data->type = $this->apply_date_offset($data->type);
        
        if (!empty($this->get_mappingid('files', $data->itemid)))
          $data->itemid = $this->get_mappingid('files', $data->itemid); 
        else
          $data->itemid = $this->apply_date_offset($data->itemid);
 
        $newitemid = $DB->insert_record('videoboard_process', $data);
        $this->set_mapping('videoboard_process', $oldid, $newitemid);
    }
    
    protected function after_execute() {
        // Add videoboard related files, no need to match by itemname (just internally handled context)
        $this->add_related_files('mod_videoboard', 'intro', null);
    }
}