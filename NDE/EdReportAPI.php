<?php

/**
 * Description of EdReportAPI
 *
 * @author david.hefley
 */

namespace NDE;

class EdReportAPI {

    private $debug = FALSE;
    private $authType = 'basic';
    private $userName = '';
    private $password = '';
    private $apikey = '';
    private $environment = 'TESTING';
    private $url = '';

    public function __construct($settings = []) {
        $this->debugLog(__FUNCTION__);
        foreach ($settings as $variable => $value) {
            $this->$variable = $value;
        }

        //default to testing environment!
        if ( isset($this->environment) && $this->environment=='LIVE') $this->url="https://api.edreports.org/v2/";
        else $this->url = "https://st.edreports.org/api/v2/";

    }

    /**
     * Set debug varilable to False
     */
    public function disableDebug(){
        $this->debug=false;
    }
    /**
     * Set debug variable to true;
     */
    public function enableDebug(){
        $this->debug=true;
    }

    /**
     * Basic function to write to the error log, and (optionally) screen if enabled
     * @param type $m
     */
    public function debugLog( string $m) {
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

    /**
     * Retrieve subjects.  It has low volatility, so we cache it for a day
     * @param int $age in minutes
     */
    public function subjects() {
        $this->debugLog('Getting Subjects');
        $data = $this->getDataList('taxonomies_subjects');
        //$this->debugLog($data);
        return $data;
    }

    /**
     * Retrieve grades.  It has low volatility, so we cache it for a day
     * @param int $age in minutes
     */
    public function grades($age = 1440) {
        $this->debugLog('Getting Grades');
        $data = $this->getDataList('taxonomies_grades');
        //$this->debugLog($data);
        return $data;
    }

    /**
     *
     * Retrieves the publishers data.  It will limit to a specific page, and number per return, and also allow for the
     * filter_[updated|deleted] parameter to be set with a date ($since) formatted as YYYY-MM-DD to retrieve items
     * updated after that date.
     *
     * @param int $page
     * @param int $perpage
     * @param string $since
     * @param string $type
     * @return object
     */
    public function publishers(int $page = 1, int $perpage = 5, string $since='', string $type=''):array {
        $this->debugLog('Getting Publishers');
        $parameters = [];
        if (!empty($page))
            $parameters['page'] = (int) $page;
        if (!empty($page))
            $parameters['per-page'] = (int) $perpage;
        if ( !empty($since) && in_array($type, ['updated','deleted']) )
            $parameters['filter_' . $type ] = $since;
        $data = $this->getDataList('publishers', $parameters);
        //$this->debugLog($data);
        return $data ?? [];
    }

    /**
     * Retrieve series.
     * @param int $age in minutes
     */
    public function series($publisher = '', $subject = '', $page = 1, $perpage = 5, $since='', $type='') {
        $this->debugLog('Getting Series');
        $parameters = [];
        if (!empty($publisher))
            $parameters['publisher_id'] = (int) $publisher;
        if (!empty($subject))
            $parameters['subject_taxonomy_id'] = (int) $subject;
        if (!empty($page))
            $parameters['page'] = (int) $page;
        if (!empty($page))
            $parameters['per-page'] = (int) $perpage;
        if ( !empty($since) && in_array($type, ['updated','deleted']) )
            $parameters['filter_' . $type ] = $since;
        $data = $this->getDataList('series', $parameters);
        //$this->debugLog($data);
        return $data;
    }

    /**
     * Retrieve series detail.
     * @param int $age in minutes
     */
    public function seriesDetail($entity) {
        $this->debugLog('Getting Series Details');
        $data = $this->getDataDetails('series', $entity);
        //$this->debugLog($data);
        return $data;
    }

    /**
     * Retrieve reports.
     * @param int $age in minutes
     */
    public function reports($subject = '', $grade = '', $publisher = '', $page = 1, $perpage = 5, $since='', $type='') {
        $this->debugLog('Getting Reports');
        $parameters = [];
        if (!empty($publisher))
            $parameters['publisher_id'] = (int) $publisher;
        if (!empty($subject))
            $parameters['subject_taxonomy_id'] = (int) $subject;
        if (!empty($grade))
            $parameters['grade_taxonomy_id'] = (int) $subject;
        if (!empty($page))
            $parameters['page'] = (int) $page;
        if (!empty($page))
            $parameters['per-page'] = (int) $perpage;
        if ( !empty($since) && in_array($type, ['updated','deleted']) )
            $parameters['filter_' . $type ] = $since;
        $data = $this->getDataList('reports', $parameters);
        //$this->debugLog($data);
        return $data;
    }

    /**
     * Retrieve series detail.
     * @param int $age in minutes
     */
    public function reportsDetail($entity) {
        $data = $this->getDataDetails('reports', $entity);
        $this->debugLog('Retrieved Data for: '. $data->id.":" . $data->title);
        return $data;
    }

    /**
     * Retrieve reports.
     * @param int $age in minutes
     */
    public function reportTypes() {
        $this->debugLog('Getting Report Types');
        $data = $this->getDataList('report_types');
        //$this->debugLog($data);
        return $data;
    }

    /**
     * Retrieve report types detail.
     * @param int $age in minutes
     */
    public function reportTypesDetail($entity) {
        $this->debugLog('Getting Report Type Details');
        $data = $this->getDataDetails('report_types', $entity);
        //$this->debugLog($data);
        return $data;
    }

    /**
     * Re-index data so it comes back as id=>$data in array
     * @param type $data
     * @return type
     */
    private function reIndexResult($data) {
        $final = [];
        if (!empty($data)) {
            foreach ($data as $d) {
                $id = $d->id;
                unset($d->id);
                $final[$id] = $d;
            }
        }
        return $final;
    }

    /**
     * Do we have everything we need to actually retreive data??
     * @throws Exception
     */
    private function checkInfo() {

        if ( empty($this->url) ) throw new \Exception('Missing URL.');
        if (empty($this->apikey)) throw new \Exception('Missing API Key.');

        switch ($this->authType):
            case 'basic':
                if (empty($this->userName))
                    throw new \Exception('Missing Username.');
                if (empty($this->password))
                    throw new \Exception('Missing Password.');
                break;
            case 'none': return true; //no auth, always have what we need!
            default:
                die('Unknown Auth Type'); //unknown auth type, so bail!
        endswitch;
    }

    /**
     * Actually go to the endpoint and retrieve the data list
     * @param string $endpoint
     * @param int $page
     * @return mixed
     * @throws Exception
     */
    private function getDataDetails($endpoint, $id) {
        $this->debugLog(__FUNCTION__);

        //Just in case we need to loop pages!
        $this->checkInfo();
        $final = $this->url . $endpoint . '/' . $id;
        $this->debugLog('Using the datadetails url of ' . $final);
        $final .="?" . http_build_query (['key'=> $this->apikey]); //if we are using an api key, set it here!
        $this->debugLog($final);
        $process = curl_init($final);
        switch ($this->authType) :
            case 'basic':
                $this->debugLog('Using Basic Auth Type, ' . $this->userName . ':' . str_repeat("*", strlen($this->password) ) );
                curl_setopt($process, CURLOPT_USERPWD, $this->userName . ":" . $this->password);
                break;
        endswitch;

        curl_setopt($process, CURLOPT_TIMEOUT, 30);
        curl_setopt($process, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($process, CURLOPT_FOLLOWLOCATION, TRUE);


        $return = curl_exec($process);
        $httpCode = curl_getinfo($process, CURLINFO_HTTP_CODE);
        curl_close($process);

        if ($httpCode != 200) {
            $this->debugLog('Curl Return Code:' . $httpCode);
            return false;
        } else {
            $dataObject = json_decode($return);
            if (!$dataObject) {
                $this->debugLog('Unable to interpret data: ' . print_r($dataObject, TRUE));
                return false;
            } else {
                return $dataObject;
            }
        }
    }

    /**
     * Actually go to the endpoint and retrieve the data list
     * @param string $endpoint
     * @param int $page
     * @return mixed
     * @throws Exception
     */
    private function getDataList($endpoint, $parameters = []) {
        $this->debugLog(__FUNCTION__);

        //Just in case we need to loop pages!
        $this->checkInfo();


        $param_string = '';
        if (empty($parameters)) {
            $parameters['mt']=1;
        };
        $param_string = '?' . http_build_query($parameters);
        $final = $this->url . $endpoint . $param_string;
        $this->debugLog('Using the datalist url of ' . $final);
        $process = curl_init($final . '&' . http_build_query ( ['key'=> $this->apikey]) );
        switch ($this->authType):
            case 'basic' :
                $this->debugLog('Using Basic Auth Type, ' . $this->userName . ':' . str_repeat("*", strlen($this->password) ) );
                curl_setopt($process, CURLOPT_USERPWD, $this->userName . ":" . $this->password);
            break;
        endswitch;

        curl_setopt($process, CURLOPT_TIMEOUT, 30);
        curl_setopt($process, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($process, CURLOPT_FOLLOWLOCATION, TRUE);


        $return = curl_exec($process);
        $httpCode = curl_getinfo($process, CURLINFO_HTTP_CODE);
        curl_close($process);

        if ($httpCode != 200) {
            $this->debugLog('Curl Return Code:' . $httpCode);
            return false;
        } else {
            $dataObject = json_decode($return);
            if (!$dataObject) {
                $this->debugLog('Unable to interpret data: ' . print_r($dataObject, TRUE));
                return false;
            } else {
                $this->debugLog('Total Results: ' . $dataObject->results_total . ' vs Returned Results: ' . $dataObject->results_returned);

                if ($dataObject->results_total == $dataObject->results_returned) { //no need to work through pagination, full results returned
                    return $dataObject->data;
                    //$reIndexed = $this->reIndexResult( $dataObject->data );
                    //return $reIndexed;
                } else {
                    $this->debugLog('Need to loop pages');
                    if (!isset($this->tempData)) {
                        $this->tempData = [];
                        $this->totalResults = $dataObject->results_total; //just the first round
                    }
                    //$reIndexed = $this->reIndexResult( $dataObject->data );
                    $this->tempData = array_merge($this->tempData, $dataObject->data);
                    $this->debugLog(count($this->tempData) . ' returns so far!');
                    /*$this->debugLog($this->totalResults);*/
                    if ($this->totalResults > count($this->tempData)) {

                        $parameters['page'] = $dataObject->page + 1;
                        $this->debugLog("Retrieve page " . $parameters['page']);

                        $this->getDataList($endpoint, $parameters);
                    }

                    return $this->tempData;
                }
            }
        }
    }

}
