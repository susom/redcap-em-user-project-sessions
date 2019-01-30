<?php

$module->emDebug('------- Starting Manhours Cron -------', "INFO");

/**
 * For the nightly cron, the start and end time should be the previous day.
 */
$time_gap = 600; //number of seconds at which the session resets to start

//get yesterdays' start and end time
$last_midnight = new DateTime();
$this_midnight = new DateTime();
$last_midnight->setTimestamp(strtotime('yesterday midnight'));
$this_midnight->setTimestamp(strtotime('midnight'));

//set the interval to midnight for consistency
$start_date = $last_midnight->format('Y-m-d H:i:s');
$end_date = $this_midnight->format('Y-m-d H:i:s');
$module->emDebug("Running one day period from: " . $start_date . " to : " . $end_date);
echo "Running one day period from: " . $start_date . " to : " . $end_date;

$keep = $module->preprocessInterval($start_date, $end_date, $time_gap);

///echo "<br> GOT this KEEP back after PREPROCESS";
//echo '<br><pre>'. print_r($keep, true). '</pre>';