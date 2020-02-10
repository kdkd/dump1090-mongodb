<?php

chdir(__DIR__);

// If this is already running, don't make it run again.
$lockfd = fopen('beast.lck', 'c+');
if (!$lockfd)
 die;
if (!flock($lockfd, LOCK_EX | LOCK_NB, $wouldblock) || $wouldblock) {
 die;
}

$ip1090 = '192.168.0.111';
$port1090 = 30003;

require_once __DIR__ . "/vendor/autoload.php";

$tracks = (new MongoDB\Client)->adsb->raw;
$tracks->createIndex(['position'=>"2dsphere"]);
$tracks->createIndex(['hex'=>1]);
$tracks->createIndex(['callsign'=>1],['sparse'=>true]);
$tracks->createIndex(['emergency'=>1],['sparse'=>true]);


while(1) {
 if (!$socket1090 && $port1090) {
  $socket1090 = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
  socket_set_block($socket1090);
  $r = socket_connect($socket1090, $ip1090, $port1090);
  if (!$r) {
   socket_close($socket1090);
   $socket1090 = false;
   continue;
  }
 }
 $line = socket_read($socket1090, 2048, PHP_NORMAL_READ);
 if ($line === FALSE) {
  echo "Socket error " . socket_strerror(socket_last_error($socket1090)) . "\n";
  $socket1090 = false;
  continue;
 }
 $line = trim($line);
 if ($line == '')
  continue;
 $a = explode(',', $line);
 unset($msg);
 $msg['type'] = (int)$a[1];
 $msg['hex'] = $a[4];

 $gen = (new DateTime($a[6] . ' ' . $a[7]));
 $msg['dategen'] = (new MongoDB\BSON\UTCDateTime($gen));

 $gen = (new DateTime($a[8] . ' ' . $a[9]));
 $msg['datelog'] = (new MongoDB\BSON\UTCDateTime($gen));

 if ($a[10] !== '')
  $msg['callsign'] = trim($a[10])."";
 if ($a[11] !== '')
  $msg['altitude'] = (int)$a[11];
 if ($a[12] !== '')
  $msg['groundspeed'] = (int)$a[12];
 if ($a[13] !== '')
  $msg['track'] = (int)$a[13];
 if ($a[14] !== '') {
  $msg['position']['type'] = 'Point';
  $msg['position']['coordinates'] = [(float)$a[15], (float)$a[14]];
 } 
 if ($a[16] !== '')
  $msg['verticalrate'] = (int)$a[16];
 if ($a[17] !== '')
  $msg['squawk'] = (int)$a[17];
 if (($a[18] !== '') && ($a[18] !== '0'))
  $msg['alert'] = (int)$a[18];
 if (($a[19] !== '') && ($a[19] !== '0'))
  $msg['emergency'] = (int)$a[19];
 if (($a[20] !== '') && ($a[20] !== '0'))
  $msg['ident'] = (int)$a[20];
 if (($a[21] !== '') && ($a[21] !== '0'))
  $msg['ground'] = (int)$a[21];

 //print_r(json_encode($msg, JSON_PRETTY_PRINT)); 
 $i = $tracks->insertOne($msg);
 
}


