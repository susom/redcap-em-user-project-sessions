<?php
/** @var \Stanford\UserProjectSessions\UserProjectSessions $module */

require APP_PATH_DOCROOT . "ControlCenter/header.php";


$module->emDebug("---- Global Usage Report -----");


echo $module->getUrl("api/metrics.php",true,true);

?>

<h3><? echo $module->getModuleName() ?></h3>
<h4> Global Usage Report </h4>




<style>
</style>

<script type = "text/javascript">
</script>
