<?php
require __DIR__ . '/vendor/autoload.php';

$loop = \React\EventLoop\Factory::create();

$redis = new \Predis\Client([
    'scheme' => 'tcp',
    'host'   => 'redis',
    'port'   => 6379,
]);

$worker = new \DevCoin\Miner\Worker($loop, $redis);
$worker->run();
