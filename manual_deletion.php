<?php
// Define CLI_SCRIPT to indicate this is a CLI execution.
define('CLI_SCRIPT', true);

// Include the Moodle configuration.
require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/mod/cmi5launch/lib.php');

// Function to prompt for user input in CLI.
function prompt($message) {
    echo $message . ": ";
    return trim(fgets(STDIN));
}

// Ask the user for the instance ID.
$id = prompt("Enter the cmi5launch instance ID to delete");

if (!is_numeric($id) || $id <= 0) {
    echo "Invalid ID. Please provide a positive integer.\n";
    exit(1);
}

// Run the deletion function.
$result = cmi5launch_delete_instance($id);

if ($result) {
    echo "Deletion successful for ID: $id.\n";
} else {
    echo "Deletion failed for ID: $id.\n";
}
