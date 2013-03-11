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

$_noop =& $cmd_res->options['noop'];
$_verb =& $cmd_res->options['verbose'];

$mdb = connect_mongodb();

// Find hosts we haven't updated in the last 6 hours and pull their file list
$query = array('timestamp_listnars' => array('$lte' => new MongoDate(strtotime("-6 hours"))),
               'active' => array('$mod' => array(2,1)));
$host_list = $mdb->$_config['mongo']['collection']['hosts']->find($query);
logger("Find hosts needing nar list update");
foreach ($host_list as $host) {
    foreach ($host['sp'] as $sp) {
        logger("Update nar list {$sp['_id']} {$sp['hostname']}");
        $ret = update_nar_list_db($sp['_id'],$sp['hostname']);
        if ($ret === true) {
            logger("Update success {$sp['_id']} {$sp['hostname']}");
            $mdb->$_config['mongo']['collection']['hosts']->update(array('_id' => $host['_id']),array('$set' => array('timestamp_listnars' => new MongoDate())));
        }
    }
}
unset($query,$host_list,$ret);

?>
