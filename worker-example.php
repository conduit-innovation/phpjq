<?php
require_once(dirname(__FILE__) . DIRECTORY_SEPARATOR . "class" . DIRECTORY_SEPARATOR . "class-phpjq-server.php");
require_once(dirname(__FILE__) . DIRECTORY_SEPARATOR . "class" . DIRECTORY_SEPARATOR . "class-phpjq-job.php");
require_once(dirname(__FILE__) . DIRECTORY_SEPARATOR . "class" . DIRECTORY_SEPARATOR . "class-phpjq-worker.php");


PHPJQ_Worker::validate_request() or die();

$worker = new PHPJQ_Worker($argv[1], $argv[2]);

$worker->register("test", function($params){
    return $params;
});

$worker->start();