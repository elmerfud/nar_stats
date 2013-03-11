#!/usr/bin/php
<?php
require_once 'Console/CommandLine.php';
require_once(dirname(__FILE__) . "/../lib/common.php");
require_once(dirname(__FILE__) . "/../lib/nar_download.php");

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
    'default' => 5,
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
    exit(1);
}

$forks =& $cmd_res->options['process_forks']; // how many children to spawn
$msg_id = msg_get_queue(ftok(__FILE__,'b')); // IPC message queue
$_noop =& $cmd_res->options['noop'];
$_verb =& $cmd_res->options['verbose'];

$pids = array();

for($i=1;$i<=$forks;$i++) { 
    $pids[$i] = pcntl_fork();
    if (!$pids[$i]) {
        // Initial wait loop for messages to appear in IPC queue
        for ($f_pre_queue_sleep = 0; 
             $f_pre_queue_sleep <= $_config['ipc']['prework_intervals']; 
             $f_pre_queue_sleep++) {
            sleep($_config['ipc']['prework_sleep']);
            if (isset($f_q_stat)) { unset($f_q_stat); }
            logger("Checking queue for initial work");
            $f_q_stat = msg_stat_queue($msg_id); 
            if ($f_q_stat['msg_qnum']!=0) {
                logger("Message recieved!");
                break; //messages here break out!
            }
            if ($f_pre_queue_sleep == $_config['ipc']['prework_intervals']) { 
                logger("No initial work, giving up");
                exit(0);
            }
            logger("No initial work found for try {$f_pre_queue_sleep} , waiting");
        }

        $f_mdb = connect_mongodb();
        $f_gdb = $f_mdb->getGridFS();

        while (true) {
            if (msg_receive($msg_id, $i, $f_msg_type, 4096, $f_msg, true, MSG_IPC_NOWAIT)) {
                // Recieved work for queue
                logger("Recieved work on {$i} of {$f_msg_type} for {$f_msg[0]}");
                if (retrive_nar($f_msg[0],$f_msg[1])===true) {
                    logger("Fetched NAR {$f_msg[1]}");
                    $f_gridfs['nar_filename'] = $f_msg[1];
                    $f_gridfs['nar_id'] = new MongoId($f_msg[2]);
                    $f_gdb->storeFile("{$_config['tmp_dir']}{$f_msg[1]}",$f_gridfs);
                    $f_gdb->ensureIndex(array('nar_filename' => 1),array('unique' => true, 'dropDups' => true));
                    $f_mdb->$_config['mongo']['collection']['nar_files']->update(array('_id' => new MongoId($f_msg[2])), array('$set' => array('timestamp_downloaded' => new MongoDate()))); 
                    unlink("{$_config['tmp_dir']}{$f_msg[1]}");
                } else {
                    logger("Failed to fetch NAR {$f_msg[1]}");
                    $f_mdb->$_config['mongo']['collection']['nar_files']->update(array('_id' => new MongoId($f_msg[2])), array('$set' => array('timestamp_disabled' => new MongoDate())));
                }
            } else {
                logger("Message queue empty, waiting");
                // No work, wait and recheck queue
                for ($f_post_queue_sleep = 0; 
                     $f_post_queue_sleep <= $_config['ipc']['postwork_intervals']; 
                     $f_post_queue_sleep++) {
                    if (isset($q_stat)) { unset($q_stat); }
                    logger("Check message queue");
                    $f_q_stat = msg_stat_queue($msg_id);
                    //logger("message in queue {$q_stat['msg_qnum']}");
                    if ($f_q_stat['msg_qnum']==0) {
                        logger("Waiting for queue message type {$i} try {$f_post_queue_sleep}");
                        sleep($_config['ipc']['postwork_sleep']);
                    } else {
                        logger("Found messages in queue");
                        break;
                    }
                    if ($f_post_queue_sleep == $_config['ipc']['postwork_intervals']) {
                        logger("No work for me, giving up {$i}");
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

logger("Find nar files needing downloaded");
$nar_list = $mdb->$_config['mongo']['collection']['nar_files']->find(array('timestamp_downloaded' => array('$exists' => false), 'timestamp_disabled' => array('$exists' => false)));
$i = 1;
foreach ($nar_list as $nar) {
    $host = $mdb->$_config['mongo']['collection']['hosts']->findOne(array('sp._id' => $nar['sp_id']),array('sp'));
    foreach ($host['sp'] as $sp) {
        if($sp['_id']->{'$id'} == $nar['sp_id']->{'$id'}) {
            if (!isset($des_msg_type[$sp['hostname']])) {
                $des_msg_type[$sp['hostname']] = $i;
                $i++;
            }
            $fixed_msg_type = $des_msg_type[$sp['hostname']] % $forks;
            if ($fixed_msg_type == 0) { 
                $fixed_msg_type = $forks;                
            }
            logger("Queue work of type {$fixed_msg_type} for {$sp['hostname']} {$nar['nar_filename']}");
            msg_send($msg_id,$fixed_msg_type,array($sp['hostname'],$nar['nar_filename'],$nar['_id']->{'$id'}));
        }
    }
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

