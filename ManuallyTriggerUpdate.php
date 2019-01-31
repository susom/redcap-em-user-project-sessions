<?php
/** @var \Stanford\UserProjectSessions\UserProjectSessions $module */


require APP_PATH_DOCROOT . "ControlCenter/header.php";


$module->emDebug("---- Running Manual Update -----");

?>

<h3><? echo $module->getModuleName() ?></h3>
<div class="card card-primary">
    <div class="card-heading"><strong>Update Progress</strong></div>
    <div class="card-body">

        <pre><?php
                while( $module->checkStatus() ) {
                    // $module->emDebug("Updating " . $module->next_date_dt->format('Y-m-d'));
                    echo "Updating " . $module->next_date_dt->format('Y-m-d') . "\n";
                }
                // // REFRESH ALL
                // // REFRESH ONE
                // $module->checkStatus();
                // $module->emDebug("Updating " . $module->next_date_dt->format('Y-m-d'));
            ?>Done</pre>

    </div>

</div>
<!--<div class="card-footer">-->
<!--    <button class="btn btn-primary" name="save" onclick="submit()">START</button>-->
<!--</div>-->

<style>
</style>

<script type = "text/javascript">
</script>
