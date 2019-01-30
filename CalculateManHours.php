<?php

namespace Stanford\CalculateManHours;

include_once "emLoggerTrait.php";

use \Plugin;
use \ExternalModules\ExternalModules;


use DateTime;
use DateInterval;


/*
 *

This runs one day at a time, parsing all projects and users for that day









 *
 */

class CalculateManHours extends \ExternalModules\AbstractExternalModule {

    use emLoggerTrait;

    // const USER_SESSION_TABLE        = "stanford_session_user_summary";
//     // Each row is a user (on one project)...
//     const SQL_CREATE_USER_TABLE = "CREATE TABLE `stanford_session_user_summary` (
//   `id` bigint(20) NOT NULL AUTO_INCREMENT,
//   `username` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
//   `ip` varchar(100) COLLATE utf8_unicode_ci DEFAULT NULL,
//   `session_count` int(11) DEFAULT NULL COMMENT 'Number of log rows summarized',
//   `session_start` datetime DEFAULT NULL,
//   `session_end` datetime DEFAULT NULL,
//   `duration` int(11) DEFAULT NULL COMMENT 'Seconds',
//   PRIMARY KEY (`id`),
//   UNIQUE KEY `stanford_session_user_summary_username_session_start_pk` (`username`,`session_start`),
//   KEY `stanford_session_user_summary_username_idx` (`username`)
// ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci";


    const PROJECT_SESSION_TABLE    = "stanford_session_project_summary";
    const SQL_CREATE_PROJECT_TABLE = "CREATE TABLE `stanford_session_project_summary` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `username` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `project_id` int(10) DEFAULT NULL,
  `ip` varchar(100) COLLATE utf8_unicode_ci DEFAULT NULL,
  `session_count` int(11) DEFAULT NULL COMMENT 'Number of log rows summarized',
  `session_start` datetime DEFAULT NULL,
  `session_end` datetime DEFAULT NULL,
  `duration` int(11) DEFAULT NULL COMMENT 'Seconds',
  PRIMARY KEY (`id`),
  UNIQUE KEY `stanford_session_project_summary_username_project_id_session_start_pk` (`username`,`project_id`,`session_start`),
  KEY `stanford_session_project_summary_username_idx` (`username`),
  KEY `stanford_session_project_summary_project_idx` (`project_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci";


    public  $scan_range_start_date;
    public  $activity_interval;
    public  $last_date_completed;
    public  $yesterday;

    public  $next_date_dt;      // This is a DT Object for the date we are doing on this run!

    public  $is_running;    // Boolean to describe if the system is running and should not do anything...

    public $ts_start;


    public function __construct()
    {
        parent::__construct();
    }


    /**
     * When enabled for a server, verify the tables exist
     * @param $version
     * @param $project_id
     */
    public function redcap_module_system_enable() {
        $this->emDebug("Running redcap_module_system_enable");

        // See if tables exist
        // $result = $this->checkTableAndCreate("stanford_session_user_summary", self::SQL_CREATE_USER_TABLE);
        // if (! $result) $this->emError("Error creating stanford_session_user_summary");

        $result = $this->checkTableAndCreate("stanford_session_project_summary", self::SQL_CREATE_PROJECT_TABLE);
        if (! $result) $this->emError("Error creating stanford_session_user_summary");

        if ($result) $this->emDebug("All tables exist");
     }


    /**
     * See if a table exists
     * @param $table_name
     * @return bool
     */
    public function tableExists($table_name) {
        // Make sure we have the custom log table
        $q = db_query("SELECT 1 FROM " . db_real_escape_string($table_name) . " LIMIT 1");
        return !($q === FALSE);
    }


    /**
     * Check and/or create tables
     * @param $table_name
     * @param $create_sql
     * @return bool
     */
    private function checkTableAndCreate($table_name, $create_sql) {
        if (! $this->tableExists($table_name)) {
            $this->emDebug("$table_name does not exist - creating!");
            $q = $this->query($create_sql);
            if ($q !== true) {
                $this->emError("Error creating table $table_name");
                return false;
            }
        }

        // Assume it worked!
        return true;
    }


    /**
     * Prepares the object and checks if an update is necessary and performs the update
     * @return bool         Did the update succeed.  If true, you should run again
     * @throws \Exception
     */
    public function checkStatus()
    {

        $this->scan_range_start_date    = $this->getSystemSetting('scan_range_start_date');
        $this->activity_interval        = $this->getSystemSetting('activity_interval');
        $this->last_date_completed      = $this->getSystemSetting('last_date_completed');

        // Determine the date of yesterday
        $yesterday_dt = new DateTime();
        $yesterday_dt->add(DateInterval::createFromDateString('yesterday'));
        $this->yesterday                = $yesterday_dt->format('Y-m-d');

        // Is this required scan_range_start_date present
        if (empty($this->scan_range_start_date)) {
            $this->emDebug("Unable to begin until a scan_range_start_date is configured for this EM");
            return false;
        }

        // Is scan_range_start_date a valid date
        if (DateTime::createFromFormat('Y-m-d', $this->scan_range_start_date) === FALSE) {
            $this->emDebug("The scan_range_start_date is not a valid Y-m-d format");
            return false;
        }
        $scan_range_start_date_dt = new DateTime($this->scan_range_start_date);

        // Is the activity interval valid
        if (empty($this->activity_interval) || intval($this->activity_interval) != $this->activity_interval || $this->activity_interval < 1) {
            $this->emDebug("The activity_interval is not a valid non-zero integer");
            return false;
        }

        // Is scan_range_start_date before or equal to yesterday
        if ($scan_range_start_date_dt > $yesterday_dt) {
            $this->emDebug("The scan_range_start_date is not far enough in the past to do anything");
            return false;
        }


        // Set the last_date_completed if not already set to one day before the start date
        if (empty($this->last_date_completed)) {
            $last_date_completed_dt = clone $scan_range_start_date_dt;
            $last_date_completed_dt->sub(new DateInterval('P1D'));
            $this->last_date_completed = $last_date_completed_dt->format('Y-m-d');
        }

        // Determine the next date
        $this->next_date_dt = new DateTime($this->last_date_completed);
        $this->next_date_dt->add(new DateInterval('P1D'));

        // See if yesterday has already been done
        if ( strtotime($this->last_date_completed) >= strtotime($this->yesterday) ) {
            // We are up to date - no need to do anything
            $this->emDebug('Up to date - no need to do anything');
            return false;
        }

        $this->emDebug("Updating summary of " . $this->next_date_dt->format('Y-m-d') );

        $result = $this->summarizeDate($this->next_date_dt);

        return $result;
    }


    /**
     * Perform a summary on the specified date
     * @param DateTime $date_dt
     * @return bool
     */
    public function summarizeDate($date_dt) {
        $date_ymd = $date_dt->format("Y-m-d");

        // DELETE ANY EXISTING DATA FOR THIS DATE
        $result = $this->deleteProjectSummaryData($date_ymd);

        // CREATE NEW ENTRIES FOR THIS DATE
        if ($result) {
            $result = $this->addProjectSessions($date_dt);
            // $this->emDebug("Tried to add sessions for " . $date_ymd, $result);
        } else {
            $this->emDebug("Error with addProjectSettings");
        }

        // // UPDATE EM SETTINGS
        if ($result) {
            $this->setSystemSetting('last_date_completed', $date_ymd);
            $this->emDebug("Updating last_date_completed to $date_ymd");
        }

        return $result;
    }


    /**
     * Delete data for date
     * @param $date_dt
     * @return bool
     */
    public function deleteProjectSummaryData($date_ymd) {
        // DELETE EXISTING DATA FOR THAT DATE
        $ending = "FROM " . self::PROJECT_SESSION_TABLE . " WHERE CAST(session_start AS DATE) = '" . $date_ymd . "';";

        $sql = "SELECT count(*) $ending";
        $q = db_query($sql);
        $count = db_result($q,0);

        $this->emDebug("Delete summary found $count");

        if ($count > 0) {
            $q = db_query("DELETE " . $ending);
            $this->emDebug("Deleted $count existing rows from " . self::PROJECT_SESSION_TABLE . " for " . $date_ymd);
        }

        return true;
    }


    /**
     * Creates a data array for the specificed date
     * @param $date_dt
     * @return bool
     */
    public function addProjectSessions($date_dt) {
        $date_ymd = $date_dt->format('Y-m-d');

        // A 2-D array that has user_id => project => timestamps => ['last_time', session_count]
        $data = array();

        $api_url = APP_PATH_WEBROOT_FULL . "api/";

        $sql = sprintf("select * from redcap_log_view where CAST(ts AS DATE) = '%s'" .
            " and full_url != '%s'" .
            " and user is not null" .
            " and project_id is not null" .
            // " and user != '[survey respondent]'" .
            " ORDER BY user, project_id, ts ASC;",
            prep($date_ymd),
            prep($api_url)
        );

        $q = db_query($sql);
        $this->emDebug("Found " . db_num_rows($q) . " rows for from " . $date_ymd); // $sql);

        while ($row = db_fetch_assoc($q)) {
            $user = $row['user'];

            $candidate_ts = $row['ts'];
            $project = $row['project_id'];

            if (! $data[$user][$project]) {
                // first record for this user in this project - initialize the session using the
                // candidate_ts as the first time.
                $data[$user][$project][$candidate_ts]['session_count'] = 0;
                $data[$user][$project][$candidate_ts]['last_time'] = $candidate_ts;
            } else {
                // we have an existing session for user-project
                // Not needed but safety to make sure we are ordering by TS
                ksort($data[$user][$project]);

                // Goto the end of the array
                end($data[$user][$project] );

                $most_recent_start = key($data[$user][$project]);

                // get the last activity of this most recent session
                $last_activity = $data[$user][$project][$most_recent_start]['last_time'];

                // $this->emDebug("$user $project $most_recent_start $candidate_ts " . json_encode($last_activity));

                //check if the time elapsed since last_time is within the activity interval
                $second_diff = (strtotime($candidate_ts) - strtotime($last_activity));

                if (($second_diff > $this->activity_interval)) {
                    // gap is larger than session interval, so start another session for this user-project, and carry on
                    $data[$user][$project][$candidate_ts]['session_count'] = 0;
                    $data[$user][$project][$candidate_ts]['last_time'] = $candidate_ts;
                } else {
                    //time elapsed is less than allowed time gap, so add to current session
                    $data[$user][$project][$most_recent_start]['session_count'] += 1;
                    $data[$user][$project][$most_recent_start]['last_time'] = $candidate_ts;
                }
            }
        }

        //add to database
        $result = $this->saveToProjectDB($data);
        return $result;
    }



    /**
     * Saves project data array to SQL table
     * @param $data
     * @return bool
     */
    public function saveToProjectDB($data) {
        if (!empty($data)) {

            $params = array();
            foreach($data as $user => $projects ) {
                foreach ($projects as $project_id =>$start_times) {
                    foreach ($start_times as $ts => $session) {

                        $duration = (strtotime($session['last_time']) - strtotime($ts));

                        $params[] = sprintf("('%s','%s','%s','%s',%d,%d)",
                            $user,
                            $project_id,
                            $ts,
                            $session['last_time'],
                            $session['session_count'],
                            $duration
                        );
                     }
                 }
             }

            $this->emDebug("New Sessions", $params);

            $sql = 'INSERT INTO ' . self::PROJECT_SESSION_TABLE .
                ' (username, project_id, session_start, session_end, session_count, duration) VALUES ' .
                implode(',', $params);

            $result = db_query($sql);

            if ($result !== true) {
                $this->emDebug("ERROR IN INSERT", $result, $sql, db_error(), db_errno());
                return false;
            }
        }
        return true;
    }


    /**
     * A Cron method that is called every day to update the summary
     * @param $cron_name
     * @throws \Exception
     */
    public function daily_cron($cron_name) {

        // $this->emDebug("Daily Cron", $cron_name);
        while( $this->checkStatus() ) {
            $this->emDebug("Updating " . $this->next_date_dt->format('Y-m-d'));
        }
    }


//     function preprocessIntervalByProject($start, $end, $time_gap) {
//         $keep = array();
//
//         $sql = sprintf("select * from redcap_log_view where ts > '%s' and ts < '%s' "
//             . "and full_url != 'https://redcap.stanford.edu/api/' "
//             . "and user is not null and user != '[survey respondent]' order by ts asc;",
//             prep($start),
//             prep($end));
// 		$this->emDebug("Running: ", $sql);
//         $result = db_query($sql);
//
//
//         while ($row = db_fetch_assoc($result)) {
//             $user = $row['user'];
//             $candidate_ts = $row['ts'];
//             $project = $row['project_id'];
//
//             //get the last activity for this user - project
//             //if ($keep[$user][$project]) {
//                 if ($keep[$user][$project]) {
//                 //retrieve last timestamp from $keep array for user-project
//                 $timestamps = (array_keys($keep[$user][$project]));
//
//                 //This is the last start time
//                 $current_start = end($timestamps);
//                 $last_activity = $keep[$user][$project][$current_start]['last_time'];
//
//                 //check if the time elapsed since last_time is within the gap
//                 $second_diff = (strtotime($candidate_ts) - strtotime($last_activity));
// //                $module->emDebug($second_diff, "DEBUG", "USER: ".$user." and PROJECT: ".$project.", TIME_GAP" . $time_gap . ", current start :"
// //                    . $current_start . " for current candidate time: " . $candidate_ts . " against time of last activity " . $last_activity);
//
//                 if (($second_diff > $time_gap)) {
//                     //time_gap is larger, so start another session for this user, and carry on
//                     $keep[$user][$project][$candidate_ts]['session_count'] = 0;
//                     $keep[$user][$project][$candidate_ts]['last_time'] = $candidate_ts;
//                     continue;
//                 } else {
//                     //time elapsed is less than allowed time gap, so add to current session
//                     $keep[$user][$project][$current_start]['session_count'] += 1;
//                     $keep[$user][$project][$current_start]['last_time'] = $candidate_ts;
//                 }
//
//             } else {
//                 //first record for this user, so record it and go to next candidate
//                 $keep[$user][$project][$candidate_ts]['session_count'] = 0;
//                 $keep[$user][$project][$candidate_ts]['last_time'] = $candidate_ts;
//                 continue;
//             }
//         }
//
//         //add to database
//         $this->saveToProjectDB($keep);
//     }
//
//
//     /**
//      * While we are deciding what parameters to monitor, just keep all "sessions" in rows
//      * to analyze later.
//      *
//      * @param $start - Start of time period to analyze
//      * @param $end   - End of time period to analyze
//      * @param $time_gap  - Gap between timestamps which is considered to be within a "session"
//      * @return array
//      */
//     function preprocessInterval($start, $end, $time_gap) {
//         $keep = array();
//
//         $sql = sprintf("select * from redcap_log_view where ts > '%s' and ts < '%s' "
//             . "and full_url != 'https://redcap.stanford.edu/api/' "
//             . "and user is not null and user != '[survey respondent]' order by ts asc;",
//             prep($start),
//             prep($end));
//         $result = db_query($sql);
//
//
//
//         /**
//          * example of array from db
//          * (
//          * [log_view_id] => 47668471
//          * [ts] => 2017-10-01 00:00:07
//          * [user] => rogupta
//          * [event] => PAGE_VIEW
//          * [ip] => 171.65.163.223
//          * [browser_name] => unknown
//          * [browser_version] => unknown
//          * [full_url] => https://redcap.stanford.edu/api/
//          * [page] => api/index.php
//          * [project_id] => 8473
//          * [event_id] => NULL
//          * [record] => NULL
//          * [form_name] => NULL
//          * [miscellaneous] => // API Request: token = '1**'; content = 'instrument'; format = 'json'
//          * [session_id] => NULL
//          * )
//          */
//         while ($row = db_fetch_assoc($result)) {
//             $user = $row['user'];
//             $candidate_ts = $row['ts'];
//
//             //get last activity for this user-project
//             if ($keep[$user]) {
//                 //retrieve last timestamp from $keep array for user
//                 $timestamps = (array_keys($keep[$user]));
//
//                 //This is the last start time
//                 $current_start = end($timestamps);
//                 $last_activity = $keep[$user][$current_start]['last_time'];
//
//                 //check if the time elapsed since last_time is within the gap
//                 $second_diff = (strtotime($candidate_ts) - strtotime($last_activity));
// //                $module->emDebug($second_diff, "DEBUG", "SECOND DIFF vs  " . $time_gap . " for current start " . $current_start
// //                    . " for current candidate tim" . $candidate_ts . " against time of last activity " . $last_activity);
//
//                 if (($second_diff > $time_gap)) {
//                     //time_gap is larger, so start another session for this user, and carry on
//                     $keep[$user][$candidate_ts]['session_count'] = 0;
//                     $keep[$user][$candidate_ts]['last_time'] = $candidate_ts;
//                     continue;
//                 } else {
//                     //time elapsed is less than allowed time gap, so add to current session
//                     $keep[$user][$current_start]['session_count'] += 1;
//                     $keep[$user][$current_start]['last_time'] = $candidate_ts;
//                     //$keep[$user][$current_start]['duration'] = (strtotime($candidate_ts) - strtotime($current_start))/60;
//                 }
//
//             } else {
//                 //first record for this user, so record it and go to next candidate
//                 $keep[$user][$candidate_ts]['session_count'] = 0;
//                 $keep[$user][$candidate_ts]['last_time'] = $candidate_ts;
//                 continue;
//             }
//
//         }
//
//         //add to database:
//         $this->saveToSesssionDB($keep);
//
//         return $keep;
//     }
//
//
//
//
//     /**
//      * @param $keep
//      */
//     function saveToSesssionDB($keep) {
//         /*  these are the DB columns
//         project_id int,
//         user varchar(255),
//         ip varchar(100),
//         session_count int,
//         session_start TIMESTAMP,
//         session_end DATETIME,
//         duration int
//         */
//
//       //        $module->emDebug($keep, "DEBUG", "STATE OF KEEP");
//         $sql = array();
//         foreach( $keep as $user => $start_time ) {
//             foreach ($start_time as $ts =>$session) {
//                 $duration = (strtotime($session['last_time']) - strtotime($ts));
//                 //$module->emDebug($session, "DEBUG", "SESSION ARRay for user ".$user. " with start ".$ts . " duration ".$duration);
//                 $sql[] = sprintf("('%s','%s','%s',%d,%d)",$user,$ts,$session['last_time'],
//                     $session['session_count'],$duration);
//             }
//         }
//
//         $result = db_query('INSERT INTO stanford_session_user_summary (username, session_start, session_end, session_count, duration) VALUES '
//             .implode(',', $sql));
//     }
//


}