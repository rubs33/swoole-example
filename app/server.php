<?php
$http = new \Swoole\HTTP\Server('0.0.0.0', getenv('APP_PORT') ?? 3000);

$http->on('start', static function ($server) {
    fwrite(STDOUT, "Swoole http server is started\n");
});

$http->on('request', function ($request, $response) {
    $response->header('Content-Type', 'text/plain');
    $response->end("Hello World\n");

    fwrite(STDOUT, "REQUEST!\n");
});

$http->start();
