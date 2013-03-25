<?php
require_once(dirname(__FILE__) . "/../lib/common.php");

if (!isset($_POST['id'])) {
    array_push($error,"No ID supplied");
}

if (!isset($_POST['type'])) {
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
          "timestamp" => array('$gt' => $start, '$lte' => $end));
try {
    $res = $mdb->$_config['mongo']['collection']['nar_data']->distinct('Object Name', $find);
} catch (Exception $e) {
    array_push($error,"MongoDB error " . $e->getMessage());
}

if (count($error)>=1) {
    // Errors already encountered bail
    echo json_encode(array('error' => $error));
} else {
    //var_dump($raw);
    echo json_encode($res);
}

?>
