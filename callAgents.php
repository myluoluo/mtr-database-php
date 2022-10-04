<?php

// CLI only
if (php_sapi_name() !== 'cli') 
    die('Access Denied');


include_once "Medoo.class.php";

function fetch ($url, $postdata = null, $timeout = 60) {
    global $API;
    global $proxy, $enableProxy;
    $ch = curl_init ();
    if($enableProxy) {
        curl_setopt ($ch, CURLOPT_PROXY, $proxy);
    }
    curl_setopt ($ch, CURLOPT_URL, $API . $url);
    if (!is_null ($postdata)) {
        curl_setopt ($ch, CURLOPT_POSTFIELDS, http_build_query ($postdata));
    }
    curl_setopt ($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt ($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt ($ch, CURLOPT_HEADER, false);
    curl_setopt ($ch, CURLOPT_TIMEOUT, $timeout);

    $response = [
        'url'           => $API . $url,
        'postData'      => $postdata,
        'body'          => curl_exec   ($ch),
        'curlErrorCode' => curl_errno  ($ch),
        'statusCode'    => curl_getinfo($ch , CURLINFO_HTTP_CODE),
    ];
    curl_close ($ch);
    
    if($response['statusCode'] != 200) {
        print_r($response);
        throw new Exception("HTTP CODE: " . $response['statusCode']);
    }
    
    if(!$response['body'] || !strstr($response['body'], 'add!')) {
        print_r($response);
        throw new Exception('not response!');
    }
    
    return $response['body'];
}

// Config file
$config = require __DIR__ . '/config.inc.php';
$dbConfig = & $config['database'];

$db = new Medoo (array (
    'database_type' => 'mysql',
    'database_name' => $dbConfig['database'],
    'server' => $dbConfig['host'],
    'username' => $dbConfig['username'],
    'password' => $dbConfig['password'],
    'charset' => 'utf8',
    'option' => array (
        PDO::ATTR_PERSISTENT => false
    )
));
$targets = $db->select("targets", "*");
$agents = $db->select("agents", "*");

foreach ($targets as $target) {
    foreach ($agents as $agent) {
        echo "request: $agent[url] to $target[host]\n";
        echo fetch($agent['url'], [
            'token'         => $agent['token'],
            'hostname'      => $agent['hostname'],
            'category'      => $target['category'],
            'host'          => $target['host'],
            'interval'      => $target['interval'],
            'report-cycles' => $target['report-cycles'],
            'tcp'           => $target['tcp'],
            'tcp_port'      => $target['tcp_port'],
        ], 900); // 15m
        echo "\n\n";
    }
}
