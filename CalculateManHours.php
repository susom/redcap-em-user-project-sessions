<?php

namespace Stanford\CalculateManHours;

use \Plugin as Plugin;

class CalculateManHours extends \ExternalModules\AbstractExternalModule {


    public function getLastEnteredTime() {
        $sql = "select max(session_end)  as session_end from stanford_session_user_summary";
        $q = db_query($sql);

        $result = db_fetch_array($q);
        $max = $result['session_end'];

        Plugin::log($max, "DEBUG", "MAX VALUE");
        return $max;
    }

    public function startCron() {
        //get all projects that are enabled for this module
        $enabled = ExternalModules::getEnabledProjects($this->PREFIX);

        $url = $this->getUrl('ManHoursCron.php', false, true);

        while ($proj = db_fetch_assoc($enabled)) {
            $pid = $proj['project_id'];

            //Processing the previous day's data at 1am
            $current_hour = date('H');

            //if not 1 am , continue
            if ($current_hour != 1) continue;

            $this_url = $url . '&pid=' . $pid;


            //currently cannot make https calls locally.  In meantime, str replace with http
            if ($_SERVER['DOCUMENT_ROOT'] == "/var/redcap/prod/web") {
                $this_url = str_ireplace('https','http', $this_url);
            }
            //Plugin::log(($this_url), "URL IS ");
            $response = http_get($this_url);
            //Plugin::log($response, "DEBUG", "RESPONSE");

        }
    }


    function preprocessIntervalByProject($start, $end, $time_gap) {
        $keep = array();

        $sql = sprintf("select * from redcap_log_view where ts > '%s' and ts < '%s' "
            . "and full_url != 'https://redcap.stanford.edu/api/' "
            . "and user is not null and user != '[survey respondent]' order by ts asc;",
            prep($start),
            prep($end));
	//	Plugin::log($sql, "DEBUG", "Running this sql");
        $result = db_query($sql);


        while ($row = db_fetch_assoc($result)) {
            $user = $row['user'];
            $candidate_ts = $row['ts'];
            $project = $row['project_id'];

            //get the last activity for this user - project
            //if ($keep[$user][$project]) {
                if ($keep[$user][$project]) {
                //retrieve last timestamp from $keep array for user-project
                $timestamps = (array_keys($keep[$user][$project]));

                //This is the last start time
                $current_start = end($timestamps);
                $last_activity = $keep[$user][$project][$current_start]['last_time'];

                //check if the time elapsed since last_time is within the gap
                $second_diff = (strtotime($candidate_ts) - strtotime($last_activity));
//                Plugin::log($second_diff, "DEBUG", "USER: ".$user." and PROJECT: ".$project.", TIME_GAP" . $time_gap . ", current start :"
//                    . $current_start . " for current candidate time: " . $candidate_ts . " against time of last activity " . $last_activity);

                if (($second_diff > $time_gap)) {
                    //time_gap is larger, so start another session for this user, and carry on
                    $keep[$user][$project][$candidate_ts]['session_count'] = 0;
                    $keep[$user][$project][$candidate_ts]['last_time'] = $candidate_ts;
                    continue;
                } else {
                    //time elapsed is less than allowed time gap, so add to current session
                    $keep[$user][$project][$current_start]['session_count'] += 1;
                    $keep[$user][$project][$current_start]['last_time'] = $candidate_ts;
                }

            } else {
                //first record for this user, so record it and go to next candidate
                $keep[$user][$project][$candidate_ts]['session_count'] = 0;
                $keep[$user][$project][$candidate_ts]['last_time'] = $candidate_ts;
                continue;
            }
        }

        //add to database
        $this->saveToProjectDB($keep);
    }


    /**
     * While we are deciding what parameters to monitor, just keep all "sessions" in rows
     * to analyze later.
     *
     * @param $start - Start of time period to analyze
     * @param $end   - End of time period to analyze
     * @param $time_gap  - Gap between timestamps which is considered to be within a "session"
     * @return array
     */
    function preprocessInterval($start, $end, $time_gap) {
        $keep = array();

        $sql = sprintf("select * from redcap_log_view where ts > '%s' and ts < '%s' "
            . "and full_url != 'https://redcap.stanford.edu/api/' "
            . "and user is not null and user != '[survey respondent]' order by ts asc;",
            prep($start),
            prep($end));
        $result = db_query($sql);



        /**
         * example of array from db
         * (
         * [log_view_id] => 47668471
         * [ts] => 2017-10-01 00:00:07
         * [user] => rogupta
         * [event] => PAGE_VIEW
         * [ip] => 171.65.163.223
         * [browser_name] => unknown
         * [browser_version] => unknown
         * [full_url] => https://redcap.stanford.edu/api/
         * [page] => api/index.php
         * [project_id] => 8473
         * [event_id] => NULL
         * [record] => NULL
         * [form_name] => NULL
         * [miscellaneous] => // API Request: token = '1**'; content = 'instrument'; format = 'json'
         * [session_id] => NULL
         * )
         */
        while ($row = db_fetch_assoc($result)) {
            $user = $row['user'];
            $candidate_ts = $row['ts'];

            //get last activity for this user-project
            if ($keep[$user]) {
                //retrieve last timestamp from $keep array for user
                $timestamps = (array_keys($keep[$user]));

                //This is the last start time
                $current_start = end($timestamps);
                $last_activity = $keep[$user][$current_start]['last_time'];

                //check if the time elapsed since last_time is within the gap
                $second_diff = (strtotime($candidate_ts) - strtotime($last_activity));
//                Plugin::log($second_diff, "DEBUG", "SECOND DIFF vs  " . $time_gap . " for current start " . $current_start
//                    . " for current candidate tim" . $candidate_ts . " against time of last activity " . $last_activity);

                if (($second_diff > $time_gap)) {
                    //time_gap is larger, so start another session for this user, and carry on
                    $keep[$user][$candidate_ts]['session_count'] = 0;
                    $keep[$user][$candidate_ts]['last_time'] = $candidate_ts;
                    continue;
                } else {
                    //time elapsed is less than allowed time gap, so add to current session
                    $keep[$user][$current_start]['session_count'] += 1;
                    $keep[$user][$current_start]['last_time'] = $candidate_ts;
                    //$keep[$user][$current_start]['duration'] = (strtotime($candidate_ts) - strtotime($current_start))/60;
                }

            } else {
                //first record for this user, so record it and go to next candidate
                $keep[$user][$candidate_ts]['session_count'] = 0;
                $keep[$user][$candidate_ts]['last_time'] = $candidate_ts;
                continue;
            }

        }

        //add to database:
        $this->saveToSesssionDB($keep);

        return $keep;
    }



     function saveToProjectDB($keep) {
       //       Plugin::log($keep, "DEBUG", "STATE OF KEEP TO add to Project DB");
         $sql = array();
         foreach( $keep as $user => $project ) {
             foreach ($project as $project_id =>$start_time) {
                 foreach ($start_time as $ts => $session) {
                     $duration = (strtotime($session['last_time']) - strtotime($ts));
                     //Plugin::log($session, "DEBUG", "SESSION ARRay for user ".$user. " with start ".$ts . " duration ".$duration);
                     $sql[] = sprintf("('%s','%s','%s',%d,%d, '%s')", $user, $ts, $session['last_time'],
                         $session['session_count'], $duration, $project_id);
                 }
             }
         }

         $result = db_query('INSERT INTO stanford_session_project_summary (username, session_start, session_end, session_count, duration, project_id) VALUES '
             .implode(',', $sql));
	 Plugin::log($result, "DEBUG", "RESULT OF QUERY");

    }

    /**
     * @param $keep
     */
    function saveToSesssionDB($keep) {
        /*  these are the DB columns
        project_id int,
        user varchar(255),
        ip varchar(100),
        session_count int,
        session_start TIMESTAMP,
        session_end DATETIME,
        duration int
        */

      //        Plugin::log($keep, "DEBUG", "STATE OF KEEP");
        $sql = array();
        foreach( $keep as $user => $start_time ) {
            foreach ($start_time as $ts =>$session) {
                $duration = (strtotime($session['last_time']) - strtotime($ts));
                //Plugin::log($session, "DEBUG", "SESSION ARRay for user ".$user. " with start ".$ts . " duration ".$duration);
                $sql[] = sprintf("('%s','%s','%s',%d,%d)",$user,$ts,$session['last_time'],
                    $session['session_count'],$duration);
            }
        }

        $result = db_query('INSERT INTO stanford_session_user_summary (username, session_start, session_end, session_count, duration) VALUES '
            .implode(',', $sql));
    }



}