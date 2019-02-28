<?php

namespace NDE;

/**
 * Description of WPHelper
 *
 * @author david.hefley
 */
class WPHelper {

    private $debug = 2;
    private $cache = 'wordpress';
    private $edReports;
    private $forceRefresh = false;
    private $queryStart = 0;
    private $queryLimit = 10;
    private $dbVer = "23";
    private $syncDates = '';
    private $columnsFor=[];
    private $ages = [
        'subjects' => 1440,
        'report_types' => 1440,
        'grades' => 1440,
        'publishers' => 60,
        'series' => 60,
        'seriesDetail' => 1440,
        'reports' => 240,
        'reportsDetail' => 1440,
    ];

    public function __construct($debug = '') {

        if (function_exists('is_wpe') && is_wpe())
            $this->debug = 0;
        if (function_exists('is_wpe_snapshot') && is_wpe_snapshot())
            $this->debug = 1;
        $er = get_option('edrepsettings');

        if (empty($debug)) {
            $this->debug = isset($er['debug']) ? (int) $er['debug'] : 2;
        } else {
            $this->debug = (int) $debug;
        }

        $this->edReports = new EdReportAPI([
            'authType' => isset($er['authType']) ? $er['authType'] : '',
            'userName' => isset($er['userName']) ? $er['userName'] : '',
            'password' => isset($er['password']) ? $er['password'] : '',
            'apikey' => isset($er['key']) ? $er['key'] : '',
            'environment' => isset($er['environment']) ? $er['environment'] : 'TEST',
            'debug' => $this->debug
        ]);

        add_action('init', [$this, 'init']);
    }

    public function init() {
        add_action('wp_ajax_edrepimage', [$this, 'edrepimage_handler']);
        add_action('wp_ajax_edrepimage_nopriv', [$this, 'edrepimage_handler']);

        $dbVer = get_option('ndecustomdbver');
        if ($dbVer != $this->dbVer) {
            $this->database();
        }
    }

    public function seriesReports($series_id) {
        global $wpdb;
        $reports_table = $wpdb->prefix . 'EdRep_reports';
        $grades_table = $wpdb->prefix . 'EdRep_grades';
        $details_table = $wpdb->prefix . 'EdRep_report_details';
        $qry = "SELECT a.id, title, report_date, report_type, b.name as grade, description, data FROM {$reports_table} a";
        $qry .= " LEFT JOIN {$grades_table} b on a.grade_taxonomy_id = b.id";
        $qry .= " LEFT JOIN {$details_table} c on a.id = c.id";
        $qry .= " WHERE a.series_id=%d";
        $qry .= " ORDER BY cast(grade as unsigned)";

        $params = [$series_id];
        $qry = $wpdb->prepare($qry, $params);
        $reports = $wpdb->get_results($qry);
        return $reports;
    }

    public function edrepimage_handler() {
        $image = base64_decode($_REQUEST['img']);
        $this->edReports->serveImage($image);
        die();
    }

    public function database() {
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        global $wpdb;

        $queries = [];
        /** GRADES * */
        $table_name = $wpdb->prefix . 'EdRep_grades';
        $queries[] = "CREATE TABLE `" . $table_name . "` (
                    `id` int(11) NOT NULL, `code` varchar(10) NOT NULL, 
                    `name` varchar(10) NOT NULL, 
                    `description` varchar(50) NOT NULL, 
                    PRIMARY KEY  (id)
                   );";

        /** SUBJECT * */
        $table_name = $wpdb->prefix . 'EdRep_subjects';
        $queries[] = "CREATE TABLE `" . $table_name . "` (`id` int(11) NOT NULL, `name` varchar(50) NOT NULL, PRIMARY KEY  (id) );";

        /** PUBLISHERS * */
        $table_name = $wpdb->prefix . 'EdRep_publishers';
        $queries[] = "CREATE TABLE `" . $table_name . "` (
            `id` int(11) NOT NULL, 
            `publisher` varchar(150) NOT NULL,
            `date_updated` datetime NOT NULL, 
            PRIMARY KEY  (id) );";

        /** SERIES * */
        $table_name = $wpdb->prefix . 'EdRep_series';
        $queries[] = "CREATE TABLE `" . $table_name . "` (
            `id` int(11) NOT NULL, 
            `title` varchar(150) NOT NULL, 
            `grades_description` varchar(150) NOT NULL, 
            `publisher_id` int(11) NOT NULL, 
            `subject_taxonomy_id` int(11) NOT NULL, 
            `edition` varchar(40) NOT NULL, 
            PRIMARY KEY  (id) );";


        /** SERIES->DETAIL * */
        $table_name = $wpdb->prefix . 'EdRep_series_detail';
        $queries[] = "CREATE TABLE `" . $table_name . "` (
                    `id` int(11) NOT NULL,                     
                    `title` varchar(100) NOT NULL, 
                    `series_url` varchar(200) NOT NULL, 
                    `grades_description` varchar(100) NOT NULL, 
                    `publisher_id` int(11) NOT NULL, 
                    `edition` varchar(50) NOT NULL, 
                    `subject_taxonomy_id` int(11) NOT NULL, 
                    `image` varchar(100) NOT NULL, 
                    `insights_from_reviewers` text NOT NULL,
                    `additional_notes` text NOT NULL,
                    `is_state_specific` text NOT NULL,
                    `additional_notes_open_source` text NOT NULL,
                    `series_date` date NOT NULL,
                    `publisher_responses` text NOT NULL,
                    `last_updated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP NOT NULL, 
                    PRIMARY KEY  (id)
                   );";


        /** REPORTS * */
        $table_name = $wpdb->prefix . 'EdRep_reports';
        $queries[] = "CREATE TABLE `" . $table_name . "` (
                    `id` int(11) NOT NULL,                     
                    `title` varchar(100) NOT NULL, 
                    `report_date` date NOT NULL,
                    `subject_taxonomy_id` int(11) NOT NULL, 
                    `grade_taxonomy_id` int(11) NOT NULL, 
                    `series_id` int(11) NOT NULL, 
                    `report_type` varchar(100) NOT NULL,                     
                    PRIMARY KEY  (id)
                   );";

        $table_name = $wpdb->prefix . 'EdRep_report_types';
        $queries[] = "CREATE TABLE `" . $table_name . "` (
                    `id` varchar(20) NOT NULL,                     
                    `name` varchar(100) NOT NULL,                     
                    PRIMARY KEY  (id)
                    );";

        $table_name = $wpdb->prefix . 'EdRep_report_types_details';

        $queries[] = "CREATE TABLE `" . $table_name . "` (
                    `id` varchar(20) NOT NULL,                     
                    `gateway` varchar(1) NOT NULL,                     
                    `title` varchar(150) NOT NULL,                     
                    `intervals` text NOT NULL,                    
                    PRIMARY KEY  (id, gateway)
                    );";


        $table_name = $wpdb->prefix . 'EdRep_report_types_grades';
        $queries[] = "CREATE TABLE `" . $table_name . "` (
                    `id` varchar(20) NOT NULL,                     
                    `grade` varchar(100) NOT NULL,                     
                    PRIMARY KEY  (id, grade)
                    );";

        $table_name = $wpdb->prefix . 'EdRep_report_details';
        $queries[] = "CREATE TABLE `" . $table_name . "` (
                    `id` varchar(20) NOT NULL,
                    `series_id` int(11) NOT NULL,
                    `data` mediumtext NOT NULL,
                    `last_updated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP NOT NULL,
                    PRIMARY KEY  (id)
                    );";

        $result = dbDelta($queries);
        $this->debugLog($result);

        update_option('ndecustomdbver', $this->dbVer);
    }

    public function queryStart($x = 0) {
        $this->queryStart = $x;
    }

    public function queryLimit($x = 10) {
        $this->queryLimit = $x;
    }

    public function subjects() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'EdRep_subjects';
        $qry = "SELECT * FROM {$table_name}";
        $data = $wpdb->get_results($qry);
        return $data;
    }

    public function grades() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'EdRep_grades';
        $qry = "SELECT * FROM {$table_name}";
        $data = $wpdb->get_results($qry);
        return $data;
    }

    public function publishers() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'EdRep_publishers';
        $qry = "SELECT * FROM {$table_name}";
        $data = $wpdb->get_results($qry);
        return $data;
    }

    public function series($subject = '', $grades = [], $text = '', $state = 'none') {
        global $wpdb;
        $series_table = $wpdb->prefix . 'EdRep_series';
        $seriesdetails_table = $wpdb->prefix . 'EdRep_series_detail';
        $subject_table = $wpdb->prefix . 'EdRep_subjects';
        $publisher_table = $wpdb->prefix . 'EdRep_publishers';

        $qry = "SELECT a.id, title, grades_description, b.name as subject, c.publisher, edition FROM {$seriesdetails_table} a";
        /* $qry .= " LEFT JOIN {$seriesdetails_table} d ON d.id = a.id"; */
        $qry .= " LEFT JOIN {$subject_table} b ON a.subject_taxonomy_id = b.id";
        $qry .= " LEFT JOIN {$publisher_table} c ON a.publisher_id = c.id";


        $qry .= " WHERE (`is_state_specific`='" . $state . "' OR `is_state_specific`='' )";
        $parameter_values = [];
        if (!empty($subject)) {
            $qry .= ' AND `subject_taxonomy_id`=%d';
            $parameter_values[] = $subject;
        }
        if (!empty($text)) {
            $qry .= ' AND `title` LIKE %s';
            $parameter_values[] = '%' . $text . '%';
        }


        if (!empty($grades)) {
            if (in_array('hs', $grades)) {
                $grades[] = '9';
                $grades[] = '10';
                $grades[] = '11';
                $grades[] = '12';
            }
            if (in_array('0', $grades)) {
                $grades[] = 'K';
            }
            $grades = array_unique($grades);
            $grade_ids = $this->getGradeIds($grades);
            if (!empty($grade_ids)) {
                $related_series = $this->getSeriesFromGrades($grade_ids);
                if ($related_series === FALSE)
                    return [];
                $qry .= " AND a.id IN (" . implode(',', $related_series) . ")";
            }
        }

        $qry .= " ORDER BY a.title";
        $qry .= " LIMIT {$this->queryStart},{$this->queryLimit}";
        $qry = $wpdb->prepare($qry, $parameter_values);
        $data = $wpdb->get_results($qry);
        return $data;
    }

    private function getSeriesFromGrades($grade_ids) {
        if (empty($grade_ids))
            return [];
        global $wpdb;
        $reports_table = $wpdb->prefix . 'EdRep_reports';
        //$func = function($value) { return '"'.$value.'"';};
        //$sql_grades = array_map($func,$grades);
        $qry = "SELECT DISTINCT series_id FROM {$reports_table} WHERE `grade_taxonomy_id` IN (" . implode(',', $grade_ids) . ");";
        $results = $wpdb->get_col($qry);
        return $results;
    }

    private function getGradeIds($grades) {
        if (empty($grades))
            return [];
        global $wpdb;
        $grades_table = $wpdb->prefix . 'EdRep_grades';
        $func = function($value) {
            return '"' . $value . '"';
        };
        $sql_grades = array_map($func, $grades);

        if ( count($grades) > 1 ) {
            $minGrade = min($grades);
            $maxGrade = max($grades);
            $sql_grades[] = '"' . $minGrade.'-'.$maxGrade . '"';
        }

        $qry = "SELECT id FROM {$grades_table} WHERE name IN (" . implode(',', $sql_grades) . ");";
        $results = $wpdb->get_col($qry);


        $dashqry = "SELECT id,code FROM {$grades_table} WHERE `code` LIKE \"%-%\";";

        $dashCollections = $wpdb->get_results($dashqry);

        foreach ( $dashCollections as $dash ) {
            $gradeBand = explode('-', $dash->code);
            if ( count($gradeBand) != 2 ) continue; // something went wrong
            foreach ( $grades as $grade ) {
                if ( $grade >=$gradeBand[0] && $grade <=$gradeBand[1]) $results[] = $dash->id;
            }
        }

        return $results;
    }

    public function reportDetails($id = '', $subject = '') {
        global $wpdb;
        $details_table = $wpdb->prefix . 'EdRep_report_details';

        if (!empty($id)) {
            $qry = $wpdb->prepare("SELECT * FROM {$details_table} WHERE id = %d", $id);
            $data = $wpdb->get_row($qry);
            if (empty($data)) {
                $this->debugLog('Details for report ' . $id . ' not in DB, pulling from API');
                $data = $this->updateReportDetails('', $id);
            }
        } else {
            $this->debugLog('Getting group of details');
            $subject_table = $wpdb->prefix . 'EdRep_subjects';
            $publisher_table = $wpdb->prefix . 'EdRep_publishers';

            $qry = "SELECT a.id, title, grades_description, b.name as subject, c.publisher, edition FROM {$details_table} a";
            $qry .= " LEFT JOIN {$subject_table} b ON a.subject_taxonomy_id = b.id";
            $qry .= " LEFT JOIN {$publisher_table} c ON a.publisher_id = c.id";

            $qry .= " WHERE 1=1";
            $parameter_values = [];
            if (!empty($subject)) {
                $qry .= ' AND `subject_taxonomy_id`=%d';
                $parameter_values[] = $subject;
            }

            $qry .= " LIMIT {$this->queryStart},{$this->queryLimit}";
            $qry = $wpdb->prepare($qry, $parameter_values);
            $data = $wpdb->get_results($qry);
        }

        /* $qry = $wpdb->prepare("SELECT *, TIMESTAMPDIFF(SECOND, last_updated, NOW()) as age FROM {$table_name} WHERE id = %d", $id);
          $data = $wpdb->get_row($qry);
          $maxAge = $this->ages['seriesDetail'] * 60;
          if ((empty($data) || $data->age) > $maxAge && false) {
          $repdata = $this->edReports->reportsDetail($id);
          $this->updateReportDetails($id, $repdata);
          $data = $this->reportDetails($id);
          }
          return maybe_unserialize($data->data);
         *
         */
        return $data;
    }

    public function seriesDetails($id = '', $subject = '', $state = 'none') {
        global $wpdb;
        $details_table = $wpdb->prefix . 'EdRep_series_detail';

        if (!empty($id)) {
            //$this->debugLog('Getting single details');
            $qry = $wpdb->prepare("SELECT * FROM {$details_table} WHERE id = %d AND is_state_specific = %s", $id, $state);
            $data = $wpdb->get_row($qry);
            if (empty($data)) {
                $this->debugLog('Details for series ' . $id . ' not in DB, pulling from API');
                $data = $this->updateSeriesDetail('', $id);
            }
        } else { //id not set, sow e need to do a general query
            $this->debugLog('Getting group of details');
            $subject_table = $wpdb->prefix . 'EdRep_subjects';
            $publisher_table = $wpdb->prefix . 'EdRep_publishers';

            $qry = "SELECT a.id, title, grades_description, b.name as subject, c.publisher, edition FROM {$details_table} a";
            $qry .= " LEFT JOIN {$subject_table} b ON a.subject_taxonomy_id = b.id";
            $qry .= " LEFT JOIN {$publisher_table} c ON a.publisher_id = c.id";

            $qry .= " WHERE 1=1";
            $parameter_values = [];
            if (!empty($subject)) {
                $qry .= ' AND `subject_taxonomy_id`=%d';
                $parameter_values[] = $subject;
            }

            $qry .= " LIMIT {$this->queryStart},{$this->queryLimit}";
            $qry = $wpdb->prepare($qry, $parameter_values);
            $data = $wpdb->get_results($qry);
        }

        return $data;
    }

    public function reports($subject = '') {
        global $wpdb;
        $table_name = $wpdb->prefix . 'EdRep_reports';
        $qry = "SELECT * FROM {$table_name} WHERE 1=1";
        $parameter_values = [];
        if (!empty($subject)) {
            $qry .= ' AND `subject_taxonomy_id`=%d';
            $parameter_values[] = $subject;
        }

        $qry .= " LIMIT {$this->queryStart},{$this->queryLimit}";

        $qry = $wpdb->prepare($qry, $parameter_values);
        $data = $wpdb->get_results($qry);
        return $data;
    }

    public function report_types($incGrade = true) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'EdRep_report_types';
        $qry = "SELECT * FROM {$table_name}";
        $data = $wpdb->get_results($qry);
        $table_name = $wpdb->prefix . 'EdRep_report_types_grades';
        if ($incGrade) {
            foreach ($data as &$d) {
                $qry = $wpdb->prepare("SELECT grade FROM {$table_name} WHERE id = %s", $d->id);
                $d->grade_taxonomy_ids = $wpdb->get_col($qry);
            }
        }


        return $data;
    }

    /**
     * Retrieve data from appropriate cache if enabled
     * @param string $target
     * @param int $age minutes, optional as WP takes care of this automatically
     * @return boolean
     */
    private function refreshCache($target, $id = '') {
        if ($this->cache === FALSE || $this->forceRefresh) {
            //basically, this means we always pull data from the api live
            $this->debugLog('Retrieving from LIVE API');
            return true;
        } else {
            $transient = get_transient('EDREPORTS:' . $target);
            if ($transient === FALSE) {
                $this->debugLog('Scheduling update from LIVE API for ' . $target);
                $timestamp = wp_next_scheduled('refreshEdreportsData', [$target]);
                if ($timestamp !== FALSE) {
                    $this->debugLog('Already Scheduled for ' . $timestamp);
                } else {
                    $args = [$target];
                    if (!empty($id))
                        $args[] = $id;
                    wp_schedule_single_event(time() + 300, 'refreshEdreportsData', $args);
                }
            } else {
                $this->debugLog('NOT Retrieving from LIVE API');
            }
            return ( $transient === FALSE );
        }
    }

    /**
     * Enables/Disables cache refresh force
     */
    public function cacheRefresh($refresh = FALSE) {
        $this->forceRefresh = $refresh;
    }

    /**
     * Basic function to write to the error log, and (optionally) screen if enabled
     * @param type $m
     */
    public function debugLog($m) {

        if (defined('WP_CLI')) {
            echo $m . "\n";
            return;
        }

        if ($this->debug !== FALSE) {
            if ($this->debug > 1) :
                echo "<pre>";
                print_r($m);
                echo "</pre>";
            endif;

            if ($this->debug > 0)
                error_log(print_r($m, TRUE));
        }
    }

    public function nukeEdReportsData() {
        global $wpdb;
        $tableBase = $wpdb->prefix . 'EdRep_';
        $tables = [
            'grades',
            'publishers',
            'reports',
            'report_details',
            'report_types',
            'report_types_details',
            'report_types_grades',
            'series',
            'series_detail',
            'subjects'
        ];

        foreach ($tables as $table):
            $r = $wpdb->query('TRUNCATE TABLE `' . $tableBase . $table . '`');
            if (!$r)
                return false;
        endforeach;

        return true;
    }

    /** UPDATE FUNCTIONS -> mostfly for cron! * */

    /**
     * Get all series from API and insert
     * @global \NDE\type $wpdb
     * @return boolean
     */
    public function updateSeries(int $limit = 5, bool $all = false, int $timeframe = 0):bool {
        $this->debugLog(__FUNCTION__);
        $this->debugLog('Limit is ' . $limit);
        $this->debugLog('Forcing ALL is ' . ($all ? 'on' : 'off') );
        $this->debugLog('Timeframe is ' . $timeframe );

        $lastSync = $this->getLastSyncTime('series');

        if ( $lastSync == '0000-00-00' ) {
            $all = true; // we haven't done anything, so lets do this all!
        }


        global $wpdb;
        $table_name = $wpdb->prefix . 'EdRep_series';

        if ( $all ) {
            $data = $this->edReports->series('', '', 1, $limit);
            $r = $this->processAllData($table_name, $data);
            if ( $r ) $this->updateSyncTime('series');
        } else {
            $this->debugLog("last sync was " . $lastSync);
            //go back $timeframe days:
            $syncDate = date('Y-m-d', strtotime('-' . $timeframe .' day', $lastSync) );
            $this->debugLog("Syncing from {$syncDate}");
            $updated = $this->edReports->series('','', 1, $limit, $syncDate, 'updated');
            $deleted = $this->edReports->series('','', 1, $limit, $syncDate, 'deleted');
            $r = $this->processUpdates($table_name, $updated, $deleted);

            $detail_table_name = $wpdb->prefix . 'EdRep_series_detail';

            // for deleted
            if ( !empty($deleted) ) {
                foreach ($deleted as $delete) {
                    $where = ['id'=>$delete->id ];
                    $wpdb->delete($detail_table_name, $where);
                    $this->debugLog('Deleted ' . $delete->id .' from ' . $detail_table_name);
                }
            }

            if ( !empty($updated) ) {
                foreach ($updated as $update) {
                    $this->debugLog('Updating ' . $update->id);
                    $this->updateSeriesDetail(1, $update->id);
                }
            }

            if ($r) $this->updateSyncTime('series');
        }
        return true;

    }

    /**
     * Get the next $limit series and and update them
     * @global \NDE\type $wpdb
     * @param type $limit
     * @param type $id
     * @return boolean
     */
    public function updateSeriesDetail($limit = 5, $id = '') {
        $this->debugLog(__FUNCTION__);
        $this->debugLog('Loop limit set to ' . $limit);
        if (empty($limit) && empty($id))
            return false;
        global $wpdb;
        $details_table = $wpdb->prefix . 'EdRep_series_detail';
        $series_table = $wpdb->prefix . 'EdRep_series';


        //$existing_columns = $wpdb->get_col("DESC {$details_table}", 0);
        //$this->debugLog($existing_columns);
        $this->edReports->disableDebug();
        if (!empty($id)) {
            $this->debugLog('Updating singular ' . $id);
            $detaildata = $this->edReports->seriesDetail($id);
            $pub_responses = serialize($detaildata->publisher_responses);
            $detaildata->publisher_responses = $pub_responses;
            $detaildata->last_updated = date('Y-m-d h:i:s');
            $insertData = $this->sanitize_api_columns($details_table, [$detaildata] );
            if ( !empty($insertData) ) $singular_insert_data = current($insertData);
            $result = $wpdb->replace($details_table, $singular_insert_data);
            if ($result === FALSE)
                $this->debugLog('SINGLE: Unable to update series details for ' . $id);
            else
                $this->debugLog('SINGLE: Updated ' . $id . ' series details');
            return $detaildata;
        } else {
            //id was not forced, so use limit
            $this->debugLog('Attempting to update ' . $limit . ' series');
            // this is the query for age based:
            // $qry = "SELECT id from {$details_table} WHERE TIMESTAMPDIFF(MINUTE,last_updated,'" . date('Y-m-d h:i:s') . "') <= " . $this->ages['seriesDetail']; //do no tneed updates
            // this is the query for MISSING based
            $qry = "SELECT id from {$details_table}"; // this is for anything that is missing
            $reports_qry = "SELECT id FROM {$series_table} WHERE id NOT IN ({$qry}) LIMIT " . $limit;
            $this->debugLog($reports_qry);
            $detailRows = $wpdb->get_col($reports_qry);
            if (!empty($detailRows)) {
                foreach ($detailRows as $id) {
                    $detaildata = $this->edReports->seriesDetail($id);
                    $pub_responses = serialize($detaildata->publisher_responses);
                    $detaildata->publisher_responses = $pub_responses;
                    $detaildata->last_updated = date('Y-m-d h:i:s');

                    /* ensure future changes of api won't break what we are doing now! */
                    $insertData = $this->sanitize_api_columns($details_table, [$detaildata]);
                    if ( !empty($insertData) ) $singular_insert_data = current($insertData);
                    $result = $wpdb->replace($details_table, $singular_insert_data);
                    if ($result === FALSE)
                        $this->debugLog('GROUP: Unable to update series details for ' . $id);
                    else
                        $this->debugLog('GROUP: Updated ' . $id . ' series details');
                }
            } else {
                return 'noupdates';
            }
        }
    }

    public function updateReportDetails($limit = 5, $id = '') {
        $this->debugLog(__FUNCTION__);
        $this->debugLog('Loop limit set to ' . $limit);

        if (empty($limit) && empty($id)) {
            $this->debugLog('Empty parameters');
            return false;
        }
        global $wpdb;
        $details_table = $wpdb->prefix . 'EdRep_report_details';

        if (!empty($id)) {
            $detaildata = $this->edReports->reportsDetail($id);
            $this->debugLog($detaildata);
            $serData = serialize($detaildata);
            $result = $wpdb->replace($details_table, ['id' => $id, 'series_id' => $detaildata->series_id, 'data' => $serData]);
            if ($result === FALSE)
                $this->debugLog('SINGLE: Unable to update reports details for ' . $id);
            else
                $this->debugLog('SINGLE: Updated ' . $id . ' reports details');
            return $serData;
        } else {
            $reports_table = $wpdb->prefix . 'EdRep_reports';

            //this was for age based
            //$qry = "SELECT id from {$details_table} WHERE TIMESTAMPDIFF(MINUTE,last_updated,'" . date('Y-m-d h:i:s') . "') <= " . $this->ages['reportsDetail']; //do no tneed updates

            $qry = "SELECT id from {$details_table}"; // this is for *anything* that is missing
            $reports_qry = "SELECT id FROM {$reports_table} WHERE id NOT IN ({$qry}) LIMIT " . $limit;
            $this->debugLog($reports_qry);
            $detailRows = $wpdb->get_col($reports_qry);
            $this->debugLog("These are the report details we are updating: " . print_r($detailRows, true) );
            if (!empty($detailRows)) {
                foreach ($detailRows as $rid) {
                    $detaildata = $this->edReports->reportsDetail($rid);
                    if (empty($detaildata))
                        continue;
                    if (empty($detaildata->series_id))
                        $detaildata->series_id = 0;
                    $serData = base64_encode(serialize($detaildata));
                    $result = $wpdb->replace($details_table, ['id' => $rid, 'series_id' => $detaildata->series_id, 'data' => $serData]);
                    if ($result === FALSE)
                        $this->debugLog('GROUP: Unable to update reports details for ' . $rid);
                    else
                        $this->debugLog('GROUP: Updated ' . $rid . ' reports details');
                }
            } else {
                $this->debugLog('No Updates to process');
                return 'noupdates';
            }
        }
    }

    /**
     * Retreive grades from API and insert into database
     * @global type $wpdb
     * @return boolean
     */
    public function updateGrades() {
        $this->debugLog(__FUNCTION__);
        $data = $this->edReports->grades();
        if (empty($data)) {
            $this->debugLog('No data returned');
            return false;
        }
        global $wpdb;
        $table_name = $wpdb->prefix . 'EdRep_grades';
        $wpdb->query('TRUNCATE TABLE `' . $table_name . '`');
        foreach ($data as $grade) {
            $wpdb->insert($table_name, (array) $grade);
        }
        return true;
    }

    /**
     * Retreive subject listing from API and insert into db
     * @global \NDE\type $wpdb
     * @return boolean
     */
    public function updateSubjects() {
        $this->debugLog(__FUNCTION__);
        $data = $this->edReports->subjects();
        if (empty($data)) {
            $this->debugLog('No Data returned');
            return false;
        }
        global $wpdb;
        $table_name = $wpdb->prefix . 'EdRep_subjects';
        $wpdb->query('TRUNCATE TABLE `' . $table_name . '`');
        foreach ($data as $subject) {
            $wpdb->insert($table_name, (array) $subject);
        }
        return true;
    }

    /**
     * Retrieve publishers list and insert into db
     * @param integer $limit
     * @param bool $all Controls whether to do a FULL retrieval, or to only do it for the last $timeframe days
     * @param int $timeframe Controls the number of days back when doing a partial update
     * @global \NDE\type $wpdb
     * @return boolean
     */
    public function updatePublishers( int $limit = 5, bool $all = false, int $timeframe = 0):bool {
        $this->debugLog(__FUNCTION__);
        $this->debugLog('Per Page set to ' . $limit);
        $this->debugLog('Forcing ALL is ' . ($all ? 'on' : 'off') );

        $lastSync = $this->getLastSyncTime('publishers');

        if ( $lastSync == '0000-00-00' ) {
            $all = true; // we haven't done anything, so lets do this all!
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'EdRep_publishers';

        if ( $all ) {
            $data = $this->edReports->publishers(1, $limit);
            $r = $this->processAllData($table_name, $data);
            if ( $r ) $this->updateSyncTime('publishers');
        }  else {
            $this->debugLog("last sync was " . $lastSync);

            //go back $timeframe days:
            $syncDate = date('Y-m-d', strtotime('-' . $timeframe .' day', $lastSync) );
            $this->debugLog("Syncing from {$syncDate}");
            $updated = $this->edReports->publishers(1, $limit, $syncDate, 'updated');
            $deleted = $this->edReports->publishers(1, $limit, $syncDate, 'deleted');
            $r = $this->processUpdates($table_name, $updated, $deleted);
            if ( $r ) $this->updateSyncTime('publishers');
        }
        return true;
    }

    /**
     * retreive reports (short version) and insert into db
     * @global \NDE\type $wpdb
     * @return boolean
     */
    public function updateReports(int $limit = 100, bool $all = false, int $timeframe = 0) {
        $this->debugLog(__FUNCTION__);
        $this->debugLog('Per Page set to ' . $limit);
        $this->debugLog('Forcing ALL is ' . ($all ? 'on' : 'off') );

        $lastSync = $this->getLastSyncTime('reports');

        if ( $lastSync == '0000-00-00' ) {
            $all = true; // we haven't done anything, so lets do this all!
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'EdRep_reports';


        if ( $all ) {
            $data = $this->edReports->reports('', '', '', 1, $limit);
            $r = $this->processAllData($table_name, $data);
            if ( $r ) $this->updateSyncTime('reports');
        }  else {
            $this->debugLog("last sync was " . $lastSync);

            //go back $timeframe days:
            $syncDate = date('Y-m-d', strtotime('-' . $timeframe .' day', $lastSync) );
            $this->debugLog("Syncing from {$syncDate}");
            $updated = $this->edReports->reports('','','',1, $limit, $syncDate, 'updated');
            $deleted = $this->edReports->reports('','','',1, $limit, $syncDate, 'deleted');
            $r = $this->processUpdates($table_name, $updated, $deleted);



            $detail_table_name = $wpdb->prefix . 'EdRep_report_detail';

            // for deleted
            if ( !empty($deleted) ) {
                foreach ($deleted as $delete) {
                    $where = ['id'=>$delete->id ];
                    $wpdb->delete($detail_table_name, $where);
                    $this->debugLog('Deleted ' . $delete->id .' from ' . $detail_table_name);
                }
            }

            if ( !empty($updated) ) {
                foreach ($updated as $update) {
                    $this->debugLog('Updating ' . $update->id);
                    $this->updateReportDetails(1, $update->id);
                }
            }

            if ( $r ) $this->updateSyncTime('reports');
        }
        return true;

    }

    /**
     * Retrieve report types from api and insert
     * @global \NDE\type $wpdb
     * @return boolean
     */
    public function updateReportTypes() {
        $this->debugLog(__FUNCTION__);
        $data = $this->edReports->reportTypes();
        if (empty($data)) {
            $this->debugLog('No Data Retrieved');
            return false;
        }
        global $wpdb;
        $table_name = $wpdb->prefix . 'EdRep_report_types';
        $table_name2 = $wpdb->prefix . 'EdRep_report_types_grades';

        $wpdb->query('TRUNCATE TABLE `' . $table_name . '`');
        $wpdb->query('TRUNCATE TABLE `' . $table_name2 . '`');

        foreach ($data as $report_type) {
            $grades = $report_type->grade_taxonomy_ids;
            unset($report_type->grade_taxonomy_ids);
            $wpdb->insert($table_name, (array) $report_type);
            foreach ($grades as $g) {
                $wpdb->insert($table_name2, ['id' => $report_type->id, 'grade' => $g]);
            }
        }
    }

    public function updateReportTypesDetails() {
        $this->debugLog(__FUNCTION__);
        $reportTypes = $this->report_types(false);
        if (empty($reportTypes))
            return false;
        global $wpdb;
        $table_name = $wpdb->prefix . 'EdRep_report_types_details';
        foreach ($reportTypes as $type) {
            $details = $this->edReports->reportTypesDetail($type->id);
            if (empty($details)) {
                $this->debugLog('Unable to get details for ' . $type->id);
                continue;
            }
            $insert_data = [
                'id' => $type->id,
                'gateway' => '1',
                'title' => $details->gateway_1_title,
                'intervals' => base64_encode(serialize($details->gateway_1_intervals))
            ];
            $wpdb->replace($table_name, $insert_data);
            $insert_data = [
                'id' => $type->id,
                'gateway' => '2',
                'title' => $details->gateway_2_title,
                'intervals' => base64_encode(serialize($details->gateway_2_intervals))
            ];

            $wpdb->replace($table_name, $insert_data);
            $insert_data = [
                'id' => $type->id,
                'gateway' => '3',
                'title' => $details->gateway_3_title,
                'intervals' => base64_encode(serialize($details->gateway_3_intervals))
            ];
            $wpdb->replace($table_name, $insert_data);
        }
    }

    /**
     * Get specific report intervals (i.e. min/max for gateways) from database
     * @global \NDE\type $wpdb
     * @return type
     */
    public function reportIntervals() {
        $this->debugLog(__FUNCTION__);
        global $wpdb;
        $table_name = $wpdb->prefix . 'EdRep_report_types_details';
        $results = $wpdb->get_results("SELECT * FROM {$table_name}");
        $return = [];
        foreach ($results as $r) {
            if (!isset($return[$r->id]))
                $return[$r->id] = ['1' => [], '2' => [], '3' => []];
            $return[$r->id][$r->gateway]['title'] = $r->title;
            $intdata = unserialize(base64_decode($r->intervals));
            $reindex = [];
            foreach ($intdata as $i) {
                $reindex[$i->rating] = $i;
            }
            $return[$r->id][$r->gateway]['intervals'] = $reindex;
        }
        return $return;
    }


    private function loadSyncDates() {
        if ( $this->syncDates === '' ) {
            $syncdates = get_option('edrepsyncdates');
            if ( empty($syncdates) ) $this->syncDates=[]; //change it to an array so we know it is loaded
            else $this->syncDates = $syncdates;
        }


    }

    private function getLastSyncTime(string $name) {
            $this->loadSyncDates();
            if ( isset($this->syncDates[$name]) ) return $this->syncDates[$name];
            else return '0000-00-00';
    }

    /**
     * Sets the last synced time for future updates
     * @param string $name
     * @return bool
     */
    private function updateSyncTime(string $name): bool {
        $this->loadSyncDates();
        $this->syncDates[$name] = time();
        update_option('edrepsyncdates', $this->syncDates, false);
        return true;

    }

    /**
     * Makes sure that we only insert existing fields into db. Sometimes the API will change(add) fields to the return and we shouldn't just blindly insert them.
     *
     * @param string $table
     * @param object $data
     * @return array
     */
    private function sanitize_api_columns(string $table_name, $data ): array {
        global $wpdb;

        if ( !isset($this->columnsFor[$table_name]) ) {
            $retrieved_columns = $wpdb->get_col("DESC {$table_name}", 0);
            $this->columnsFor[$table_name] = $retrieved_columns;
            $this->debugLog("COLUMNS RETRIEVED FOR {$table_name}:" .  implode(',', $retrieved_columns ).'.' );
            unset($retrieved_columns);
        }

        $existing_columns = $this->columnsFor[$table_name];

        $insertData = [];
        foreach ($data as $item) {
            // lets weed out any unknown fields!
            $insertItem = [];
            foreach ($existing_columns as $datakey) {
                if (
                    ( gettype($item) == 'object' && property_exists ($item,$datakey) ) ||
                    ( gettype($item) == 'array' && data_key_exists ($item,$datakey) )

                ) { // using property_exists since teh column may exist but be null
                    $insertItem[$datakey] = $item->{$datakey};
                }else {
                    $this->debugLog("Missing |{$datakey}| column.");
                }
            }
            if ( !empty($insertItem) ) $insertData[] = $insertItem;
        }

        return $insertData;
    }

    /**
     *
     * Process udpated and deleted items
     * @param string $table_name
     * @param $updated
     * @param $deleted
     * @return bool
     */
    private function processUpdates( string $table_name, $updated, $deleted):bool {
        global $wpdb;

        if ( !empty($updated) ) {
            $sanitizedUpdated = $this->sanitize_api_columns($table_name, $updated);
        }

        if ( !empty($deleted) ) {
            $sanitizedDeleted = $this->sanitize_api_columns($table_name, $deleted);
        }

        unset($updated, $deleted); //we no longer need this

        if ( empty($sanitizedUpdated) ) {
            $this->debugLog('Nothing to update for ' . $table_name);
        } else {

            foreach ($sanitizedUpdated as $updateData) {
                $this->debugLog('Updating ' . $updateData['title']);
                $result = $wpdb->replace($table_name, $updateData); // update each data item
                if ( $result ) $this->debugLog("Updated {$result} items.");
                else $this->debugLog('Failed to update item');
            }
        }

        if ( empty($sanitizedDeleted) ) {
            $this->debugLog('Nothing to delete for ' . $table_name);
        } else {
            foreach ($sanitizedDeleted as $deleteData) {
                $where = ['id'=> $deleteData['id'] ];
                $result = $wpdb->delete($table_name, $where); // delete each data item
                if ( $result ) $this->debugLog("Deleted {$result} items.");
                else $this->debugLog('Failed to delete item');
            }
        }
        return true;
    }

    private function processAllData( string $table_name, $data):bool {
        global $wpdb;
        if (empty($data)) {
            $this->debugLog('No data retrieved for ' . $table_name);
            return false;
        }

        $sanitizedData = $this->sanitize_api_columns($table_name, $data);
        unset($data); //we no longer need this
        if ( empty($sanitizedData) ) {
            $this->debugLog('No Data to insert into ' . $table_name);
            return false;
        }

        $this->debugLog('Attempting to update ' . count($sanitizedData) .' row');

        $wpdb->query('TRUNCATE TABLE `' . $table_name . '`'); // since we are doing a full sync, lets make sure the table is clear
        foreach ($sanitizedData as $insertData) {
            $result = $wpdb->insert($table_name, $insertData); // insert each data item
            if ( $result ) $this->debugLog("Inserted {$result} items.");
            else $this->debugLog('Failed to insert item');
        }
        return true;
    }


}
