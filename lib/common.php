<?php
require_once(dirname(__FILE__) . "/../config.php");

$error = array();

function connect_mongodb()
{
    global $_config;

    try {
        $c = new Mongo($_config['mongo']['connection_string'], $_config['mongo']['options']);   
        $db = $c->selectDB($_config['mongo']['database']);
    } catch (Exception $e) {
        logger("MongoDB connection failed: " . $e->getMessage());
        return false;
    }
    logger("MongoDB connection success");
    return $db;
}

function logger($message,$level = null)
{
    global $_config;

    $output_method = null;
    if (PHP_SAPI === 'cli') {
        if ($_config['logger']['cli']=='stdout') {
            $output_method = 'cli';
        } elseif ($_config['logger']['cli']=='stderr') {
            $output_method = 'cli';
        }
    } else {
        if ($_config['logger']['http']=='stdout') {
            $output_method = 'http';
        } elseif ($_config['logger']['cli']=='stderr') {
            $output_method = 'http';
        }
    }

    switch ($output_method) {
        case 'cli':
            file_put_contents("php://{$_config['logger']['cli']}", date("Y-m-d H:i:s") . " nar_stats[" . getmypid() . "] LOG {$message}\n");
            break; 
        case 'http':
            // TODO
            break;
        default:
            openlog('nar_stats', LOG_ODELAY | LOG_PID, LOG_USER);
            switch ($level) {
                case 'notice':
                    syslog(LOG_NOTICE, $message);
                    break;
                case 'err':
                    syslog(LOG_ERR, $message);
                    break;
                case 'debug':
                    syslog(LOG_DEBUG, $message);
                    break;
                default:
                    syslog(LOG_INFO, $message);
            }
            closelog();
    }
    return true;
}

?>
