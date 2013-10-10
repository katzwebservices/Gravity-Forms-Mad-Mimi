<?php

class MadMimi_SuperClass extends MadMimi {

    public $lastError = null;

    function __construct($email = null, $api_key = null, $debug=false) {
        parent::__construct($email, $api_key, $debug = false);
    }

    function getLastError() {
        return $this->lastError;
    }

    function DoRequest($path, $body, $return_status = false, $method = 'GET', $mail = false) {

        // If we want to send the complete path, let us!
        if(!preg_match('/https?/ism', $path)) {
            $url = "https://api.madmimi.com{$path}";
            if ($mail == false) { $url = "http://api.madmimi.com{$path}"; }
        } else {
            $url = $path;
        }

        if(empty($method) || !in_array($method, array('POST', 'PUT', 'DELETE', 'GET'))) { $method = 'POST'; }

        if($method == 'GET') {
            $url .= '?'.http_build_query($body);
            $body = '';
        }

        $headers = array(
            'Expect' => ''
        );

        // Set SSL verify to false because of server issues.
        $args = array(
            'body'      => $body,
            'method'    => strtoupper($method),
            'headers'   => $headers,
            'sslverify' => false,
            'timeout'   => 10
        );

        $result = wp_remote_request($url, $args);

        #echo '<pre>';
        #print_r(array('url' => $url, 'args' => $args, 'result' => $result));
        #echo '</pre>';

        $this->lastRequest = $result;

        if(is_wp_error($result)) {
            $this->lastError = $result->get_error_message();
            return false;
        }

        $body = wp_remote_retrieve_body($result);

        if((int)$result['response']['code'] > 299) {
            $this->lastError = $body;
            return false;
        }

        return $body;
    }
}