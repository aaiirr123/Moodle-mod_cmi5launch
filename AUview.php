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
 * Prints an AUs session information annd allows retreival of session or start of new one. 
 *
 * @copyright  2023 Megan Bohland
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(dirname(dirname(__FILE__))) . '/config.php');
require('header.php');

//For connecting to Progress class - MB
require_once("$CFG->dirroot/mod/cmi5launch/cmi5PHP/src/Progress.php");

global $cmi5launch;

// Trigger module viewed event.
$event = \mod_cmi5launch\event\course_module_viewed::create(array(
    'objectid' => $cmi5launch->id,
    'context' => $context,
));
$event->add_record_snapshot('course', $course);
$event->add_record_snapshot('cmi5launch', $cmi5launch);
$event->add_record_snapshot('course_modules', $cm);
$event->trigger();

// Print the page header.
$PAGE->set_url('/mod/cmi5launch/view.php', array('id' => $cm->id));
$PAGE->set_title(format_string($cmi5launch->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);
$PAGE->requires->jquery();

// Output starts here.
echo $OUTPUT->header();

if ($cmi5launch->intro) { // Conditions to show the intro can change to look for own settings or whatever.
    echo $OUTPUT->box(
        format_module_intro('cmi5launch', $cmi5launch, $cm->id),
        'generalbox mod_introbox',
        'cmi5launchintro'
    );
}

// TODO: Put all the php inserted data as parameters on the functions and put the functions in a separate JS file.
?>

    <script>
      
        function key_test(registration) {
        
            if (event.keyCode === 13 || event.keyCode === 32) {
                mod_cmi5launch_launchexperience(registration);
            }
        }
        
        // Function to run when the experience is launched.
        function mod_cmi5launch_launchexperience(registration) {
            // Set the form paramters.
            $('#launchform_registration').val(registration);
            // Post it.
            $('#launchform').submit();
            
            //Add some new content.
            if (!$('#cmi5launch_status').length) {
                var message = "<?php echo get_string('cmi5launch_progress', 'cmi5launch'); ?>";
                $('#region-main .card-body').append('\
                <div id="cmi5launch_status"> \
                    <span id="cmi5launch_completioncheck"></span> \
                    <p id="cmi5launch_attemptprogress">' + message + '</p> \
                    <p id="cmi5launch_exit"> \
                        <a href="complete.php?id=<?php echo $id ?>&n=<?php echo $n ?>" title="Return to course"> \
                            Return to course \
                        </a> \
                    </p> \
                </div>\
            ');
            }
            $('#cmi5launch_completioncheck').load('completion_check.php?id=<?php echo $id ?>&n=<?php echo $n ?>');
        }

        // TODO: there may be a better way to check completion. Out of scope for current project.
        $(document).ready(function() {
            setInterval(function() {
                $('#cmi5launch_completioncheck').load('completion_check.php?id=<?php echo $id ?>&n=<?php echo $n ?>');
            }, 30000); // TODO: make this interval a configuration setting.
        });
    </script>
<?php


//Retrieve the registration AND au ID from view.php
$fromView = required_param('AU_view', PARAM_TEXT);
//Break it into array (AU is first index)
$lmsAndId = explode(",", $fromView);
//Retrieve AU ID
$auID = array_shift($lmsAndId);

 // Reload cmi5 instance.
 $record = $DB->get_record('cmi5launch', array('id' => $cmi5launch->id));
//Ok what is record here?
$regid = $record->registrationid;

///Ok, lets start with a simple test
//Lets check for the certain capability and display a message if it is found/not found
//Excellent! The test works, now lets introduce like a flag, and use that to display progress or not
//Well call it canSEe for now
$canSee = true | false;
//now change it based on capability
$context = context_module::instance($cm->id);
if (has_capability('mod/cmi5launch:addinstance', $context)) {
    // Do or display something.
    echo "<br>";
    echo "This is someone we want to let see grades/progress!!!";
    echo "<br>";
    $canSee = true;
}else{
    echo "<br>";
    echo "This is not someone to see grades! Boo!";
    echo "<br>";
    $canSee = false;
}

    //If it is NOT empty there are relevent registrations
    if (!$lmsAndId[0] == "") {
        
        $getregistrationdatafromlrsstate = cmi5launch_get_global_parameters_and_get_state(
            "http://cmi5api.co.uk/stateapikeys/registrations"
        );

        $lrsrespond = $getregistrationdatafromlrsstate->httpResponse['status'];

        if ($lrsrespond != 200 && $lrsrespond != 404) {
            // On clicking new attempt, save the registration details to the LRS State and launch a new attempt.
            echo "<div class='alert alert-error'>" . get_string('cmi5launch_notavailable', 'cmi5launch') . "</div>";

            if ($CFG->debug == 32767) {
                echo "<p>Error attempting to get registration data from State API.</p>";
                echo "<pre>";
                var_dump($getregistrationdatafromlrsstate);
                echo "</pre>";
            }
            die();
        }
            $registrationdatafromlrs = json_decode($getregistrationdatafromlrsstate->content->getContent(), true);

            //Array to hold verbs and be returned
           // $progress = array();

            //Remove dupliicate registration IDs (now it removes dupe object ids)
            $lmsAndId = array_unique($lmsAndId);

            //Array to hold info for table population
            $tableData = array();

            //Build table
            $table = new html_table();
            $table->id = 'cmi5launch_auSessionTable';
            $table->caption = get_string('modulenameplural', 'cmi5launch');
            $table->head = array(
                get_string('cmi5launchviewfirstlaunched', 'cmi5launch'),
                get_string('cmi5launchviewlastlaunched', 'cmi5launch'),
                get_string('cmi5launchviewprogress', 'cmi5launch'),
                get_string('cmi5launchviewlaunchlinkheader', 'cmi5launch'),
            );


            foreach ($lmsAndId as $lmsId) {

                //array to hold data for table
                $sessionInfo = array();

                $sessionInfo[] = date_format(
                    date_create($registrationdatafromlrs[$regid]['created']),
                    'D, d M Y H:i:s'
                );
                $sessionInfo[] = date_format(
                    date_create($registrationdatafromlrs[$regid]['lastlaunched']),
                    'D, d M Y H:i:s'
                );

              //Bring in progress class
              //MB
              //Lets try to only do this if based on canSee
        if ($canSee == true) {
            $progress = new progress;
            $getProgress = $progress->getRetrieveStatement();

    

            $sessionInfo[] = ("<pre>" . implode("\n ", $getProgress($regid, $cmi5launch->id, $lmsId)) . "</pre>");
        }

        //Create a string to pass the AU ID and registration to next page (launch.ph)
        $infoForNextPage = $auID; // . "," . $regId;
        
            $sessionInfo[] =
                    "<a tabindex=\"0\" id='cmi5relaunch_attempt'
                onkeyup=\"key_test('" . $infoForNextPage . "')\" onclick=\"mod_cmi5launch_launchexperience('" . $infoForNextPage . "')\" style='cursor: pointer;'>"
                    . get_string('cmi5launchviewlaunchlink', 'cmi5launch') . "</a>"
                ;

                //add to be fed to table
                $tableData[] = $sessionInfo;
            }

            $table->data = $tableData;

            echo html_writer::table($table);

    //Create a string to pass the auid and reg to next page (launch.php)
    $infoForNextPage = $auID; // . "," . $regId;

            //This builds the start new reg button - MB
            // Needs to come after previous attempts so a non-sighted user can hear launch options.
            if ($cmi5launch->cmi5multipleregs) {
                echo "<p id='cmi5launch_newattempt'><a tabindex=\"0\"
            onkeyup=\"key_test('" . $infoForNextPage . "')\" onclick=\"mod_cmi5launch_launchexperience('"
                    . $infoForNextPage
                    . "')\" style=\"cursor: pointer;\">"
                    . get_string('cmi5launch_attempt', 'cmi5launch')
                    . "</a></p>";
            }
        }
        else {
            
            //Create a string to pass the auid and reg to next page (launch.php)
            $infoForNextPage = $auID;

            //New attempt
            echo "<p tabindex=\"0\"
            onkeyup=\"key_test('" . $infoForNextPage . "')\"
            id='cmi5launch_newattempt'><a onclick=\"mod_cmi5launch_launchexperience('"
            . $infoForNextPage
            . "')\" style=\"cursor: pointer;\">"
            . get_string('cmi5launch_attempt', 'cmi5launch')
            . "</a></p>";
        }

// Add a form to be posted based on the attempt selected.
?>
    <form id="launchform" action="launch.php" method="get" target="_blank">
        <input id="launchform_registration" name="launchform_registration" type="hidden" value="default">
        <input id="id" name="id" type="hidden" value="<?php echo $id ?>">
        <input id="n" name="n" type="hidden" value="<?php echo $n ?>">
    </form>
<?php

echo $OUTPUT->footer();
