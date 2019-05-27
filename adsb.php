<?php

// Version 0.01

chdir(__DIR__);

// If this is already running, don't make it run again.
$lockfd = fopen('adsb.lck', 'c+');
if (!$lockfd)
 die;
if (!flock($lockfd, LOCK_EX | LOCK_NB, $wouldblock) || $wouldblock) {
 die;
}

// Replace these with the URL to your instance of dump1090
$url1090 = 'http://192.168.0.111:8080/data/aircraft.json';
// If running dump978, uncomment this and change to this URL too.
#$url978 = 'http://192.168.0.111:8978/data/aircraft.json';

require_once __DIR__ . "/vendor/autoload.php";

$tracks = (new MongoDB\Client)->adsb->tracks;
$tracks->createIndex(['position'=>"2dsphere"]);
$tracks->createIndex(['flight'=>1],['sparse'=>true]);

$old_aircraft = Array();

while(1) {
 sleep(1);
 $now = time();

 if (isset($url1090))
  parse_tracks($url1090, 1090);
 if (isset($url978))
  parse_tracks($url978, 978);

 echo "History: " . count($old_aircraft);   
 foreach($old_aircraft as $k => $o) {
  if ($o['last_seen'] < ($now - 90))
   unset($old_aircraft[$k]);
 }
 echo "->" . count($old_aircraft)."\n";
 //print_r($old_aircraft);
}


function parse_tracks($url, $freq) {
  global $old_aircraft, $tracks, $now;

  $raw = json_decode(file_get_contents($url), true);
  if ($raw === NULL) {
      echo "Couldn't grab latest aircraft data\n";
      return;
  }
  $aircraft = $raw['aircraft'];
  $new_aircraft = Array();
  foreach ($aircraft as $plane) {
      if (!isset($plane['hex']) || !isset($plane['messages'])) {
          echo "Missing too much information from this line: " . var_dump($plane) . "\n";
          continue;
      }
      if (isset($old_aircraft[$plane['hex']])) {
          // Is the message count identical?
          if ($old_aircraft[$plane['hex']]['messages'] == $plane['messages']) {
              //echo "Skipping " . $plane['hex'] . " because messages is unchanged " . $plane['messages'] . "\n";
              continue;
          }
     }
     $old_aircraft[$plane['hex']]['messages'] = $plane['messages'];
     $old_aircraft[$plane['hex']]['last_seen'] = $now;
     $plane['timestamp'] = $now;
     $plane['freq'] = $freq;
     if (isset($plane['lat'])) {
      $plane['position']['type'] = 'Point';
      $plane['position']['coordinates'] = [$plane['lon'], $plane['lat']];
      unset($plane['lat']);
      unset($plane['lon']);
     }
     if (isset($plane['flight']))
      $plane['flight'] = trim($plane['flight']);
     $new_aircraft[] = $plane;
  }
  //echo json_encode($new_aircraft, JSON_PRETTY_PRINT);

  if (count($new_aircraft) > 0)
   $i = $tracks->insertMany($new_aircraft);
  echo $freq . ": " . count($new_aircraft) . "/" . count($aircraft) . "  ";
}
