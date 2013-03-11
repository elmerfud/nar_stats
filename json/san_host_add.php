<?php
require_once(dirname(__FILE__) . "/../lib/common.php");

if (!isset($_POST['sanname'])||trim($_POST['sanname']) == '') {
    array_push($error,"SAN name missing");
}

if (!isset($_POST['spa'])||trim($_POST['spa']) == '') {
    array_push($error,"SPA missing");
}

if (!isset($_POST['spb'])||trim($_POST['spb']) == '') {
    array_push($error,"SPB missing");
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

$data = array('active' => 0,
              'sanname' => $_POST['sanname'],
              'sp' => array(array('hostname' => $_POST['spa'],'_id' => new MongoID()),
                            array('hostname' => $_POST['spb'],'_id' => new MongoID())),
              'timestamp_added' => new MongoDate());
$options = array('w' => 1, 'fsync' => 'true');

try {
    $res = $mdb->$_config['mongo']['collection']['hosts']->insert($data,$options);
    $mdb->$_config['mongo']['collection']['hosts']->ensureIndex(array('active' => true));
    $mdb->$_config['mongo']['collection']['hosts']->ensureIndex(array('sp._id' => 1), array('unique' => 1));
    $mdb->$_config['mongo']['collection']['hosts']->ensureIndex(array('sp.hostname' => 1), array('unique' => 1));
} catch (Exception $e) {
    logger("MongoDB Fail " . $e->getMessage());
    array_push($error,"MongoDB Fail " . $e->getMessage());
}

if (count($error)>=1) {
    echo json_encode(array('error' => $error));
} else {
    echo json_encode(array('success' => $res));
}


?>
