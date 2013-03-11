<?php
require_once(dirname(__FILE__) . "/../lib/common.php");

$mdb = connect_mongodb();

if ($mdb === false) {
    array_push($error,"DB connection error");
}

if (count($error)>=1) {
    // Errors already encountered bail
    echo json_encode(array('error' => $error));
    die();
}

try {
    $output = array();
    $res = $mdb->$_config['mongo']['collection']['hosts']->find();
    foreach ($res as $row) {
        array_push($output,$row);
    }
} catch (Exception $e) {
    array_push($error,"MongoDB error " . $e->getMessage());
}

if (count($error)>=1) {
    // Errors already encountered bail
    echo json_encode(array('error' => $error));
} else {
    echo json_encode($output);
}

?>
