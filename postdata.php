<?php
if(!in_array($_SERVER['REMOTE_ADDR'], [
    '127.0.0.1',
])) {
    die($_SERVER['REMOTE_ADDR']);
}

// Timezone setting
date_default_timezone_set($config['timezone']);

$output         = (string)$_POST['output'];
$endDateTime    = (string)$_POST['endDateTime'];
$startDateTime  = (string)$_POST['startDateTime'];
$category       = (string)$_POST['category'];
$hostname       = (string)$_POST['hostname'];
$cmd            = (string)$_POST['cmd'];
$interval       = (int)$_POST['interval'];
$mtr            = json_decode((string)$_POST['mtr'], true);


define('VERSION', "1.0.0");

// Config file
$config = require __DIR__ . '/config.inc.php';
$dbConfig = & $config['database'];


// Database connection
try {

    // Database connection
    $conn = new PDO("mysql:host={$dbConfig['host']};dbname={$dbConfig['database']}", $dbConfig['username'], $dbConfig['password']);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_SILENT);

} catch (PDOException $e) {
    
    die("Error!: " . $e->getMessage() . "\n");
}

// echo $output;exit;
$data = json_decode($output, true);
// var_dump($data);exit;

// Check JSON
if (!isset($data['report'])) {
    die("Error!: MTR output result is wrong");
}

$asPath = [];
foreach ($data['report']['hubs'] as $hub) {
    if(!isset($hub['ASN']) || $hub['ASN'] == 'AS???') continue;
    if(end($asPath) == $hub['ASN']) continue;
    $asPath[] = $hub['ASN'];
}
$asPath = implode(' -> ', $asPath);

// Get end hub
$endHub = end($data['report']['hubs']);
// Save to database
$insertMap = [
    'start_datetime'    => $startDateTime,
    'end_datetime'      => $endDateTime,
    'category'          => $category,
    // 'source'         => $data['report']['mtr']['src'],
    'source'            => $hostname,
    'destination'       => $data['report']['mtr']['dst'],
    'interval'          => $interval,
    'host'              => $endHub['host'],
    'asn'               => $endHub['ASN'],
    'as_path'           => $asPath,
    'mtr_loss'          => $endHub['Loss%'],
    'mtr_sent'          => $endHub['Snt'],
    'mtr_avg'           => $endHub['Avg'],
    'mtr_best'          => $endHub['Best'],
    'mtr_worst'         => $endHub['Wrst'],
    'mtr_raw'           => $output,
    'command'           => $cmd,
];

$sql = 'INSERT INTO ' . $dbConfig['table'] . ' (`sn`, `start_datetime`, `end_datetime`, `interval`, `category`, `source`, `destination`, `host`, `asn`, `as_path`, `mtr_loss`, `mtr_sent`, `mtr_avg`, `mtr_best`, `mtr_worst`, `mtr_raw`, `command`) ';
// $sql = "INSERT INTO {$dbConfig['table']} (sn, start_datetime, end_datetime, interval, category, source, destination, host, asn, mtr_loss, mtr_sent, mtr_avg, mtr_best, mtr_worst, mtr_raw, command) 
$sql .= "VALUES (NULL, :start_datetime, :end_datetime, :interval, :category, :source, :destination, :host, :asn, :as_path, :mtr_loss, :mtr_sent, :mtr_avg, :mtr_best, :mtr_worst, :mtr_raw, :command)";
$stmt = $conn->prepare($sql);
foreach ($insertMap as $key => $value) {
    $stmt->bindValue(":{$key}", $value);
}
$result = $stmt->execute();
if ($result === false) {
    // $stmt->debugDumpParams();
    die("Error!: " . $stmt->errorInfo()[2] . "\n");
}
echo "add!";
