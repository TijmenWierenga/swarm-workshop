<?php
require_once __DIR__ . '/vendor/autoload.php';

$redis = new \Predis\Client([
    'scheme' => 'tcp',
    'host'   => 'redis',
    'port'   => 6379,
]);

$loop = React\EventLoop\Factory::create();

$server = new \React\Http\Server(function (\Psr\Http\Message\ServerRequestInterface $request) use ($redis) {
    try {
        return new \React\Http\Response(200, [
            'Content-Type' => 'application/json',
            'Access-Control-Allow-Origin' => '*'
        ], json_encode([
            "status" => 200,
            "message" => "Successfully retrieved blocks.",
            "data" => [
                "blocks" => $redis->get('blocks')
            ]
        ]));
    } catch (\Exception $e) {
        return new \React\Http\Response(500, [
            'Content-Type' => 'application/json',
            'Access-Control-Allow-Origin' => '*'
        ], json_encode([
            "status" => 500,
            "message" => "Server error",
            "errors" => [
                $e->getMessage()
            ]
        ]));
    }
});

$socket = new React\Socket\Server('0.0.0.0:9000', $loop);
$server->listen($socket);

$loop->run();
