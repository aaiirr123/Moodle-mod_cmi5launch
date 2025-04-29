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

class upload_cmi5_course extends external_api
{

    public static function execute_parameters()
    {
        return new external_function_parameters([
            'sectionid' => new external_value(PARAM_INT, 'Section ID inside the course'),
            'filename' => new external_value(PARAM_TEXT, 'Filename of uploaded file'),
            'modulename' => new external_value(PARAM_TEXT, 'Name of the created module'),
            'courseid' => new external_value(PARAM_INT, 'Moodle course ID', VALUE_DEFAULT, -1),
            'coursename' => new external_value(PARAM_TEXT, 'Moodle course fullname', VALUE_OPTIONAL),
        ]);
    }

    public static function execute(
        $sectionid,
        $filename,
        $modulename,
        $courseid = null,
        $coursename = null
    ) {
        global $DB, $USER;



        $status = 'success';
        $message = 'Activity created successfully.';
        $cmid = 0;


        try {
            $params = self::validate_parameters(self::execute_parameters(), [
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

            // Create cmi5launch module instance
            $moduleinfo = new \stdClass();
            $moduleinfo->intro = "";
            $moduleinfo->course = $course->id;
            $moduleinfo->name =  $params['moudlename'];
            $moduleinfo->section = $params['sectionid'];
            $moduleinfo->modulename = 'cmi5launch';
            $moduleinfo->overridedefaults = 0;
            $moduleinfo->module = $DB->get_field('modules', 'id', ['name' => 'cmi5launch'], MUST_EXIST);
            $moduleinfo->visible = 1;


            $fs = get_file_storage();
            $usercontext = \context_user::instance($USER->id);
            $moduleinfo->packagefile = file_get_unused_draft_itemid();

            $filerecord = [
                'contextid' => $usercontext->id,
                'component' => 'user',
                'filearea' => 'draft',
                'itemid' => $moduleinfo->packagefile,
                'filepath' => '/',
                'filename' => $params['filename'] . '.zip',
            ];

            $fs->create_file_from_pathname($filerecord, $zippath);

            $cm = add_moduleinfo($moduleinfo, $course);

            $cmid = $cm->coursemodule;
            course_add_cm_to_section($course->id, $cmid, $params['sectionid']);

            rebuild_course_cache($course->id);
        } catch (\Exception $e) {
            $status = 'error';
            $message = $e->getMessage();
        }

        return [
            'cmid' => $cmid,
            'status' => $status,
            'message' => $message,
        ];
    }

    public static function execute_returns()
    {
        return new external_single_structure([
            'cmid' => new external_value(PARAM_INT, 'Course module ID'),
            'status' => new external_value(PARAM_TEXT, 'Status'),
            'message' => new external_value(PARAM_TEXT, 'Human readable result'),
        ]);
    }
}
