<?php
/**
  Plugin Name: WP EdReports
  Description: EdReports API Integration
  Version: 0.0.1
  Author: David Hefley, Nebraska Department of Education
 */
define('NDE_EDREPORTS_VERSION', '0.1.9');
define('NDE_EDREPORTS_PATH', plugin_dir_path(__FILE__));
define('NDE_EDREPORTS_URI', plugins_url('wpedreports'));

require NDE_EDREPORTS_PATH . 'NDE/EdReportAPI.php';
require NDE_EDREPORTS_PATH . 'NDE/WPHelper.php';

//use NDE;
//TODO: Get these from an options screen:


$wpHelper = new NDE\WPHelper();

//$wpHelper->getAllData();
//$edReports->cacheRefresh( isset($_GET['apiforce']) );

function debugAPI() {
    global $wpHelper;
    ob_start();
    $wpHelper->cacheRefresh(true);
    $data = $wpHelper->report_types();
    echo "<pre>";
    print_r($data);
    echo "</pre>";
    //$edReports->reports(2);
    //$edReports->reportsDetail(2);
    //$edReports->reportTypes();
    return ob_get_clean();
}

/* [2] => stdClass Object
  (
  [title] => Mirrors & Windows: Connecting with Literature - Grade 9
  [report_date] => 2018-06-11
  [subject_taxonomy_id] => 27
  [grade_taxonomy_id] => 51
  [report_type] => ela-9-12
  )

 * 
 */

//add_filter('the_content', 'debugAPI');

/**
 * TODO: actual show real reports
 * @global NDE\WPHelper $wpHelper
 */
function edreportnextpage_func() {
    $subject = isset($_GET['subject']) ? (int) $_GET['subject'] : '';
    $page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
    $perpage = isset($_GET['perpage']) ? (int) $_GET['perpage'] : 10;
    $text = isset($_GET['textsearch']) ? sanitize_text_field($_GET['textsearch']) : '';
    $grades = isset($_GET['grades']) ? $_GET['grades'] : [];
    global $wpHelper;
    $wpHelper->queryStart(($perpage * $page));
    $wpHelper->queryLimit($perpage);
    $series = $wpHelper->series($subject, $grades, $text);
    if (!empty($series)) :
        foreach ($series as $s) :
            format_edseries($s);
        endforeach;
    else:
        if ( $page == 1 ) echo "<div class='noseriesfound'>No Materials Found</div>";
    endif;
    die();
}

add_action('wp_ajax_edreportnextpage', 'edreportnextpage_func');
add_action('wp_ajax_nopriv_edreportnextpage', 'edreportnextpage_func');

function edrep_filter_bar($atts = [], $content = '') {
    $atts = shortcode_atts(array(
        'subject' => '27', //5->MATH, 27->ELA (but should get from db instead)                
            ), $atts, 'edrep_listing');
    extract($atts);
    ob_start();
    ?>
    <form id='edreportFilterForm'>
        <div class='edreport_filter'>
            <div class='filtertitle'>
                <h3>Filter Results</h3>
            </div>
            <div class='filterbody'>
                <div class='row'>
                    <div class='col-xs-12 col-sm-7'>
                        <div role="group" aria-label="Show Reviews For Grade">
                            <?php
                            switch ($subject) {
                                case '5':
                                    $limit = 9;
                                    $include_hs = true;
                                    break;
                                default: $limit = 13;
                                    $include_hs = false;
                            }
                            for ($i = 0; $i < $limit; $i++) :
                                ?>
                                <label>
                                    <div class='fakeCheckBox' data-val='<?= $i; ?>' ><?= ($i == '0') ? 'K' : $i; ?></div>
                                    <!-- input type='checkbox' name='grades[]' value='<?= $i; ?>' checked='CHECKED' / -->
                                </label>
                                <?php
                            endfor;

                            if ($include_hs) :
                                ?>
                                <label>
                                    <div class='fakeCheckBox' title='High School' data-val='hs' alt='High School'>HS</div>
                                    <!-- input type='checkbox' name='grades[]' value='hs' checked='CHECKED' / -->
                                </label>
                            <?php endif ?>     
                            <label>
                                <div class='gradeToggle'>Toggle Selection</div>
                            </label>
                        </div>
                    </div>
                    <div class='col-xs-12 col-sm-5 text-right'>
                        <div class='search-filter'>
                            <input type="text" class="button" value="" id="q" name='textsearch' placeholder="Search Titles">
                        </div>
                    </div>
                </div>
                <div class='row'>
                    <div class='col-xs-12 col-sm-12 text-danger text-center' id='filterMessage' style='display:none;'>
                        All grade levels will be shown.
                    </div>
                </div>
            </div>
        </div>
    </form>    
    <?php
    return ob_get_clean();
}

add_shortcode('edrep_filter_bar', 'edrep_filter_bar');

function edrep_listing($atts, $content = '') {
    $atts = shortcode_atts(array(
        'subject' => '27', //5->MATH, 27->ELA (but should get from db instead)        
        'perpage' => 10
            ), $atts, 'edrep_listing');
    extract($atts);
    ob_start();
    global $wpHelper;
    $wpHelper->queryLimit($perpage);
    $series = $wpHelper->series($subject);

    $report_intervals = $wpHelper->reportIntervals();
    $imagePath = NDE_EDREPORTS_URI . '/assets/images/';
    wp_localize_script('nde_edreports', 'edrep', ['ajaxurl' => admin_url('admin-ajax.php'), 'subject' => (int) $subject, 'perpage' => (int) $perpage, 'page' => 1, 'intervals' => $report_intervals, 'images' => $imagePath]);
    if (!empty($series)) :
        ?>
        <div id="edReportHolder" style="opacity:0"><?php
            foreach ($series as $s) :
                format_edseries($s);
            endforeach;
            ?>
        </div>
        <div class='loadMore text-center'>
            <button type='button' class='ndebutton loadmorereports'>Load More Reviews...</button>
        </div>
        <?php
    else: echo "<div class='noseriesfound'>No Materials Found</div>";
    endif;

    add_action('wp_footer', 'inject_edreport_modal');

    return ob_get_clean();
}

add_shortcode('edrep_listing', 'edrep_listing');

function inject_edreport_modal() {
    ?>
    <div class="popup-container" style="" id="edreportdetails">
        <div class="popup">
            <p class="close" data-dismiss="modal" aria-label="Close">
                <a href="javascript:void(0)"><i class="fas fa-times"></i></a>
            </p>
            <h3 class='title_grade'>
                <span class='title'></span><br>
                <span class='grade'></span>
            </h3>
            <div class="reports-scroll">
                <!-- scroll -->
                <div class="det">
                    <div>
                        <div class="popup-report">
                            <fieldset class="partially-meets">
                                <legend>Alignment</legend>
                                <div class="scales">
                                    <a href="#" class="half ga-processed gw-1" target="_blank">                    
                                        <p class="ttl">&nbsp;</p>
                                        <div class="scale expectations ">
                                            <ul class='animated fadeInLeft' style=''></ul>
                                        </div>
                                        <div class="scale indices">
                                            <ul></ul>
                                        </div>
                                    </a>							
                                    <a href="#" class="half ga-processed gw-2" target="_blank">                    
                                        <p class="ttl">&nbsp;</p>
                                        <div class="scale expectations ">
                                            <ul class='animated fadeInLeft' style=''></ul>
                                        </div>
                                        <div class="scale indices">
                                            <ul></ul>
                                        </div>
                                    </a>		               
                                </div>

                                <div class="status">
                                    <div>
                                        <span></span>
                                    </div>                
                                </div>
                            </fieldset>
                            <fieldset class="did-not-review">
                                <legend>Usability</legend>
                                <div class="scales">
                                    <a href="#" target="_blank" class="ga-processed gw-3">                    
                                        <p class="ttl">&nbsp;</p>
                                        <div class="scale expectations tooltipster tooltipstered">
                                            <ul class='animated fadeInLeft' style=''></ul>
                                        </div>
                                        <div class="scale indices">
                                            <ul></ul>
                                        </div>
                                    </a>		                
                                </div>

                                <div class="status">

                                    <div>
                                        <span></span>
                                        <a href="#" class="ndetooltip" onclick="return false;" title="This material was not reviewed for Gateway Three because it did not meet expectations for Gateways One and Two">
                                            <span class='fas fa-info-circle'></span>
                                        </a>
                                    </div>
                                </div>
                            </fieldset>
                        </div>
                    </div> 
                </div>

            </div>
            <p class="view"><a href="" target="_blank" class="ga-processed">View full report on EdReports.org </a></p>
        </div>
    </div>
    <div class="ndemodaler" style='display:none;'></div>
    <?php
}

function format_edseries($d) {

    global $wpHelper;
    $full = $wpHelper->seriesDetails($d->id);
    ?>
    <div class='edrep_review' id='edrep_<?= $d->id; ?>'>
        <div class='edrep_title'>
            <h2>
                <?= $d->title; ?>                
            </h2>
            <span><?= $d->publisher; ?> | <?= $d->grades_description; ?><?php if (!empty($d->edition)) { ?> | <?= $d->edition; ?> Edition<?php } ?></span>
        </div>
        <div class='edrep_body'>
            <div class='row'>
                <div class='col-xs-12 col-sm-5 col-md-3'>
                    <div class='edreplink'>
                        <div>
                            <img src="<?= NDE_EDREPORTS_URI; ?>/assets/images/logo-edreports.jpg" alt=""/>
                        </div>
                        <div>
                            Go to <a href="<?= $full->series_url; ?>" target="_blank">EdReports.org</a> for detailed information about alignment and usability
                        </div>
                    </div>
                </div>
                <?php
                $seriesReports = $wpHelper->seriesReports($d->id);                
                $sts = count($seriesReports);
                if ($sts === 0 || $sts === 1)
                    $sts = 1;
                else
                    $sts = 3;
                ?>

                <div class='col-xs-12 col-sm-7 col-md-9'>
                    <div class='reports'  data-slick='{"slidesToShow": <?= $sts; ?>, "slidesToScroll":<?= $sts; ?>}'>
                        <?php
                        //$status = ['does-not-meet', 'meets', 'partially-meets'];
                        if (!empty($seriesReports))
                            foreach ($seriesReports as $report) :
                                $data = unserialize(base64_decode($report->data));
                                
                                
                                if ( empty($data) ) return;
                                if ($data->gateway_1_rating == 'meets' && $data->gateway_2_rating == 'meets' && $data->gateway_3_rating == 'meets') {
                                    $status = 'meets';
                                } else {
                                    if ($data->gateway_1_rating == 'partially-meets' || $data->gateway_2_rating == 'partially-meets' || $data->gateway_3_rating == 'partially-meets') {
                                        $status = 'partially-meets';
                                    }
                                    if ($data->gateway_1_rating == 'does-not-meet' || $data->gateway_2_rating == 'does-not-meet' || $data->gateway_3_rating == 'does-not-meet') {
                                        $status = 'does-not-meet';
                                    }
                                }
                                //$data->gateway_2_rating;
                                //$data->gateway_3_rating;
                                ?>
                                <div class="report <?= $status; ?>" id='report_<?= $report->id; ?>' data-id='<?= $report->id; ?>' >
                                    <?php /*
                                    <div style='display:none'>
                                        <?php print_r($data); ?>
                                    </div>
                                     * 
                                     */?>
                                    <p class="report-detail">
                                        <a href="<?= str_replace(['//api.', '//st.'],'//',$data->report_url); ?>" tabindex="0" target="_blank" class="ga-processed" title='View Full Report on EdReports.org'><?= $report->description; ?></a>
                                    </p>
                                    <div class="report-descr">
                                        
                                        <?php if (empty($data)): ?>
                                            <a href='javascript:void(0)'>
                                                <div>
                                                    <span class="circle"></span>
                                                </div>
                                                <div>
                                                    Data not yet available.
                                                </div>
                                            </a>
                                        <?php else: ?>
                                        
                                            <a href="<?= $data->report_url; ?>" tabindex="0" target="_blank" class="ga-processed">
                                                <div>
                                                    <span class="circle <?= $status; ?>"><span class='fas fa-<?php
                                                        switch ($status) :
                                                            case 'meets': echo 'check';
                                                                break;
                                                            case 'partially-meets': echo 'exclamation-triangle';
                                                                break;
                                                            case 'does-not-meet': echo '';
                                                                break;
                                                            default: echo $s;
                                                        endswitch;
                                                        ?>'></span>
                                                    </span>
                                                </div>
                                            </a>
                                        
                                            <a href="<?= $data->report_url; ?>" tabindex="0" target="_blank" class="ga-processed alignment-status-text">
                                                <div>
                                                    <?php
                                                    switch ($status) :
                                                        case 'meets': echo 'Meets expectations for alignment.';
                                                            break;
                                                        case 'partially-meets': echo 'Partially meets expectations for alignment.';
                                                            break;
                                                        case 'does-not-meet': echo 'Does not meet expectations for alignment.';
                                                            break;
                                                        default: echo $s;
                                                    endswitch;
                                                    ?> <span class="iamalink">Learn more</span>
                                                </div> 
                                            </a>

                                        <?php endif; ?>
                                    </div>
                                    
                                    
                                    
                                    <p class="report-link" title='View Report Gateway Scores'>
                                        <?php
                                        $repdata = [
                                            'title' => $d->title,
                                            'url' => $data->report_url,
                                            'grade' => $report->description,
                                            'type' => $report->report_type
                                        ];
                                        $repdata['gw_1'] = [
                                            'score' => (isset($data->gateway_1_points) ? $data->gateway_1_points : '0'),
                                            'rating' => (isset($data->gateway_1_rating) ? $data->gateway_1_rating : '-')
                                        ];
                                        $repdata['gw_2'] = [
                                            'score' => (isset($data->gateway_2_points) ? $data->gateway_2_points : '0'),
                                            'rating' => (isset($data->gateway_2_rating) ? $data->gateway_2_rating : '-')
                                        ];
                                        
                                        $repdata['gw_3'] = [
                                            'score' => (isset($data->gateway_3_points) ? $data->gateway_3_points : '0' ),
                                            'rating' => (isset($data->gateway_3_rating) ? $data->gateway_3_rating : '-')
                                        ];                                        
                                        ?>
                                        <a href="javascript:void(0);" data-series-id="<?= $d->id; ?>" tabindex="0" data-report='<?= json_encode($repdata); ?>'>REPORT BREAKDOWN</a>
                                    </p>
                                    <?php /* */ ?>
                                    <div style="display:none;">                                        
                                        <pre>
                                            <?= print_r($repdata); ?>
                                            <?= print_r($data); ?>
                                        </pre>
                                    </div>
                                    <?php /* */ ?>
                                </div>                        
                            <?php endforeach; ?>
                    </div>
                </div>
            </div>


        </div>
        <div class='edrep_footer'>
            <!-- button type='button'>
                + COMPARE
            </button -->
        </div>
    </div>

    <?php
}

function enqueueStylesScripts() {
    if (is_admin())
        return;

    wp_enqueue_style('nde-animate', '//cdn.jsdelivr.net/npm/animate.css@3.5.2/animate.min.css');
    wp_enqueue_style('nde-bootstrap', '//stackpath.bootstrapcdn.com/bootstrap/4.1.1/css/bootstrap.min.css');
    wp_enqueue_script('nde-popper', '//unpkg.com/popper.js/dist/umd/popper.min.js', array(), '1.0.0', true);
    wp_enqueue_script('nde-bootstrap', '//stackpath.bootstrapcdn.com/bootstrap/4.1.1/js/bootstrap.min.js', array('jquery'), '1.0.0', true);


    wp_enqueue_style('fontawesome', '//use.fontawesome.com/releases/v5.1.0/css/all.css');

    wp_enqueue_style('nde-edreports', NDE_EDREPORTS_URI . '/assets/css/nde_edreports.css');
    wp_enqueue_style('slick-slider', NDE_EDREPORTS_URI . '/assets/css/slick.css');
    wp_enqueue_style('slick-slider-theme', NDE_EDREPORTS_URI . '/assets/css/slick-theme.css');
    wp_enqueue_script('slick-slider', NDE_EDREPORTS_URI . '/assets/js/slick.js', array(), '1.0.0', true);
    wp_enqueue_script('nde_edreports', NDE_EDREPORTS_URI . '/assets/js/nde_edreports.js', array('jquery'), '1.0.0', true);
}

function wpedreports_admin_notice__nuked() {
    ?>
    <div class="notice notice-warning">
        <p>The EDReports Data has been cleaned!</p>
    </div>
    <?php
}

function wpedreports_admin_notice__nuke_failed() {
    ?>
    <div class="notice notice-error">
        <p>Clearing of EdReports data encountered unexpected problems!!</p>
    </div>
    <?php
}

function wpedreports_init() {
    enqueueStylesScripts();
    if (is_admin()) {        
        if (isset($_POST['edreports_manual_update'])) {
            $function = $_POST['edreports_manual_update'];
            $limit = !empty( (int)$_POST['limit']) ? (int) $_POST['limit'] : 5;
            switch ($function) {
                case 'grades': nde_edreports_datapull_grades_func();
                    $results='noupdates';
                    break;
                case 'subjects': nde_edreports_datapull_subjects_func();
                    $results='noupdates';
                    break;
                case 'publishers': nde_edreports_datapull_publishers_func($limit);                    
                    break;
                case 'reports': nde_edreports_datapull_reports_func();
                    $results='noupdates';
                    break;
                case 'reportsdetails': $results = nde_edreports_datapull_reports_details_func($limit);                    
                    break;
                case 'types': nde_edreports_datapull_reporttypes_func();
                    $results='noupdates';
                    break;
                case 'typedetails': nde_edreports_datapull_reporttypes_details_func();
                    $results='noupdates';
                    break;
                case 'series': nde_edreports_datapull_series_func($limit);
                    $results='noupdates';
                    break;
                case 'seriesdetails': $results = nde_edreports_datapull_series_details_func($limit);
                    break;
            }
            
            
            
            if ( isset($_POST['loop']) && $_POST['loop']=="1" ) {
                if ( !isset($results) || $results!='noupdates') {
                ?>
                Reloading...
                <script>
                    location.reload();
                </script>
                <?php
                }
            }
            
            die('FINISHED');
        }


        if (isset($_REQUEST['nukeEdReports']) && $_REQUEST['nukepassphrase'] == 'NUKEEDREPORTS') :
            $wpHelper = new NDE\WPHelper();
            $result = $wpHelper->nukeEdReportsData();
            if ($result)
                add_action('admin_notices', 'wpedreports_admin_notice__nuked');
            else
                add_action('admin_notices', 'wpedreports_admin_notice__nuke_failed');
        endif;
    }
}

add_action('init', 'wpedreports_init');

function refreshEdreportsData($target) {
    global $wpHelper;

    $wpHelper->debugLog('Retrieving API Data');
}

register_activation_hook(__FILE__, 'nde_edreports_activation');

function nde_edreports_activation() {
    if (!wp_next_scheduled('nde_edreports_datapull_series')) {
        wp_schedule_event(time(), 'hourly', 'nde_edreports_datapull_series');
    }
    if (!wp_next_scheduled('nde_edreports_datapull_series_details')) {
        wp_schedule_event(time(), 'tenminutes', 'nde_edreports_datapull_series_details');
    }
    if (!wp_next_scheduled('nde_edreports_datapull_reports')) {
        wp_schedule_event(time(), 'hourly', 'nde_edreports_datapull_reports');
    }
    if (!wp_next_scheduled('nde_edreports_datapull_reports_details')) {
        wp_schedule_event(time(), 'tenminutes', 'nde_edreports_datapull_reports_details');
    }
    if (!wp_next_scheduled('nde_edreports_datapull_publisher')) {
        wp_schedule_event(time(), 'hourly', 'nde_edreports_datapull_publisher');
    }
    if (!wp_next_scheduled('nde_edreports_datapull_grades')) {
        wp_schedule_event(time(), 'daily', 'nde_edreports_datapull_grades');
    }
    if (!wp_next_scheduled('nde_edreports_datapull_subjects')) {
        wp_schedule_event(time(), 'daily', 'nde_edreports_datapull_subjects');
    }
    if (!wp_next_scheduled('nde_edreports_datapull_reporttypes')) {
        wp_schedule_event(time(), 'daily', 'nde_edreports_datapull_reporttypes');
    }
    if (!wp_next_scheduled('nde_edreports_datapull_reporttypes_details')) {
        wp_schedule_event(time(), 'daily', 'nde_edreports_datapull_reporttypes_details');
    }
}

register_deactivation_hook(__FILE__, 'nde_edreports_deactivation');

function nde_edreports_deactivation() {
    wp_clear_scheduled_hook('nde_edreports_datapull_series');
    wp_clear_scheduled_hook('nde_edreports_datapull_series_details');
    wp_clear_scheduled_hook('nde_edreports_datapull_reports');
    wp_clear_scheduled_hook('nde_edreports_datapull_reports_details');
    wp_clear_scheduled_hook('nde_edreports_datapull_publisher');
    wp_clear_scheduled_hook('nde_edreports_datapull_grades');
    wp_clear_scheduled_hook('nde_edreports_datapull_subjects');
    wp_clear_scheduled_hook('nde_edreports_datapull_reporttypes');
    wp_clear_scheduled_hook('nde_edreports_datapull_reporttypes_details');
}

add_action('nde_edreports_datapull_series', 'nde_edreports_datapull_series_func');
add_action('nde_edreports_datapull_series_details', 'nde_edreports_datapull_series_details_func');
add_action('nde_edreports_datapull_reports', 'nde_edreports_datapull_reports_func');
add_action('nde_edreports_datapull_reports_details', 'nde_edreports_datapull_reports_details_func');
add_action('nde_edreports_datapull_publisher', 'nde_edreports_datapull_publishers_func');
add_action('nde_edreports_datapull_grades', 'nde_edreports_datapull_grades_func');
add_action('nde_edreports_datapull_subjects', 'nde_edreports_datapull_subjects_func');
add_action('nde_edreports_datapull_reporttypes', 'nde_edreports_datapull_reporttypes_func');
add_action('nde_edreports_datapull_reporttypes_details', 'nde_edreports_datapull_reporttypes_details_func');

function nde_edreports_datapull_series_func($limit=100) {
    error_log('Running ' . __FUNCTION__);
    if ( defined( 'DOING_CRON' ) ) $debug=1;
    else $debug=2;
    $wpHelper = new NDE\WPHelper( $debug );
    $wpHelper->updateSeries($limit);
    error_log('Finished');
}

function nde_edreports_datapull_series_details_func($limit = 5) {
    error_log('Running ' . __FUNCTION__);
    if ( defined( 'DOING_CRON' ) ) {
        $debug=1;
        $limit=100;
    }
    else $debug=2;
    $wpHelper = new NDE\WPHelper($debug);
    $result = $wpHelper->updateSeriesDetail($limit);
    error_log('Finished');
    return $result;
}

function nde_edreports_datapull_reports_func() {
    error_log('Running ' . __FUNCTION__);
    if ( defined( 'DOING_CRON' ) ) $debug=1;
    else $debug=2;
    $wpHelper = new NDE\WPHelper($debug);
    $wpHelper->updateReports();
    error_log('Finished');
}

function nde_edreports_datapull_reports_details_func($limit=15) {
    error_log('Running ' . __FUNCTION__);
    if ( defined( 'DOING_CRON' ) ) $debug=1;
    else $debug=2;
    $wpHelper = new NDE\WPHelper($debug);
    $results = $wpHelper->updateReportDetails($limit);    
    error_log('Finished running ' . __FUNCTION__);
    return $results;
}
         

function nde_edreports_datapull_publishers_func($limit=100) {
    error_log('Running ' . __FUNCTION__);
    if ( defined( 'DOING_CRON' ) ) $debug=1;
    else $debug=2;
    $wpHelper = new NDE\WPHelper($debug);
    $wpHelper->updatePublishers($limit);
    error_log('Finished');
}

function nde_edreports_datapull_reporttypes_details_func() {
    error_log('Running ' . __FUNCTION__);
    if ( defined( 'DOING_CRON' ) ) $debug=1;
    else $debug=2;
    $wpHelper = new NDE\WPHelper($debug);
    $wpHelper->updateReportTypesDetails();
    error_log('Finished');
}

function nde_edreports_datapull_reporttypes_func() {
    error_log('Running ' . __FUNCTION__);
    if ( defined( 'DOING_CRON' ) ) $debug=1;
    else $debug=2;
    $wpHelper = new NDE\WPHelper($debug);
    $wpHelper->updateReportTypes();
    error_log('Finished');
}

function nde_edreports_datapull_grades_func() {
    error_log('Running ' . __FUNCTION__);
    if ( defined( 'DOING_CRON' ) ) $debug=1;
    else $debug=2;
    $wpHelper = new NDE\WPHelper($debug);
    $wpHelper->updateGrades();
    error_log('Finished');
}

function nde_edreports_datapull_subjects_func() {
    error_log('Running ' . __FUNCTION__);
    if ( defined( 'DOING_CRON' ) ) $debug=1;
    else $debug=2;
    $wpHelper = new NDE\WPHelper($debug);
    $wpHelper->updateSubjects();
    error_log('Finished');
}

function nde_edreports_add_intervals($schedules) {
    // add a 'weekly' interval
    $schedules['weekly'] = array(
        'interval' => 604800,
        'display' => __('Once Weekly')
    );
    $schedules['monthly'] = array(
        'interval' => 2635200,
        'display' => __('Once a month')
    );
    $schedules['tenminutes'] = array(
        'interval' => 600,
        'display' => __('Every 10 Minutes')
    );
    return $schedules;
}

add_filter('cron_schedules', 'nde_edreports_add_intervals');

function wpedreports_create_menu() {
    $edreplogo = "PD94bWwgdmVyc2lvbj0iMS4wIiBlbmNvZGluZz0idXRmLTgiPz4NCjwhRE9DVFlQRSBzdmcgUFVCTElDICItLy9XM0MvL0RURCBTVkcgMS4xLy9FTiIgImh0dHA6Ly93d3cudzMub3JnL0dyYXBoaWNzL1NWRy8xLjEvRFREL3N2ZzExLmR0ZCI+DQo8c3ZnIHZlcnNpb249IjEuMSIgaWQ9IkxheWVyXzEiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyIgeG1sbnM6eGxpbms9Imh0dHA6Ly93d3cudzMub3JnLzE5OTkveGxpbmsiIHg9IjBweCIgeT0iMHB4Ig0KCSB3aWR0aD0iMTIxLjMyNnB4IiBoZWlnaHQ9IjEyMS4zMjdweCIgdmlld0JveD0iMjQ1IDMzNS42ODQgMTIxLjMyNiAxMjEuMzI3IiBlbmFibGUtYmFja2dyb3VuZD0ibmV3IDI0NSAzMzUuNjg0IDEyMS4zMjYgMTIxLjMyNyINCgkgeG1sOnNwYWNlPSJwcmVzZXJ2ZSI+DQo8dGl0bGU+ZWRyZXBvcnRzX2xvZ29fY215azwvdGl0bGU+DQo8cGF0aCBmaWxsPSIjRkZENzc3IiBkPSJNMjk5Ljk0MywzMzUuNjg0djU0Ljk0M0gyNDVDMjQ3LjY4NCwzNjEuNTMxLDI3MC44NDcsMzM4LjM2OCwyOTkuOTQzLDMzNS42ODR6Ii8+DQo8cGF0aCBmaWxsPSIjMDBBQUU3IiBkPSJNMzY2LjMyNiwzOTAuNjI3aC01NS4wODR2LTU0Ljk0M0MzNDAuNDc5LDMzOC4zNjgsMzYzLjUwMiwzNjEuNTMxLDM2Ni4zMjYsMzkwLjYyN3oiLz4NCjxwYXRoIGZpbGw9IiM0OTU5NjUiIGQ9Ik0yNDUsNDAyLjA2OGg1NC45NDN2NTQuOTQyQzI3MC44NDcsNDU0LjE4NiwyNDcuNjg0LDQzMS4xNjQsMjQ1LDQwMi4wNjh6Ii8+DQo8L3N2Zz4NCg==";
    add_menu_page('WP EdReports Tools', 'EDReports', 'administrator', __FILE__, 'edreports_tools_page', "data:image/svg+xml;base64," . $edreplogo);
    add_options_page('WP EdReports Settings', 'EDReports', 'manage_options', 'edreports_settings', 'edreports_plugin_settings_page');
    add_action('admin_init', 'register_wpedreports_settings');
}

add_action('admin_menu', 'wpedreports_create_menu');

function register_wpedreports_settings() {
    register_setting('wpedreports-settings-group', 'edrepsettings');
}

function edreports_tools_page() {
    ?>
    <h2>Clear all EdReports Data</h2>
    <form method="post">
        <hr />
        <table class="form-table">
            <tr valign="top">
                <th scope="row">Clear EdReports Data</th>
                <td>
                    <input type="text" name="nukepassphrase" value="" placeholder="" /><small>Type in <em>NUKEEDREPORTS</em></small><br />
                    <button class="button button-primary button-large" name="nukeEdReports" value="1">Dump all EdReport's Cached Data</button>                    
                </td>
            </tr>
        </table>
    </form>
    <hr />
    <h2>Manual Refresh Of Data</h2>
    <form method='post' target='_blank'>
        <table class="form-table">
            <tr valign="top">
                <th scope="row">How many entities to process at a time?</th>
                <td>
                    <select name='limit'>
                        <option value='5'>5</option>
                        <option value='15'>15</option>
                        <option value='25'>25</option>
                        <option value='35'>35</option>
                        <option value='50'>50</option>
                        <option value='75'>75</option>
                        <option value='100'>100</option>
                        <option value='200'>200</option>
                        <option value='300'>300</option>
                    </select>                        
                </td>
            </tr>
            <tr valign="top">
                <th scope="row">Enable Continuous Looping?</th>
                <td><select name='loop'>
                        <option value=''>No</option>
                        <option value='1'>Yes</option>
                    </select></td>
            </tr>
            <tr valign="top">
                <th scope="row">Table</th>
                <td>
                    <button class='button-large button button-primary' onclick="jQuery(this).removeClass('button-primary')" name='edreports_manual_update' value='grades' value='1'>Grades</button>
                    <button class='button-large button button-primary' onclick="jQuery(this).removeClass('button-primary')" name='edreports_manual_update' value='subjects' value='1'>Subjects</button>
                    <button class='button-large button button-primary' onclick="jQuery(this).removeClass('button-primary')" name='edreports_manual_update' value='publishers' value='1'>Publishers</button>
                    <hr />
                    <button class='button-large button button-primary' onclick="jQuery(this).removeClass('button-primary')" name='edreports_manual_update' value='types' value='1'>Report Types</button>
                    <button class='button-large button button-primary' onclick="jQuery(this).removeClass('button-primary')" name='edreports_manual_update' value='typedetails' value='1'>Report Type Details</button>
                    <hr />
                    <button class='button-large button button-primary' onclick="jQuery(this).removeClass('button-primary')" name='edreports_manual_update' value='series' value='1'>Series</button>
                    <button class='button-large button button-primary' onclick="jQuery(this).removeClass('button-primary')" name='edreports_manual_update' value='seriesdetails' value='1'>Series Details (Uses limit)</button>
                    <hr />
                    <button class='button-large button button-primary' onclick="jQuery(this).removeClass('button-primary')" name='edreports_manual_update' value='reports' value='1'>Reports</button>
                    <button class='button-large button button-primary' onclick="jQuery(this).removeClass('button-primary')" name='edreports_manual_update' value='reportsdetails' value='1'>Report Details</button>

                    
                    
                </td>
            </tr>
            <tr>
                <th></th>
                <td>
                    Each button opens a new window to process request. Only Series Details and Report Details should really bu used with continuous looping.
                </td>
            </tr>
        </table>

    </form>
    <?php
}

function edreports_plugin_settings_page() {
    ?>
    <div class="wrap">
        <h1>WP EdReports Integration</h1>

        <form method="post" action="options.php">
            <?php settings_fields('wpedreports-settings-group'); ?>
            <?php do_settings_sections('wpedreports-settings-group'); ?>
            <?php $er = get_option('edrepsettings'); ?>
            <table class="form-table">
                <tr valign="top">
                    <th scope="row">Environment</th>
                    <td>
                        <select name="edrepsettings[environment]">
                            <option value="TESTING">TESTING</option>
                            <option value="LIVE" <?= isset($er['environment']) && esc_attr($er['environment']) == 'LIVE' ? 'selected="SELECTED"' : ''; ?>>LIVE</option>
                        </select>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row">Authentication Type</th>
                    <td>
                        <select name="edrepsettings[authType]">
                            <option value="none">None</option>
                            <option value="basic" <?= isset($er['authType']) && esc_attr($er['authType']) == 'basic' ? 'selected="SELECTED"' : ''; ?>>Basic</option>                            
                        </select>
                    </td>
                </tr>         
                <tr valign="top">
                    <th scope="row">Basic Auth User</th>
                    <td><input type="text" name="edrepsettings[userName]" value="<?php echo isset($er['userName']) ? esc_attr($er['userName']) : ''; ?>" /></td>
                </tr>        
                <tr valign="top">
                    <th scope="row">Basic Auth Password</th>
                    <td><input type="password" name="edrepsettings[password]" value="<?php echo isset($er['password']) ? esc_attr($er['password']) : ''; ?>" /></td>
                </tr>
                <tr valign="top">
                    <th scope="row">API Key</th>
                    <td><input type="password" name="edrepsettings[key]" value="<?php echo isset($er['key']) ? esc_attr($er['key']) : ''; ?>" /></td>
                </tr>
                <tr valign="top">
                    <th scope="row">Debug Level</th>
                    <td>
                        <select name="edrepsettings[debug]">
                            <option value="0">None</option>
                            <option value="1" <?= isset($er['debug']) && esc_attr($er['debug']) == '1' ? 'selected="SELECTED"' : ''; ?>>1 - Log File</option>
                            <option value="2" <?= isset($er['debug']) && esc_attr($er['debug']) == '2' ? 'selected="SELECTED"' : ''; ?>>2 - On-screen</option>
                        </select>
                    </td>
                </tr>
            </table>

            <?php submit_button(); ?>





        </form>
    </div>
<?php } ?>