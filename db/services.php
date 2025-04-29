<?php

$functions = [
    'mod_cmi5launch_upload_cmi5_course' => [
        'classname' => 'mod_cmi5launch\external\upload_cmi5_course',
        'methodname' => 'execute',
        'description' => 'Upload a CMI5 course and create a module',
        'type' => 'write',
        'ajax' => true,
        'capabilities' => 'moodle/course:manageactivities',
    ],
];
