<?php

Plugin::log('------- Starting Manhours Cron -------', "INFO");
Plugin::log($project_id, 'PID');

//pick up the configs

$time_gap = 600; //number of seconds at which the session resets to start

$start = date('Y-m-d H:i:s');

//$increment =  $this->getProjectSetting('num-hours', $project_id);
$increment = 14;

$enddate = date('Y-m-d H:i:s');
//$enddate = strtotime ( '+'.$increment.' day' , strtotime ($start) ) ;
$backdate = strtotime ( '-'.$increment.' day' , strtotime ($enddate ) ) ;
$backdate = date ( 'Y-m-d H:i:s' , $backdate );


$last_midnight = new DateTime('2018-04-01');
$this_midnight = new DateTime('2018-04-02');

$last_midnight->setTime(0,0,0);
$this_midnight->setTime(0,0,0);
$start_date = $last_midnight->format('Y-m-d H:i:s');
$end_date = $this_midnight->format('Y-m-d H:i:s');
Plugin::log($last_midnight, "DEBUG", "LAST MIDNIGHT");
Plugin::log($this_midnight, "DEBUG", "THIS MIDNIGHT");
Plugin::log($start_date, "DEBUG", "LAST MIDNIGHT");
Plugin::log($end_date, "DEBUG", "THIS MIDNIGHT");

echo "<br> examining: START: ".$start_date;
echo "<br> examining: END: ".$end_date;


//$keep = $module->preprocessMonth($backdate, $enddate, $time_gap);
$keep = $module->preprocessMonth($start_date, $end_date, $time_gap);

echo "<br> GOT this KEEP back after PREPROCESS";
echo '<br><pre>'. print_r($keep, true). '</pre>';