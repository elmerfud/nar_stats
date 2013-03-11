<?php
require_once(dirname(__FILE__) . "/../lib/common.php");

if (!isset($_GET['id'])) {
    array_push($error,"No ID supplied");
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

$start = new MongoDate(strtotime("7 days ago"));
$end = new MongoDate();
$find = array('sp_id' => new MongoId($_GET['id']),
           "timestamp" => array('$gt' => $start, '$lte' => $end));
try {
    $res = $mdb->$_config['mongo']['collection']['nar_data']->distinct('type', $find);
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
