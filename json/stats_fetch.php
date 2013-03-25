<?php
require_once(dirname(__FILE__) . "/../lib/common.php");

if (!isset($_POST['id'])) {
    array_push($error,"No ID supplied");
}

if (!isset($_POST['type'])) {
    array_push($error,"No type supplied");
}

if (!isset($_POST['obj'])) {
    array_push($error,"No type supplied");
}

if (count($error)>=1) {
    // Errors already encountered bail
    echo json_encode(array('error' => $error));
    die();
}

$mdb = connect_mongodb();

if ($mdb === false) {
    array_push($error,"DB connection error");
}

if (count($error)>=1) {
    // Errors already encountered bail
    echo json_encode(array('error' => $error));
    die();
}

if (isset($_POST['start_date'])&&isset($_POST['end_date'])) {
    $start = new MongoDate(strtotime("{$_POST['start_date']} 00:00:00"));
    $end = new MongoDate(strtotime("{$_POST['end_date']} 23:59:59"));
} else {
    $start = new MongoDate(strtotime("14 days ago"));
    $end = new MongoDate();
}

$find = array('sp_id' => new MongoId($_POST['id']),
                'type' => $_POST['type'],
           "timestamp" => array('$gt' => $start, '$lte' => $end),
           'Object Name' => $_POST['obj']);
$color = array();
try {
    $output = array();
    $res = $mdb->$_config['mongo']['collection']['nar_data']->find($find)->sort(array('timestamp' => 1));
    foreach ($res as $row) {
        $raw[] = $row;
        $time = $row['timestamp']->sec * 1000;
        $obj_name = $row['Object Name'];
        unset($row['_id'],$row['Object Name'],$row['Owner Array Name'],$row['type'],$row['timestamp'],$row['sp_id']);
        foreach ($row as $_name => $val) {
            if (!isset($color[$_name])) {
                $color[$_name] = count($color);
            }
            $name = $obj_name . " " . $_name;
            if (!is_array($output[$name])) {
                $output[$name] = array();
            }
            if (!is_array($output[$name]['data'])) {
                $output[$name]['data'] = array();
            }
            $output[$name]['label'] = $name;
            $output[$name]['color'] = $color[$_name]; 
            array_push($output[$name]['data'],array($time,(float)$val));
        } 
    }
} catch (Exception $e) {
    array_push($error,"MongoDB error " . $e->getMessage());
}

if (count($error)>=1) {
    // Errors already encountered bail
    echo json_encode(array('error' => $error));
} else {
    //var_dump($raw);
    echo json_encode($output);
}


?>
