<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle. If not, see <http://www.gnu.org/licenses/>.
/**
 *
 * @package mod
 * @subpackage attendance
 * @copyright
 *
 * @license
 *
 */
// define('AJAX_SCRIPT', true);
// define('NO_DEBUG_DISPLAY', true);
require_once (dirname ( dirname ( dirname ( dirname ( __FILE__ ) ) ) ) . '/config.php');
require_once $CFG->dirroot . '/mod/attendance/mobile/locallib.php';
require_once $CFG->libdir . '/accesslib.php';
global $CFG, $DB, $OUTPUT, $PAGE, $USER;

$action = required_param ( 'action', PARAM_ALPHA );
$time = optional_param ( 'time', null , PARAM_RAW_TRIMMED );
$sessionid = optional_param ( 'sessionid', null , PARAM_RAW_TRIMMED );
$attendanceid = optional_param ( 'attendanceid', null , PARAM_RAW_TRIMMED );
$username = required_param ( 'username', PARAM_RAW_TRIMMED );
$password = required_param ( 'password', PARAM_RAW_TRIMMED );

if (! $user = authenticate_user_login ( $username, $password )) {
	attendance_json_error ( 'Invalid username or password' );
}
// This is the correct way to fill up $USER variable
// complete_user_login($user);

switch ($action) {
	
	case 'login':
		if (! $user ) {
			attendance_json_error ( 'Invalid username or password' );
		}
		else{
			attendance_json_error ( 'Valid login' );
		}
		break;
	
		
	case 'sessions' :
		$sqlgetsessions = "SELECT sess.id AS sessionid, course.fullname AS coursename, course.id AS courseid,
							sess.description AS description, FROM_UNIXTIME(sess.sessdate) AS time,
							MAX(sess.timemodified) AS timemodified
							FROM  {attendance_sessions} AS sess
							INNER JOIN {attendance} AS att ON (att.id= sess.attendanceid )
							INNER JOIN {course} AS course ON ( course.id = att.course )
							WHERE
							course.id IN 
									(SELECT course FROM 
									(SELECT c.id AS course
									FROM {user} AS u
									INNER JOIN {role_assignments} AS ra ON (ra.userid = u.id)
									INNER JOIN {context} AS ct ON (ct.id = ra.contextid)
									INNER JOIN {course} AS c ON (c.id = ct.instanceid)
									INNER JOIN {role} AS r ON (r.id = ra.roleid)	
									WHERE u.id= ? ) as courses)
							AND 
							sess.id NOT IN
								( SELECT takensessions FROM (
									SELECT sess.id AS takensessions FROM mdl_attendance_log  AS log
									INNER JOIN mdl_user AS users ON ( users.id = log.studentid )
									INNER JOIN mdl_attendance_sessions AS sess ON (sess.id = log.sessionid)
									INNER JOIN mdl_attendance AS att ON (att.id= sess.attendanceid )
									INNER JOIN mdl_course AS course ON ( course.id = att.course )
									WHERE users.id = ? ) AS taken)
							AND FROM_UNIXTIME(sess.sessdate) >= ?
							GROUP BY sess.sessdate
							ORDER BY FROM_UNIXTIME(sess.sessdate) ASC
				";
		//missing DateADD in case you want to take attendance within a margin of time
		
		$sessions = $DB->get_recordset_sql ( $sqlgetsessions, array (
				$user->id,
				$user->id,
				$time
		) );
		//var_dump($sessions);
		if (! $sessions) {
			$output = array ();								
			$output [] = 0;
		} else {
			foreach ( $sessions as $obj ) {
				$output [] = $obj;
			}
		}
		
		echo attendance_json_array($output);
		break;
		
		
case 'attendance':
		//taking attendance from mobile app
		//requiered params: username, password and session id
		require_once $CFG->dirroot . '/mod/attendance/locallib.php';

		$pageparams = new stdClass();
		$pageparams->sessionid  = $sessionid;
		$pageparams->grouptype  = 0;
		$pageparams->sort       = optional_param('sort', null, PARAM_INT);
		$pageparams->copyfrom   = optional_param('copyfrom', null, PARAM_INT);
		$pageparams->viewmode   = optional_param('viewmode', null, PARAM_INT);
		$pageparams->gridcols   = optional_param('gridcols', null, PARAM_INT);
		$pageparams->page       = optional_param('page', 1, PARAM_INT);
		$pageparams->perpage    = optional_param('perpage', get_config('attendance', 'resultsperpage'), PARAM_INT);
		
		$cm             = get_coursemodule_from_id('attendance', $attendanceid, 0, false, MUST_EXIST);
		$course         = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
		$att            = $DB->get_record('attendance', array('id' => $cm->instance), '*', MUST_EXIST);
		$context = context_system::instance();
	
		$att = new attendance($att, $cm, $course, $context, $pageparams);
		
		$statuses = implode(',', array_keys( (array)$att->get_statuses() ));
		$statussesarray = explode(",", $statuses);
		// el primer string de statuses ($statussesarray[0])significa el value del statusid 
		$now = time();

		$record = new stdClass();
		$record->studentid = $user->id;
		$record->statusid = $statussesarray[0];
		$record->statusset = $statuses;
		$record->remarks = get_string('set_by_student', 'mod_attendance');
		$record->sessionid = $sessionid;
		$record->timetaken = $now;
		$record->takenby = $user->id;

		$dbsesslog = $att->get_session_log($sessionid);
		if (array_key_exists($record->studentid, $dbsesslog)) {
			// Already recorded do not save.
			return false;
		}
		
		$logid = $DB->insert_record('attendance_log', $record, false);
		$record->id = $logid;

		// Update the session to show that a register has been taken, or staff may overwrite records.
		$session = $att->get_session_info($sessionid);
		$session->lasttaken = $now;
		$session->lasttakenby = $USER->id;
		$DB->update_record('attendance_sessions', $session);

		// Update the users grade.
		$att->update_users_grade(array($USER->id));

		/* create url for link in log screen
		 * need to set grouptype to 0 to allow take attendance page to be called
		 * from report/log page */
		
// 		$params = array(
// 				'sessionid' => $this->pageparams->sessionid,
// 				'grouptype' => 0);
		
// 		// Log the change.
// 		$event = \mod_attendance\event\attendance_taken_by_student::create(array(
// 				'objectid' => $this->id,
// 				'context' => $this->context,
// 				'other' => $params));
// 		$event->
//      ('course_modules', $this->cm);
// 		$event->add_record_snapshot('attendance_sessions', $session);
// 		$event->add_record_snapshot('attendance_log', $record);
// 		$event->trigger();
		attendance_json_error ( 'Attendance Taken Correctly!' );
		break;
		
// 		case 'bringqrcode':
// 			require_once $CFG->dirroot . '/mod/attendance/lib.php';
// 			$context = optional_param ( 'context', null , PARAM_RAW_TRIMMED );
// 			$filearea = optional_param ( 'filearea', null , PARAM_RAW_TRIMMED );
// 			$filename = optional_param ( 'filename', null , PARAM_RAW_TRIMMED );
// 			attendance_pluginfile($context, $filearea, $filename);
// 		break;
}
//end of actions
