<?php

namespace mod_cmi5launch\external;

defined('MOODLE_INTERNAL') || die();

use core_external\external_function_parameters;
use core_external\external_value;
use core_external\external_single_structure;
use core_external\external_api;
use context_course;

require_once($CFG->dirroot . '/mod/cmi5launch/lib.php');
require_once($CFG->libdir . '/filelib.php');
require_once($CFG->dirroot . '/course/modlib.php');
require_once($CFG->dirroot . '/course/lib.php');

class update_cmi5_course extends external_api
{

    public static function execute_parameters()
    {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'ID of the cmi5 launch link'),
            'sectionid' => new external_value(PARAM_INT, 'Section ID inside the course'),
            'filename' => new external_value(PARAM_TEXT, 'Filename of uploaded file'),
            'modulename' => new external_value(PARAM_TEXT, 'Name of the created module'),
            'courseid' => new external_value(PARAM_INT, 'Moodle course ID', VALUE_DEFAULT, -1),
            'coursename' => new external_value(PARAM_TEXT, 'Moodle course fullname', VALUE_OPTIONAL),
        ]);
    }

    public static function execute(
        $cmid,
        $sectionid,
        $filename,
        $modulename,
        $courseid = null,
        $coursename = null
    ) {
        global $DB, $USER;



        $status = 'success';
        $message = 'Activity created successfully.';

        try {
            $params = self::validate_parameters(self::execute_parameters(), [
                'cmid' => $cmid,
                'sectionid' => $sectionid,
                'filename' => $filename,
                'modulename' => $modulename,
                'courseid' => $courseid,
                'coursename' => $coursename,

            ]);

            if ($params['courseid'] != -1) {
                $course = $DB->get_record('course', ['id' => $params['courseid']], '*', MUST_EXIST);
            } else if (!empty($params['coursename'])) {
                $course = $DB->get_record('course', ['fullname' => $params['coursename']], '*', MUST_EXIST);
            } else {
                throw new \moodle_exception('You must provide either courseid or coursename.');
            }

            $context = context_course::instance($course->id);
            self::validate_context($context);

            if (empty($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
                throw new \moodle_exception('No file uploaded or upload error.');
            }

            $uploadedfile = $_FILES['file']['tmp_name'];

            // Move uploaded file
            $tmpdir = make_temp_directory('cmi5launchupload');
            $zippath = $tmpdir . '/' . $params['filename'];
            if (!move_uploaded_file($uploadedfile, $zippath)) {
                throw new \moodle_exception('Failed to move uploaded file.');
            }

            // List zip contents
            $zip = new \zip_packer();
            $zipcontent = $zip->list_files($zippath);

            if ($zipcontent === false) {
                throw new \moodle_exception('Failed to list ZIP contents.');
            }

            $coursemodule = get_coursemodule_from_id('cmi5launch', $params['cmid'], 0, false, MUST_EXIST);
            $cmi5launch = $DB->get_record('cmi5launch', ['id' => $coursemodule->instance], '*', MUST_EXIST);

            // Update cmi5launch fields
            $cmi5launch->name = $params['modulename'];
            $cmi5launch->packagefile = file_get_unused_draft_itemid();

            // Create draft file
            $fs = get_file_storage();
            $usercontext = \context_user::instance($USER->id);
            $filerecord = [
                'contextid' => $usercontext->id,
                'component' => 'user',
                'filearea' => 'draft',
                'itemid' => $cmi5launch->packagefile,
                'filepath' => '/',
                'filename' => $params['filename'] . '.zip',
            ];
            $fs->create_file_from_pathname($filerecord, $zippath);

            $updatecmi5launch = clone $cmi5launch;
            $updatecmi5launch->instance = $cmi5launch->id;
            $updatecmi5launch->course = $course->id;
            $updatecmi5launch->coursemodule = $coursemodule->id;

            $result = cmi5launch_update_instance($updatecmi5launch);

            if (!$result) {
                throw new \moodle_exception('Failed to update cmi5launch instance.');
            }

            rebuild_course_cache($course->id);
        } catch (\Exception $e) {
            $status = 'error';
            $message = $e->getMessage();
        }

        return [
            'status' => $status,
            'message' => $message,
        ];
    }

    public static function execute_returns()
    {
        return new external_single_structure([
            'status' => new external_value(PARAM_TEXT, 'Status'),
            'message' => new external_value(PARAM_TEXT, 'Human readable result')
        ]);
    }
}
