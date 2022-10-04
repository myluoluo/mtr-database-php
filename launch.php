<?php
$postUrl = 'https://domains.com/postdata.php';
$requestToken = 'token';
$cmdPath = '/usr/bin/mtr';
$mtrList = ['/usr/sbin/mtr', '/usr/bin/mtr', '/usr/local/sbin/mtr'];
foreach ($mtrList as $path) {
    $testMtr = strtolower(shell_exec($path . " -h"));
    if(strstr($testMtr, 'usage:')) {
        $testMtr = strtolower(shell_exec($path . " --version"));
        if(strstr($testMtr, '0.85')) {
            die('mtr version is too low!');
        }
        $cmdPath = $path;
        // echo "mtr: $cmdPath\n";
    }
}

set_time_limit(0);

if(!function_exists("shell_exec")) die("shell_exec disable!");

// Timezone setting
date_default_timezone_set($config['timezone']);

define('VERSION', "1.0.0");

function fetch ($url, $postdata = null) {
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
    curl_setopt ($ch, CURLOPT_TIMEOUT, 20);

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
        // fwrite(STDOUT, '执行失败，是否重试？(yes/no, def: yes): ');
        // $retry = trim(fgets(STDIN));
        // if(empty($retry) || strtolower($retry) == 'yes' || strtolower($retry) == 'y') {
        //     return fetch($url, $postdata);
        // }
        throw new Exception("HTTP CODE: " . $response['statusCode']);
    }
    
    if(!$response['body'] || !strstr($response['body'], 'add!')) {
        print_r($response);
        throw new Exception('not response!');
    }
    
    return $response['body'];
}

if(
    !isset($_POST['token']) ||
    !isset($_POST['category']) ||
    !isset($_POST['host']) ||
    !isset($_POST['interval']) ||
    !isset($_POST['report-cycles']) ||
    !isset($_POST['tcp']) ||
    !isset($_POST['tcp_port']) ||
    !isset($_POST['hostname']) ||

    $_POST['token'] != $requestToken
) {
    die();
}

$category = (string)$_POST['category'];
$host = (string)$_POST['host'];
$interval = (int)$_POST['interval'];
if($interval < 1 || $interval > 60) $interval = 1;
$reportCycles = (int)$_POST['report-cycles'];
if($reportCycles < 1 || $reportCycles > 999) $reportCycles = 10;
$hostname = (string)$_POST['hostname'];
$tcp = (int)$_POST['tcp'];
$tcpPort = (int)$_POST['tcp_port'];


$mtr = [];
$mtr['host'] = $host;
$mtr['cycles'] = $reportCycles;

// TCP
if($tcp == 1) {
    $mtr['tcp-cmd'] = '--tcp';
    $mtr['port'] = $tcpPort;
    if($mtr['port'] < 1) $mtr['port'] = 80;
    $mtr['port-cmd'] = "--port=" . $mtr['port'];
}

// MTR process
$startDateTime = date("Y-m-d H:i:s");

$cmd = "{$cmdPath} {$mtr['host']} -z -c {$mtr['cycles']} {$mtr['tcp-cmd']} {$mtr['port-cmd']} -rb --json 2>&1";
// $cmd = "{$cmdPath} -rb -c 3 -i 1 --json google.com";
// echo $cmd;
$output = shell_exec($cmd);
$endDateTime = date("Y-m-d H:i:s");

// echo $output;exit;
$data = json_decode($output, true);
// var_dump($data);exit;

// Check JSON
if (!isset($data['report'])) {
    die("Error!: MTR output result is wrong");
}

echo fetch($postUrl, [
    'output'        => $output,
    'endDateTime'   => $endDateTime,
    'startDateTime' => $startDateTime,
    'category'      => $category,
    'hostname'      => $hostname,
    'cmd'           => $cmd,
    'mtr'           => json_encode($mtr),
    'interval'      => $interval
]);

echo "\n";
exit("Process success\n");

