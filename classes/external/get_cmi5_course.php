<?php

namespace mod_cmi5launch\external;

defined('MOODLE_INTERNAL') || die();

use core_external\external_function_parameters;
use core_external\external_value;
use core_external\external_single_structure;
use core_external\external_multiple_structure;
use core_external\external_api;

class get_cmi5_course extends external_api {

    public static function execute_parameters() {
        return new external_function_parameters([
            'modulename' => new external_value(PARAM_TEXT, 'Module name', VALUE_OPTIONAL),
        ]);
    }

    public static function execute($modulename = null) {
        global $DB;

        $results = [];

        $sql = "SELECT id AS cmi5id, name AS modulename, course
                FROM {cmi5launch}
                WHERE 1 = 1";
        $params = [];

        if (!empty($modulename)) {
            $sql .= " AND name LIKE :modulename";
            $params['modulename'] = "%$modulename%";
        }

        $records = $DB->get_records_sql($sql, $params);

        foreach ($records as $record) {
            $results[] = [
                'cmi5id' => $record->cmi5id,
                'modulename' => $record->modulename,
                'courseid' => $record->course,
            ];
        }

        return $results;
    }

    public static function execute_returns() {
        return new external_multiple_structure(
            new external_single_structure([
                'cmi5id' => new external_value(PARAM_INT, 'CMI5launch instance ID'),
                'modulename' => new external_value(PARAM_TEXT, 'Module name'),
                'courseid' => new external_value(PARAM_INT, 'Course ID'),
            ])
        );
    }
}
