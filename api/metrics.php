<?php
namespace Stanford\UserProjectSessions;
/** @var UserProjectSessions $module */


/**
 * This is called as an ajax services from the google data studio to report metrics on REDCap
 */
use \System;

$shared_secret_token = $module->getSystemSetting('shared_secret_token');
$token = @$_GET['token'];

if (empty($shared_secret_token)) {
    exit("Security token not configured");
}

if (empty($token)) {
    $module->emDebug("Missing token");
    exit("Improper API request");
}

if ($token != $shared_secret_token) {
    $module->emDebug("Invalid token: $token");
    exit("Improper authentication - this has been logged.");
}


$report = @$_GET['report'];

switch($report) {
    case "userProjectSessions":
        $year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');
        System::increaseMemory(4096);
        $sql = "select * from em_user_project_sessions where year(session_start) = $year";
        $q = db_query($sql);
        $data = [];
        while ($row = db_fetch_assoc($q)) {
            $data[] = $row;
        }
        break;

    case "projects":
        $sql = '
select
  CASE
    WHEN status = 0 THEN "Development"
    WHEN status = 1 THEN "Production"
    WHEN status = 2 THEN "Inactive"
    WHEN status = 3 THEN "Archived"
    ELSE NULL
  END as Status,
  Count
from
     (select status,
             count(*) as Count
      from redcap_projects
      group by status
     ) Raw';
        $q = db_query($sql);
        $data = [];
        while ($row = db_fetch_assoc($q)) {
            $data[] = $row;
        }
        break;


    default:
        $module->emDebug("Invalid Report Option: " . $report);
}

if (!empty($data)) {
    header("Content-Type: application/json");
    echo json_encode($data);
}
