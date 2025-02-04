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
 * Class to hold ways to communicate with CMI5 player through its API's.
 *
 * @copyright  2023 Megan Bohland
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_cmi5launch\local;

defined('MOODLE_INTERNAL') || die();

use mod_cmi5launch\local\cmi5launch_helpers;
// Include the errorover (error override) funcs.
require_once($CFG->dirroot . '/mod/cmi5launch/classes/local/errorover.php');
class cmi5_connectors
{

    public function cmi5launch_get_create_tenant()
    {
        return [$this, 'cmi5launch_create_tenant'];
    }
    public function cmi5launch_get_retrieve_token()
    {
        return [$this, 'cmi5launch_retrieve_token'];
    }
    public function cmi5launch_get_retrieve_url()
    {
        return [$this, 'cmi5launch_retrieve_url'];
    }
    public function cmi5launch_get_create_course()
    {
        return [$this, 'cmi5launch_create_course'];
    }
    public function cmi5launch_get_delete_course()
    {
        return [$this, 'cmi5launch_delete_course'];
    }

    public function cmi5launch_get_session_info()
    {
        return [$this, 'cmi5launch_retrieve_session_info_from_player'];
    }
    public function cmi5launch_get_registration_with_post()
    {
        return [$this, 'cmi5launch_retrieve_registration_with_post'];
    }
    public function cmi5launch_get_registration_with_get()
    {
        return [$this, 'cmi5launch_retrieve_registration_with_get'];
    }
    public function cmi5launch_get_send_request_to_cmi5_player_post()
    {
        return [$this, 'cmi5launch_send_request_to_cmi5_player_post'];
    }

    public function cmi5launch_get_send_request_to_cmi5_player_get()
    {
        return [$this, 'cmi5launch_send_request_to_cmi5_player_get'];
    }

    public function cmi5launch_get_connectors_error_message()
    {
        return [$this, 'cmi5launch_connectors_error_message'];
    }

    /**
     * Function to create a course.
     * @param mixed $id - tenant id in Moodle.
     * @param mixed $tenanttoken - tenant bearer token.
     * @param mixed $filename -- The filename of the course to be imported, to be added to url POST request.
     * @return bool|string - Response from cmi5 player.
     */
    public function cmi5launch_create_course($id, $tenanttoken, $filename)
    {

        global $DB, $CFG;

        $settings = cmi5launch_settings($id);

        // Build URL to import course to.
        $url = $settings['cmi5launchplayerurl'] . "/api/v1/course";

        // To determine the headers.
        $filetype = "zip";

        $databody = $filename->get_content();

        // So this one has some troubleshooting built in already, but we probably need to throw an exception to stop function or moodle will freak

        // Sends the stream to the specified URL.
        $result = $this->cmi5launch_send_request_to_cmi5_player_post('cmi5launch_stream_and_send', $databody, $url, $filetype, $tenanttoken);

        // Now this will never return false, it will throw an exception if it fails, so we can just return the result
        try {
            // Check result and display message if not 200.
            $resulttest = $this->cmi5launch_connectors_error_message($result, "creating the course");

            //    echo "Is it resulting?";
            if ($resulttest == true) {
                // Return an array with course info.
                return $result;
                // I think this is the problem, it is coming back and throwing another error! I thought it would stop... do I need a kill in the error message hander?
                // Its throwing BOB??? The third path is executing, THATS the problem!
                // either way though, shouldn't the error funtion have ITS own test? like,
                //  what we need to test here is is resulttrue is true or not
            } else {
                // This should never be false, it should throw an exception if it is, so we can just return the result
                // But catch all else that miht go wrong
                throw new playerException("creating the course.");
            }
        } // catch all else that might go wrong
        catch (\Throwable $e) {
            throw new playerException("creating the course" . $e);
        }
    }

    public function cmi5launch_delete_course($id, $tenanttoken)
    {
        global $DB, $USER, $CFG;

        $record = $DB->get_record("cmi5launch", array('id' => $id));

        $settings = cmi5launch_settings($id);

        $userscourse = $DB->get_record('cmi5launch_usercourse', ['courseid'  => $record->courseid, 'userid'  => $USER->id]);

        $playerurl = $settings['cmi5launchplayerurl'];
        $courseid = $userscourse->courseid;


        // Build URL for launch URL request.
        $url = $playerurl . "/api/v1/course/" . $courseid;

        // Sends the stream to the specified URL.
        $result = $this->cmi5launch_send_request_to_cmi5_player_delete('cmi5launch_stream_and_send', $tenanttoken, $url);

        // Now this will never return false, it will throw an exception if it fails, so we can just return the result
        try {
            echo "It worked?";
            //     // Check result and display message if not 200.
            //     $resulttest = $this->cmi5launch_connectors_error_message($result, "creating the course");

            // //    echo "Is it resulting?";
            //     if ($resulttest == true) {
            //         // Return an array with course info.
            //         return $result;
            //         // I think this is the problem, it is coming back and throwing another error! I thought it would stop... do I need a kill in the error message hander?
            //         // Its throwing BOB??? The third path is executing, THATS the problem!
            //         // either way though, shouldn't the error funtion have ITS own test? like,
            //         //  what we need to test here is is resulttrue is true or not
            //     } else {
            //         // This should never be false, it should throw an exception if it is, so we can just return the result
            //         // But catch all else that miht go wrong
            //         throw new playerException("creating the course.");
            //     }
        } // catch all else that might go wrong
        catch (\Throwable $e) {
            throw new playerException("creating the course" . $e);
        }
    }


    /**
     * Function to create a tenant.
     * @param $urltosend - URL retrieved from user in URL textbox.
     * @param $username - username.
     * @param $password - password.
     * @param $newtenantname - the name the new tenant will be, retreived from Tenant Name textbox.
     */
    public function cmi5launch_create_tenant($newtenantname)
    {

        global $CFG, $cmi5launchid;

        $settings = cmi5launch_settings($cmi5launchid);

        $username = $settings['cmi5launchbasicname'];
        $playerurl = $settings['cmi5launchplayerurl'];
        $password = $settings['cmi5launchbasepass'];

        // Build URL for launch URL request.
        $url = $playerurl . "/api/v1/tenant";

        // The body of the request must be made as array first.
        $data = array(
            'code' => $newtenantname
        );

        // To determine the headers.
        $filetype = "json";

        // Data needs to be JSON encoded.
        $data = json_encode($data);

        // Sends the stream to the specified URL.
        $result = $this->cmi5launch_send_request_to_cmi5_player_post('cmi5launch_stream_and_send', $data, $url, $filetype, $username, $password);


        // Check result and display message if not 200.
        $resulttest = $this->cmi5launch_connectors_error_message($result, "creating the tenant");
        // why is it coming back null and shouldnt we go to else the?


        // Now this will never return false, it will throw an exception if it fails, so we can just return the result
        try {
            if ($resulttest == true) {

                return $result;
            } else {

                throw new playerException("creating the tenant.");
            }
        } // catch all else that might go wrong
        catch (\Throwable $e) {

            throw new playerException("Uncaught error creating the tenant" . $e);
        }
    }

    /**
     * Function to retrieve registration from cmi5 player.
     * This way uses the registration ID and GET request.
     * Registration  is "code" in returned json body.
     * @param $registration - registration UUID
     * @param $id - cmi5 launch id
     */
    public function cmi5launch_retrieve_registration_with_get($registration, $id)
    {

        $settings = cmi5launch_settings($id);

        $token = $settings['cmi5launchtenanttoken'];
        $playerurl = $settings['cmi5launchplayerurl'];

        global $CFG;

        // Build URL for launch URL request.
        $url = $playerurl . "/api/v1/registration/" . $registration;

        // Sends the stream to the specified URL.
        $result = $this->cmi5launch_send_request_to_cmi5_player_get('cmi5launch_stream_and_send', $token, $url);

        // Check result and display message if not 200.
        $resulttest = $this->cmi5launch_connectors_error_message($result, "retrieving the registration");

        // Now this will never return false, it will throw an exception if it fails, so we can just return the result
        try {
            if ($resulttest == true) {
                return $result;
            } else {

                throw new playerException("retrieving the registration information.");
            }
        } // catch all else that might go wrong
        catch (\Throwable $e) {

            throw new playerException("Uncaught error retrieving the registration information." . $e);
        }
    }

    /**
     * Function to retrieve registration from cmi5 player.
     * This way uses the course id and actor name.
     * As this is a POST request it returns a new code everytime it is called.
     * Registration  is "code" in returned json body.
     * @param $courseid - course id - The course ID in the CMI5 player.
     * @param $id - the course id in MOODLE.
     */
    public function cmi5launch_retrieve_registration_with_post($courseid, $id)
    {

        global $USER;

        $settings = cmi5launch_settings($id);

        $actor = $USER->username;
        $token = $settings['cmi5launchtenanttoken'];
        $playerurl = $settings['cmi5launchplayerurl'];
        $homepage = $settings['cmi5launchcustomacchp'];
        global $CFG;

        // Build URL for launch URL request.
        $url = $playerurl . "/api/v1/registration";

        // The body of the request must be made as array first.
        $data = array(
            'courseId' => $courseid,
            'actor' => array(
                'account' => array(
                    "homePage" => $homepage,
                    "name" => $actor,
                ),
            ),
        );

        // Data needs to be JSON encoded.
        $data = json_encode($data);
        // To determine the headers.
        $filetype = "json";

        // Sends the stream to the specified URL.
        $result = $this->cmi5launch_send_request_to_cmi5_player_post('cmi5launch_stream_and_send', $data, $url, $filetype, $token);

        // Check result and display message if not 200.
        $resulttest = $this->cmi5launch_connectors_error_message($result, "retrieving the registration");

        // Now this will never return false, it will throw an exception if it fails, so we can just return the result
        try {
            if ($resulttest == true) {

                $registrationinfo = json_decode($result, true);

                // The returned 'registration info' is a large json object.
                // Code is the registration id we want.
                $registration = $registrationinfo["code"];

                return $registration;
            } else {
                throw new playerException("retrieving the registration information.");
            }
        } // catch all else that might go wrong
        catch (\Throwable $e) {

            throw new playerException("Uncaught error retrieving the registration information." . $e);
        }
    }

    /**
     * Function to retrieve a token from cmi5 player.
     * @param $url - URL to send request to
     * @param $username - username
     * @param $password - password
     * @param $audience - the name the of the audience using the token,
     * @param #tenantid - the id of the tenant
     */
    public function cmi5launch_retrieve_token($audience, $tenantid)
    {

        // Honestly the params can be rabbbed through settings right? So I thinks we can change this whole func.
        // but if it is called, will it need to go tooo secret back page? 
        // and can we make it same page, like if pthere is no prompt? which is fdiff then null right? Or maybe another page just to be certain.

        global $CFG, $cmi5launchid;

        $settings = cmi5launch_settings($cmi5launchid);

        //$actor = $USER->username;
        $username = $settings['cmi5launchbasicname'];
        $playerurl = $settings['cmi5launchplayerurl'];
        $password = $settings['cmi5launchbasepass'];

        // Build URL for launch URL request.
        $url = $playerurl . "/api/v1/auth";

        // The body of the request must be made as array first.
        $data = array(
            'tenantId' => $tenantid,
            'audience' => $audience,
        );
        $filetype = "json";

        // Data needs to be JSON encoded.
        $data = json_encode($data);

        // Sends the stream to the specified URL.
        $result = $this->cmi5launch_send_request_to_cmi5_player_post('cmi5launch_stream_and_send', $data, $url, $filetype, $username, $password);

        // Check result and display message if not 200.
        $resulttest = $this->cmi5launch_connectors_error_message($result, 'retrieving the tenant token.');

        // Now this will never return false, it will throw an exception if it fails, so we can just return the result
        try {
            if ($resulttest == true) {

                $resultDecoded = json_decode($result, true);
                $token = $resultDecoded['token'];

                return $token;
            } else {
                throw new playerException("retrieving the tenant token.");
            }
        } // catch all else that might go wrong
        catch (\Throwable $e) {
            throw new playerException("Uncaught error retrieving the tenant token." . $e);
        }
    }

    /**
     * Function to retrieve a launch URL for an AU.
     * @param $id - courses's ID in MOODLE to retrieve corect record.
     * @param $auindex -AU's index to send to request for launch url.
     */
    public function cmi5launch_retrieve_url($id, $auindex)
    {

        global $DB, $USER;

        // Retrieve actor record, this enables correct actor info for URL storage.
        $record = $DB->get_record("cmi5launch", array('id' => $id));

        $settings = cmi5launch_settings($id);

        $userscourse = $DB->get_record('cmi5launch_usercourse', ['courseid'  => $record->courseid, 'userid'  => $USER->id]);

        $registrationid = $userscourse->registrationid;

        $homepage = $settings['cmi5launchcustomacchp'];
        $returnurl = $userscourse->returnurl;
        $actor = $USER->username;
        $token = $settings['cmi5launchtenanttoken'];
        $playerurl = $settings['cmi5launchplayerurl'];
        $courseid = $userscourse->courseid;


        // Build URL for launch URL request.
        $url = $playerurl . "/api/v1/course/" . $courseid  . "/launch-url/" . $auindex;

        $data = array(
            'actor' => array(
                'account' => array(
                    "homePage" => $homepage,
                    "name" => $actor,
                ),
            ),
            'returnUrl' => $returnurl,
            'reg' => $registrationid,
        );

        // To determine the headers.
        $filetype = "json";

        // Data needs to be JSON encoded.
        $data = json_encode($data);
        // Sends the stream to the specified URL.
        $result = $this->cmi5launch_send_request_to_cmi5_player_post('cmi5launch_stream_and_send', $data, $url, $filetype, $token);

        // Check result and display message if not 200.
        $resulttest = $this->cmi5launch_connectors_error_message($result, "retrieving the launch url from player.");

        // Now this will never return false, it will throw an exception if it fails, so we can just return the result
        try {
            if ($resulttest == true) {
                // Only return the URL.
                $urldecoded = json_decode($result, true);

                return $urldecoded;
            } else {
                throw new playerException("retrieving the launch url from player.");
            }
        } // catch all else that might go wrong
        catch (\Throwable $e) {
            throw new playerException("Uncaught error retrieving the launch url from player." . $e);
        }
    }

    /**
     * Function to construct, send an URL, and save result as POST message to player.
     * @param $databody - the data that will be used to construct the body of request as JSON.
     * @param $url - The URL the request will be sent to.
     * @param $filetype - The type of file being sent, either zip or json.
     * @param ...$tokenorpassword is a variable length param. If one is passed, it is $token, if two it is $username and $password.
     * @return - $result is the response from cmi5 player.
     */
    public function cmi5launch_send_request_to_cmi5_player_post($cmi5launch_stream_and_send, $databody, $url, $filetype, ...$tokenorpassword)
    {

        // Set error and exception handler to catch and override the default PHP error messages, to make messages more user friendly.
        set_error_handler('mod_cmi5launch\local\sifting_data_warning', E_WARNING);
        set_exception_handler('mod_cmi5launch\local\exception_au');

        try {

            // I rhink this whole thing should be try catch cause there are several things that cango w
            // Assign passed in function to variable.
            $stream = $cmi5launch_stream_and_send;
            // Determine content type to be used in header.
            // It is also the same as accepted type.
            $contenttype = $filetype;
            if ($contenttype == "zip") {
                $contenttype = "application/zip\r\n";
            } else if ("json") {
                $contenttype = "application/json\r\n";
            }

            // If number of args is greater than one it is for retrieving tenant info and args are username and password.
            if (count($tokenorpassword) == 2) {

                $username = $tokenorpassword[0];
                $password = $tokenorpassword[1];


                // Use key 'http' even if you send the request to https://...
                // There can be multiple headers but as an array under the ONE header.
                // Content(body) must be JSON encoded here, as that is what CMI5 player accepts.
                $options = array(
                    'http' => array(
                        'method' => 'POST',
                        'header' => array(
                            'Authorization: Basic ' . base64_encode("$username:$password"),
                            "Content-Type: " . $contenttype .
                                "Accept: " . $contenttype
                        ),
                        'content' => ($databody),
                    ),
                );

                //By calling the function this way, it enables encapsulation of the function and allows for testing.
                //It is an extra step, but necessary for required PHP Unit testing.
                $result = call_user_func($stream, $options, $url);


                // Else the args are what we need for posting a course.
            } else {

                // First arg will be token.
                $token = $tokenorpassword[0];

                // Use key 'http' even if you send the request to https://...
                // There can be multiple headers but as an array under the ONE header
                // content(body) must be JSON encoded here, as that is what CMI5 player accepts
                // JSON_UNESCAPED_SLASHES used so http addresses are displayed correctly.
                $options = array(
                    'http' => array(
                        'method' => 'POST',
                        'ignore_errors' => true,
                        'header' => array(
                            "Authorization: Bearer " . $token,
                            "Content-Type: " . $contenttype .
                                "Accept: " . $contenttype
                        ),
                        'content' => ($databody),
                    ),
                );


                //By calling the function this way, it enables encapsulation of the function and allows for testing.
                //It is an extra step, but necessary for required PHP Unit testing.
                $result = call_user_func($stream, $options, $url);

                // Ok, calling it throuw the third party isn't workin, what if we mock call_user_func instead and have an eror thrown there
            }


            // Restore default hadlers.
            restore_exception_handler();
            restore_error_handler();

            // Return response.
            return $result;
        } catch (\Throwable $e) {

            // Restore default hadlers.
            restore_exception_handler();
            restore_error_handler();
            //
            throw new playerException("communicating with player, sending or crafting a POST request: " . $e);
        }
    }

    /**
     * Function to construct and send GET request to CMI5 player.
     * @param $token - the token that will be used to authenticate the request.
     * @param $url - The URL the request will be sent to.
     * @return - $sessionDecoded is the response from cmi5 player.
     */
    public function cmi5launch_send_request_to_cmi5_player_get($cmi5launch_stream_and_send, $token, $url)
    {

        $stream = $cmi5launch_stream_and_send;
        // Use key 'http' even if you send the request to https://...
        // There can be multiple headers but as an array under the ONE header
        // content(body) must be JSON encoded here, as that is what CMI5 player accepts
        // JSON_UNESCAPED_SLASHES used so http addresses are displayed correctly.
        $options = array(
            'http' => array(
                'method'  => 'GET',
                'ignore_errors' => true,
                'header' => array(
                    "Authorization: Bearer " . $token,
                    "Content-Type: application/json\r\n" .
                        "Accept: application/json\r\n"
                ),
            ),
        );

        try {
            //By calling the function this way, it enables encapsulation of the function and allows for testing.
            //It is an extra step, but necessary for required PHP Unit testing.
            $result = call_user_func($stream, $options, $url);

            // Return response.
            return $result;
        } catch (\Throwable $e) {
            //  echo" are we here?";
            throw new playerException("communicating with player, sending or crafting a GET request: " . $e);
        }
    }

    public function cmi5launch_send_request_to_cmi5_player_delete($cmi5launch_stream_and_send, $token, $url)
    {
        // Stream function for handling the HTTP request
        $stream = $cmi5launch_stream_and_send;

        // HTTP context options for DELETE request
        $options = array(
            'http' => array(
                'method'  => 'DELETE', // Change to DELETE
                'ignore_errors' => true,
                'header' => array(
                    "Authorization: Bearer " . $token,
                    "Content-Type: application/json\r\n" .
                        "Accept: application/json\r\n"
                ),
            ),
        );

        try {
            // Call the provided stream function with the constructed options and URL
            $result = call_user_func($stream, $options, $url);

            // Return the response
            return $result;
        } catch (\Throwable $e) {
            // Throw an exception if the DELETE request fails
            throw new playerException("Error communicating with player while sending a DELETE request: " . $e->getMessage());
        }
    }

    /**
     * Retrieve session info from cmi5player
     * @param mixed $sessionid - the session id to retrieve
     * @param mixed $id - cmi5 id
     * @return mixed $sessionDecoded - the session info from cmi5 player.
     */
    public function cmi5launch_retrieve_session_info_from_player($sessionid, $id)
    {

        global $DB;

        $settings = cmi5launch_settings($id);

        $token = $settings['cmi5launchtenanttoken'];
        $playerurl = $settings['cmi5launchplayerurl'];

        // Build URL for launch URL request.
        $url = $playerurl . "/api/v1/session/" . $sessionid;

        // Sends the stream to the specified URL.
        $result = $this->cmi5launch_send_request_to_cmi5_player_get('cmi5launch_stream_and_send', $token, $url);

        // Check result and display message if not 200.
        $resulttest = $this->cmi5launch_connectors_error_message($result, "retrieving the session information.");

        // Now this will never return false, it will throw an exception if it fails, so we can just return the result
        try {
            if ($resulttest == true) {

                return $result;
            } else {
                throw new playerException("retrieving the session information.");
            }
        } // catch all else that might go wrong
        catch (\Throwable $e) {

            throw new playerException("Uncaught error retrieving the session information." . $e);
        }
    }


    // An error message catcher.
    /**
     * Function to test returns from cmi5 player and display error message if found to be false
     * // or not 200.
     * @param mixed $resulttotest - The result to test.
     * @param string $type - The type missing to be added to the error message.
     * @return bool
     */
    public  function cmi5launch_connectors_error_message($resulttotest, $type)
    {


        // Decode result because if it is not 200 then something went wrong
        // If it's a string, decode it.
        if (is_string($resulttotest)) {
            $resulttest = json_decode($resulttotest, true);
        } else {
            $resulttest = $resulttotest;
        }

        // I think splittin these to return two seperate messages deppennnding on whether player is running is better.
        // Player cannot return an error if not runnin,
        if ($resulttest === false) {


            $errormessage =  $type . " CMI5 Player is not communicating. Is it running?";

            throw new playerException($errormessage);
        } else if (array_key_exists("statusCode", $resulttest) && $resulttest["statusCode"] != 200) {


            $errormessage = $type . " CMI5 Player returned " . $resulttest["statusCode"] . " error. With message '"
                . $resulttest["message"] . "'.";

            //   echo"whatt is error messae before throwing::: " . $errormessage;
            //  echo" what is error messae: " . $errormessage;"";
            throw new playerException($errormessage);
        } else {
            // No errors, continue.

            return true;
        }
    }
}
