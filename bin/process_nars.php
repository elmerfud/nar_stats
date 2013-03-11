#!/usr/bin/php
<?php
require_once 'Console/CommandLine.php';
require_once(dirname(__FILE__) . "/../lib/common.php");
require_once(dirname(__FILE__) . "/../lib/nar_process.php");

$cmd_line = new Console_CommandLine();
$cmd_line->description = "Bulk NAR downloader";
$cmd_line->version = '0.02 alpha';

$cmd_line->addOption('noop', array(
    'short_name' => '-n',
    'long_name' => '--noop',
    'description' => 'Do no actual message copy work',
    'help_name' => 'NOOP',
    'action' => 'StoreTrue'));
$cmd_line->addOption('process_forks', array(
    'short_name' => '-p',
    'long_name' => '--pfork',
    'description' => 'Number of processes to fork off',
    'help_name' => 'PFORK',
    'default' => 30,
    'action' => 'StoreInt'));
$cmd_line->addOption('verbose', array(
    'short_name' => '-v',
    'long_name' => '--verbose',
    'description' => 'Verbose messages',
    'action' => 'StoreTrue'));

try {
    $cmd_res = $cmd_line->parse();
} catch (Exception $cmd_exc) {
    $cmd_line->displayError($cmd_exc->getMessage());
}

$forks =& $cmd_res->options['process_forks']; // how many children to spawn
$msg_id = msg_get_queue(ftok(__FILE__,'a')); // IPC message queue
$_noop =& $cmd_res->options['noop'];
$_verb =& $cmd_res->options['verbose'];

$pids = array();

for($i=1;$i<=$forks;$i++) { 
    $pids[$i] = pcntl_fork();
    if (!$pids[$i]) {
        for ($f_pre_queue_sleep = 0; 
             $f_pre_queue_sleep <= $_config['ipc']['prework_intervals']; 
             $f_pre_queue_sleep++) {
            sleep($_config['ipc']['prework_sleep']); // wait for IPC queue to get messages
            if (isset($f_q_stat)) { unset($f_q_stat); } // unset an old queue status
            $f_q_stat = msg_stat_queue($msg_id); // get queue status
            if ($f_q_stat['msg_qnum']!=0) {
                logger("Message recieved!");
                break; //messages here break out!
            }
            if ($$f_pre_queue_sleep == $_config['ipc']['prework_intervals']) { // Been a min and no queue messages time to give up
                logger("No initial work, giving up");
                exit(0);
            }
            logger("No initial work found for try {$f_pre_queue_sleep} , waiting");
        }

        $f_mdb = connect_mongodb();
        $f_gdb = $f_mdb->getGridFS();

        while (true) {
            if (msg_receive($msg_id, 0, $f_msg_type, 4096, $f_msg, true, MSG_IPC_NOWAIT)) {
                // Recieved work for queue
                logger("Recieved work {$f_msg[1]}");
                $fh = $f_gdb->findOne(array('nar_filename' => $f_msg[1]));
                $fh->write("{$_config['tmp_dir']}{$f_msg[1]}");
                if (convert_nar_xml($f_msg[1])===false) {
                    logger("Error converting {$f_msg[1]} disabling");
                    $f_mdb->$_config['mongo']['collection']['nar_files']->update(array('_id' => new MongoId($f_msg[0])), array('$set' => array('timestamp_disabled' => new MongoDate())));
                    unlink("{$_config['tmp_dir']}{$f_msg[1]}.xml");
                    unlink("{$_config['tmp_dir']}{$f_msg[1]}");
                } else {
                    logger("Processing XML {$f_msg[1]}");
                    $xml = new XMLReader();
                    if ($xml->open("{$_config['tmp_dir']}{$f_msg[1]}.xml")) {
                        while ($xml->read()) {
                            if (($xml->name == 'object')&&($xml->nodeType == XMLReader::ELEMENT)) {
                                if ($xml->hasAttributes) {
                                    $attrs = array();
                                    while ($xml->moveToNextAttribute()) {
                                        $attrs[$xml->name] = $xml->value;
                                    }
                                    if (isset($attrs['type'])) {
                                        $data = process_XML($xml->readInnerXML());
                                        foreach ($data as $entry) {
                                            if (array_key_exists('Owner Array Name',$entry)===false) {
                                                continue;
                                            }
                                            $entry['_id'] = sha1($entry['sample_date'] . $entry['Object Name'] . $f_msg[2] . $entry['Owner Array Name']);
                                            $entry['timestamp'] = new MongoDate(strtotime($entry['Poll Time']));
                                            $entry['sp_id'] = new MongoId($f_msg[2]);
                                            $entry['type'] = $attrs['type']; 
                                            unset($entry['sample_date'],$entry['Poll Time']);

                                            try {
                                                $f_mdb->$_config['mongo']['collection']['nar_data']->insert($entry,array('safe' => true));
                                                $f_mdb->$_config['mongo']['collection']['nar_data']->ensureIndex(array('sp_id' => 1, 'type' => 1, 'timestamp' => 1));
                                            } catch (MongoCursorException $e) {
                                                logger("Error instering data {$f_msg[1]}");
                                            }
                                        }
                                    }
                                    unset($attrs,$data);
                                }
                            }
                        }
                        $xml->close();
                        unlink("{$_config['tmp_dir']}{$f_msg[1]}.xml");
                        unlink("{$_config['tmp_dir']}{$f_msg[1]}");
                        $f_mdb->$_config['mongo']['collection']['nar_files']->update(array('_id' => new MongoId($f_msg[0])), array('$set' => array('timestamp_processed' => new MongoDate())));
                    } else {
                        logger("Error processing XML {$f_msg[1]}");
                    }
                    unset($xml);
                }
            } else {
                // No work, wait and recheck queue
                for ($f_post_queue_sleep = 0; 
                     $f_post_queue_sleep <= $_config['ipc']['postwork_intervals']; 
                     $f_post_queue_sleep++) {
                    if (isset($f_q_stat)) { unset($f_q_stat); }
                    $q_stat = msg_stat_queue($msg_id);
                    //logger("message in queue {$q_stat['msg_qnum']}");
                    if ($f_q_stat['msg_qnum']==0) {
                        //logger("Waiting for message type {$i}");
                        sleep($_config['ipc']['postwork_sleep']);
                    } else {
                        //logger("Break {$i}");
                        break;
                    }
                    if ($f_post_queue_sleep == $_config['ipc']['postwork_intervals']) {
                        logger("No work for me, giving up");
                        exit(0);
                    }
                } 
                //logger("Continue loop {$i}");
            }
        }
        exit();
    }
}

$mdb = connect_mongodb();
register_shutdown_function('shutdown');

$query = array('timestamp_downloaded' => array('$exists' => true),'timestamp_processed' => array('$exists' => false), 'timestamp_disabled' => array('$exists' => false));
$nar_list = $mdb->$_config['mongo']['collection']['nar_files']->find($query);

foreach ($nar_list as $nar) {
    msg_send($msg_id,1,array($nar['_id']->{'$id'},$nar['nar_filename'],$nar['sp_id']->{'$id'}));
}


// Wait for children
for ($i=1;$i<=$forks;$i++) {
    pcntl_waitpid($pids[$i], $status, WUNTRACED);
}

exit(0);

function shutdown()
{
    global $msg_id;
    logger("Shutting down");
    msg_remove_queue($msg_id);
}

?>

