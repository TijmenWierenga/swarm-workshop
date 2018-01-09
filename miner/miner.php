<?php
require __DIR__ . '/vendor/autoload.php';

$miningSpeed = DevCoin\Miner\Worker::MINING_SPEED_SLOW;

if (file_exists(__DIR__ . '/config/config.php')) {
    $contents = file(__DIR__ . '/config/config.php');
    $config = new \DevCoin\Miner\Config($contents);
    $miningSpeed = $config->get('speed');
}

$loop = \React\EventLoop\Factory::create();

$redis = new \Predis\Client([
    'scheme' => 'tcp',
    'host'   => 'redis',
    'port'   => 6379,
]);

$worker = new \DevCoin\Miner\Worker($loop, $redis, $miningSpeed);
$worker->run();
