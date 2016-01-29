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
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Mobile app for module attendance
 *
 * @package   mod_attendance_mobile
 * @copyright  2016 Jorge Caban� <jorgecabane93@gmail.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Returns a json string for a jsonOutput
 *
 * @param unknown $jsonOutput
 */
function attendance_json_output($jsonOutput)
{
	global $OUTPUT;

	// Callback para from webpage
	$callback = optional_param('callback', null, PARAM_RAW_TRIMMED);

	// Headers
	header('Content-Type: application/javascript');
	header('Cache-Control: no-cache');
	header('Pragma: no-cache');

	if ($callback)
		$jsonOutput = $callback . "(" . $jsonOutput . ");";

		echo $jsonOutput;
		die();
}
/**
 * Returns a json string for a resultset
 *
 * @param unknown $resultset
 */
function attendance_json_resultset($resultset)
{

	// Verify that parameters are OK. Resultset should not be null.
	if (! is_array($resultset) && ! $resultset) {
		attendance_json_error('Invalid parameters for encoding json. Results are null.');
	}

	// First check if results contain data
	if (is_array($resultset)) {
		$output = array(
				'error' => '',
				'values' => array_values($resultset)
		);
		attendance_json_output(json_encode($output));
	} else {
		$output = array(
				'error' => '',
				'values' => $resultset
		);
		attendance_json_output(json_encode($resultset));
	}
}
/**
 * Returns a json array
 *
 * @param unknown $output
 */
function attendance_json_array($output)
{

	// Verify that parameter is OK. Output should not be null.
	if (! $output) {
		attendance_json_error('Invalid parameters for encoding json. output is null.');
	}

	$output = array(
			'error' => '',
			'values' => $output
	);
	attendance_json_output(json_encode($output));
}
/**
 * Returns a json string for an error
 *
 * @param unknown $message
 * @param string $values
 */
function attendance_json_error($message, $values = null)
{
	$output = array(
			'error' => $message,
			'values' => $values
	);
	attendance_json_output(json_encode($output));
}
/**
 * This function return if the attendance activity accepts
 * regrade requests at the current time.
 *
 * @param unknown $emarking
 * @return boolean
 */
function attendance_create_qr_image($qrstring,$attendanceid,$sessid)
{
	global $CFG;
	require_once ($CFG->dirroot . '/mod/attendance/phpqrcode/phpqrcode.php');

	$path= $CFG -> dataroot. "/temp/attendance/" . $attendanceid;
	if (!file_exists($path)) {
		mkdir($path, 0777, true);
	}
	
	$filename = "qr.png";
	$img = $path . "/". $filename;

	QRcode::png($qrstring, $img);
	return 	array($path, $filename);
}
/**
 * Creates a QR image based on a string
 * 
 * @param unknown $qrstring
 * @param unknown $attendanceid
 * @return multitype:string
 */


