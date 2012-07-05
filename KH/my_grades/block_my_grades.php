<?php

require_once("{$CFG->libdir}/gradelib.php");
require_once($CFG->dirroot . '/grade/report/lib.php');
require_once $CFG->dirroot.'/grade/report/overview/lib.php';

//require_once '../../../config.php';
//require_once $CFG->libdir.'/gradelib.php';
require_once $CFG->dirroot.'/grade/lib.php';
//require_once $CFG->dirroot.'/grade/report/overview/lib.php';

//$courseid = required_param('id', PARAM_INT);
//$userid   = optional_param('userid', $USER->id, PARAM_INT);


class block_my_grades extends block_base {
	public function init() {
		$this->title = get_string('my_grades', 'block_my_grades');
	}
	
	public function get_content() {
		global $DB, $USER;
	
		if ($this->content !== null) {
			return $this->content;
		}
 
		$this->content         =  new stdClass;
		//$this->content->text   = 'The content of our my_grades block!';
		//$this->content->footer = 'Footer here...';
 
		$userid=$USER->id; // hard-coding to ASmith, student
		$courseid=3; // hard-coding to Colour Theory
		
		/// basic access checks
		/*
		if (!$course = $DB->get_record('course', array('id' => $courseid))) {
			print_error('nocourseid');
		}
		require_login($course);
		*/
		
		$modname="assignment";
		$modinstance->id=3;
 
		/// return tracking object
		$gpr = new grade_plugin_return(array('type'=>'report', 'plugin'=>'overview', 'courseid'=>$courseid, 'userid'=>$userid));
 
		// Create a report instance
		$context = get_context_instance(CONTEXT_COURSE, $courseid);
		$report = new grade_report_overview($userid, $gpr, $context);

		$newdata=$this->grade_data($report);
		print_r($newdata);
		if (is_array($newdata))
		{
			foreach($newdata as $newgrade)
			{
				$this->content->text.="<br>{$newgrade[0]} - {$newgrade[1]}";
			}
		}
		else
		{
			$this->content->text.=$newdata;
		}
			// print the page
		//print_grade_page_head($courseid, 'report', 'overview', get_string('pluginname', 'gradereport_overview'). ' - '.fullname($report->user));

		//$report->setup_table();
//		if ($report->fill_table()) {
//			$this->content->text.= $report->print_table(true);
//		}
 
		return $this->content;
	}
	
	public function newfunction() {
		echo("This newfunction is being called");
	}
	
	public function specialization() {
		if (!empty($this->config->title)) {
			$this->title = $this->config->title;
		} else {
			$this->config->title = 'Default title ...';
		}
 
		if (!empty($this->config->text)) {
			$this->content->text = $this->config->text;
		} else {
			$this->config->text = 'Default text ...';
		}    
	}
	
	//print_r($this->config->title);
	/*
	if ($this->config->Allow_HTML=='1')
	{
		echo('allowhtml=1');
	}
	else
	{
		echo('allowhtml=0');
	}
	*/
	
	public function instance_allow_multiple() {
		return true;
	}


	public function grade_data($report) {
		global $CFG, $DB, $OUTPUT;

		// MDL-11679, only show user's courses instead of all courses
		if ($courses = enrol_get_users_courses($report->user->id, false, 'id, shortname, showgrades')) {
			$numusers = $report->get_numusers(false);

			foreach ($courses as $course) {
				//echo("this course id is {$course->id}");
				if (!$course->showgrades) {
					continue;
				}

				$coursecontext = get_context_instance(CONTEXT_COURSE, $course->id);

				if (!$course->visible && !has_capability('moodle/course:viewhiddencourses', $coursecontext)) {
					// The course is hidden and the user isn't allowed to see it
					continue;
				}

				$courseshortname = format_string($course->shortname, true, array('context' => $coursecontext));
				$courselink = html_writer::link(new moodle_url('/grade/report/user/index.php', array('id' => $course->id, 'userid' => $report->user->id)), $courseshortname);
				$canviewhidden = has_capability('moodle/grade:viewhidden', $coursecontext);

				// Get course grade_item
				$course_item = grade_item::fetch_course_item($course->id);

				// Get the stored grade
				$course_grade = new grade_grade(array('itemid'=>$course_item->id, 'userid'=>$report->user->id));
				$course_grade->grade_item =& $course_item;
				$finalgrade = $course_grade->finalgrade;

				/*
				if (!$canviewhidden and !is_null($finalgrade)) {
					if ($course_grade->is_hidden()) {
						$finalgrade = null;
					} else {
						$finalgrade = $report->blank_hidden_total($course->id, $course_item, $finalgrade);
					}
				}
*/
				$data[] = array($courselink, grade_format_gradevalue($finalgrade, $course_item, true));

				if (!$report->showrank) {
					//nothing to do

				} else if (!is_null($finalgrade)) {
					/// find the number of users with a higher grade
					/// please note this can not work if hidden grades involved :-( to be fixed in 2.0
					$params = array($finalgrade, $course_item->id);
					$sql = "SELECT COUNT(DISTINCT(userid))
							  FROM {grade_grades}
							 WHERE finalgrade IS NOT NULL AND finalgrade > ?
								   AND itemid = ?";
					$rank = $DB->count_records_sql($sql, $params) + 1;

					$data[] = "$rank/$numusers";

				} else {
					// no grade, no rank
					$data[] = '-';
				}

				//$report->table->add_data($data);
			}
			return $data;

		} else {
			return $OUTPUT->notification(get_string('nocourses', 'grades'));
		}
	}
}

?>