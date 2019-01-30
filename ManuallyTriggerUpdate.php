<?php
/** @var \Stanford\CalculateManHours\CalculateManHours $module */

// REFRESH ALL
while( $this->checkStatus() ) {
    $this->emDebug("Updating " . $this->next_date_dt->format('Y-m-d'));
}

exit();

// TODO: Make refresh for a given project



$begin = new DateTime('2018-09-01');
$end = new DateTime('2018-09-31');
$time_gap = 600; //number of seconds at which the session resets to start




//check the session table to see the last time recorded?
$max = $module->getLastEnteredTime();

if (!empty($_POST['action'])) {
    $action = $_POST['action'];

    switch ($action) {
        case "save":

            $begin = new DateTime($_POST['date_start']);
            $end = new DateTime($_POST['date_end']);

            startSession($begin, $end, $time_gap);

            $result = array('result' => 'success');

            header('Content-Type: application/json');
            print json_encode($result);
            exit();
            break;
        default:
            $module->emDebug($_POST, "Unknown Action in Save");
            print "Unknown action";
    }
}

function startSession($begin, $end, $time_gap) {
    global $module;

    $interval = DateInterval::createFromDateString('1 day');

    // Go day by day from begin date to end date
    $period = new DatePeriod($begin, $interval, $end);
    foreach ($period as $dt) {
        // Convert interval into string
        $start_date = $dt->format("Y-m-d H:i:s");
        $end_date   = $dt->add($interval)->format("Y-m-d H:i:s");
        $module->emDebug("PreprocessByProject: Running one day period from: " . $start_date . " to : " . $end_date);

        // $end_date = date('Y-m-d H:i:s', strtotime('+1 day', strtotime($start_date)));

        //$keep = $module->preprocessInterval($end_date, $start_date, $time_gap);
        $keep = $module->preprocessIntervalByProject($start_date, $end_date, $time_gap);

    }
}

//echo "<br> GOT this KEEP back after PREPROCESS";

// Initialize the Page
if ($context == "project") {
    $panel_title = $module->getModuleName() . " Configuration for Project " . $project_id;
    require_once APP_PATH_DOCROOT . 'ProjectGeneral/header.php';
} else {
    $panel_title = $module->getModuleName() ;
    $objHtmlPage = new HtmlPage();
    $objHtmlPage->addExternalJS(APP_PATH_JS . "base.js");
    $objHtmlPage->addStylesheet("jquery-ui.min.css", 'screen,print');
    $objHtmlPage->addStylesheet("style.css", 'screen,print');
    $objHtmlPage->addStylesheet("home.css", 'screen,print');
    $objHtmlPage->PrintHeader();
}


?>

<!--<script src="https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.22.1/moment.js"></script>-->
<!--<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-datetimepicker/4.7.14/js/bootstrap-datetimepicker.min.js"></script>-->
<!--<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-datetimepicker/4.7.14/css/bootstrap-datetimepicker.min.css">-->


<!--
<script type="text/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.4.1/js/bootstrap-datepicker.min.js"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.4.1/css/bootstrap-datepicker3.css"/>
-->

<div class="panel panel-primary">
    <div class="panel-heading"><strong><?php echo $panel_title ?></strong></div>
    <div class="panel-body">
        <span id="foo"></span><br>


        <br>Allowed interval time between entries is <?php echo $time_gap; ?> seconds.
        <br>


        <div class="container">
            <div class='col-md-5'>
                <div class="form-group">
                    <label>START</label>
                    <div class='input-group date'>
                        <input name='date-start' type='text' class="form-control" placeholder="YYYY-MM-DD" value="2018-09-01" />
                        <span class="input-group-addon">
                    <span class="glyphicon glyphicon-calendar"></span>
                </span>
                    </div>
                </div>
            </div>
            <div class='col-md-5'>
                <div class="form-group">
                    <label>END</label>
                    <div class='input-group date'>
                        <input name='date-end' type='text' class="form-control" placeholder="YYYY-MM-DD" value="2018-09-31"/>
                        <span class="input-group-addon">
                    <span class="glyphicon glyphicon-calendar"></span>
                </span>
                    </div>
                </div>
            </div>
        </div>

    </div>

</div>

<div class="panel-footer">
    <button class="btn btn-primary" name="save" onclick="submit()">START</button>
</div>





<style>
    #pagecontent { margin-top: 5px; }
    .config-editor { border-bottom: 1px solid #ddd; padding:0;}
</style>


<script type = "text/javascript">

    $(document).ready(function(){


        var max = "<?php echo $max ?>";
        console.log("max is ".max);
        $('#foo').text("Last entry was on '<?php echo $max ?>'");
    });




    function submit () {
        var saveBtn = $('button[name="save"]');
        var saveBtnHtml = saveBtn.html();
        var dateStart = $('input[name="date-start"]').val();
        var dateEnd = $('input[name="date-end"]').val();

        console.log("start: "+dateStart);
        console.log("end: "+dateEnd);

            var data = {
                "action": "save",
                "date_start" : dateStart,
                "date_end" : dateEnd
            };
            saveBtn.html('<img src="'+app_path_images+'progress_circle.gif"> Running...');
            $.ajax({
                method: 'POST',
                data: data,
                dataType: "json"
            })
                .done(function (data) {
                    if (data.result === 'success') {

                    } else {
                        // an error occurred
                        alert("Unable to run<br><br>" + data.message, "ERROR - SAVE FAILURE" );
                    }

                })
                .fail(function (data) {
                    console.log(data.responseText);
                    alert(data.responseText);
                })
                .always(function() {
                    saveBtn.html(saveBtnHtml);
                    saveBtn.prop('disabled',false);
                });
        };
</script>
