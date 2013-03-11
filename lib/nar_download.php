<?php

function retrive_nar($hostname,$filename)
{
    global $_config;

    $cmd = $_config['navisccli']['command'] . " -h {$hostname} analyzer -archive -file {$filename} -o -path {$_config['tmp_dir']} 2>&1";
    exec($cmd,$output,$ret);
    if ($ret == 0) {
        return true;
    }
    return false;
}

function update_nar_list_db($id,$hostname)
{
    global $_config;

    $cmd = $_config['navisccli']['command'] . " -h {$hostname} analyzer -archive -list";
    exec($cmd,$list,$ret);
    if ($ret == 0) {
        // Shift off the first line it's a header
        $tmp = array_shift($list);
        // Pop off the last line it's the current file
        $tmp = array_pop($list);
    } else {
        logger("Failed to get NAR list {$hostname}");
        return false;
    }

    if (count($list)<=2) {
        logger("No NAR's found on {$hostname}");
    }

    $mdb = connect_mongodb();

    foreach ($list as $row) {
        $tmp = explode(" ",$row,2);
        $data['nar_index'] = $tmp[0];
        $tmp = explode(" ",trim($tmp[1]),2);
        $data['nar_size'] = $tmp[0];
        $tmp = explode(" ",trim($tmp[1]),3);
        $date_tmp = explode("/",$tmp[0]);
        $data['nar_timestamp'] = new MongoDate(strtotime("{$date_tmp[2]}-{$date_tmp[0]}-{$date_tmp[1]} {$tmp[1]}"));
        $data['nar_filename'] = trim($tmp[2]);
        $data['sp_id'] = $id;

        $a = array('nar_filename' => $data['nar_filename']);
        $opt = array('upsert' => true, 'fsync' => true);
        try {
            $mdb->$_config['mongo']['collection']['nar_files']->update($a, array('$set' => $data), $opt);
            $mdb->$_config['mongo']['collection']['nar_files']->ensureIndex(array('nar_filename' => 1),array('unique' => true, 'dropDups' => true, 'background' => false));
            logger("Updated nar list {$data['nar_filename']}");
        } catch (Exception $e) {
            logger("Error doing nar list update",'err');
        }
    }
    return true;
}

?>
