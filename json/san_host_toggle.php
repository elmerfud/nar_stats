<?php
require_once(dirname(__FILE__) . "/../lib/common.php");

if (!isset($_GET['id'])) {
    array_push($error,"No ID");
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

$query = array('_id' => new MongoId($_GET['id']));
$data = array('$inc' => array('active' => 1));
$options = array('w' => 1, 'fsync' => 'true');

try {
    $output = array();
    $res = $mdb->$_config['mongo']['collection']['hosts']->update($query,$data,$options);
    $output = array('success' => $res);
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
