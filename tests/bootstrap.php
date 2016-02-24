<?php

require_once('src/includes/plugins/barzahlen/version/108/paymentmethod/barzahlen/api/loader.php');

define('SHOPID', '10483');
define('PAYMENTKEY', 'de74310368a4718a48e0e244fbf3e22e2ae117f2');
define('NOTIFICATIONKEY', 'e5354004de1001f86004090d01982a6e05da1c12');

function emptyLog()
{
    fclose(fopen(dirname(__FILE__) . "/barzahlen.log", "w"));
}

function writeLog($logFile, $message)
{
    error_log($message, 3, $logFile);
}
