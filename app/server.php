<?php
use Swoole\Database\{PDOConfig, PDOPool};
use Swoole\Http\{Request, Response, Server};

$port = intval(getenv('APP_PORT') ?: 3000);
$http = new Server('0.0.0.0', $port);

// Atomic counter
$counter = new Swoole\Atomic(0);

// Get DB config
$dbConf = parse_url(getenv('DB'));
parse_str($dbConf['query'], $dbOpts);
$charsets = [
    'UTF-8' => 'UTF8',
];

// Wait DB
/*
while (!($con = fsockopen($dbConf['host'], $dbConf['port'], $errcode, $errstr, 3))) {
    sleep(1);
}
fclose($con);
*/

// Prepare DB pool
$dbPoolConf = (new PDOConfig())
    ->withHost($dbConf['host'])
    ->withPort($dbConf['port'])
    ->withDbName(ltrim($dbConf['path'], '/'))
    ->withCharset($charsets[$dbOpts['charset']])
    ->withUsername($dbConf['user'])
    ->withPassword($dbConf['pass']);

$dbPool = new PDOPool($dbPoolConf, 10);

//$dsn = sprintf('%s:host=%s;port=%d;dbname=%s;charset=%s', $dbConf['scheme'], $dbConf['host'], $dbConf['port'], ltrim($dbConf['path'], '/'), $charsets[$dbOpts['charset']]);
//$pdo = new \PDO($dsn, $dbConf['user'], $dbConf['pass']);

$http->on('start', static function ($server) use ($port, $dsn) {
    fwrite(STDOUT, "Swoole http server is started on port {$port}\n");
});

$http->on('request', static function (Request $request, Response $response) use ($dbPool, $counter, $dbConf) {
    $counter->add(1);

    fwrite(STDOUT, "REQUEST {$counter->get()}\n");
    //var_dump($request->server);

    // Route to fetch 2 records from db in parallel using "batch"
    if ($request->server['request_uri'] === '/1') {
        $results = \Swoole\Coroutine\batch([
            static function () use ($dbPool) {
                $pdo = $dbPool->get();
                defer(static function () use ($pdo, $dbPool) {
                    $dbPool->put($pdo);
                });
                $stmt = $pdo->query('SELECT * FROM books WHERE id = 1');
                return $result = $stmt ? $stmt->fetchObject() : null;
            },
            static function () use ($dbPool) {
                $pdo = $dbPool->get();
                defer(static function () use ($pdo, $dbPool) {
                    $dbPool->put($pdo);
                });
                $stmt = $pdo->query('SELECT * FROM books WHERE id = 2');
                return $result = $stmt ? $stmt->fetchObject() : null;
            },
        ]);

        $response->status(200);
        $response->header('Content-Type', 'application/json');
        $response->header('X-Counter', $counter->get());
        $response->end(json_encode($results));
        return;
    }

    // Route to fetch 2 records from db in parallel using Swoole\Coroutine\MySQL::setDefer
    if ($request->server['request_uri'] === '/2') {
        $mysqlConf = [
            'host' => $dbConf['host'],
            'user' => $dbConf['user'],
            'password' => $dbConf['pass'],
            'database' => ltrim($dbConf['path'], '/'),
        ];

        $mysql1 = new \Swoole\Coroutine\MySQL();
        $mysql1->connect($mysqlConf);
        $mysql1->setDefer();
        $mysql1->query('SELECT * FROM books WHERE id = 1');

        $mysql2 = new \Swoole\Coroutine\MySQL();
        $mysql2->connect($mysqlConf);
        $mysql2->setDefer();
        $mysql2->query('SELECT * FROM books WHERE id = 2');

        $mysqlRes1 = current($mysql1->recv());
        $mysqlRes2 = current($mysql2->recv());

        $response->status(200);
        $response->header('Content-Type', 'application/json');
        $response->header('X-Counter', $counter->get());
        $response->end(json_encode([$mysqlRes1, $mysqlRes2]));
        return;
    }

    // Route to fetch 2 records from db in parallel using "go" + "chan"
    if ($request->server['request_uri'] === '/3') {
        $ch = new chan(2);

        go(static function () use ($response, $ch, $counter) {
            $r1 = $ch->pop();
            $r2 = $ch->pop();

            $response->status(200);
            $response->header('Content-Type', 'application/json');
            $response->header('X-Counter', $counter->get());
            $response->end(json_encode([$r1, $r2]));
        });

        go(static function () use ($dbPool, $ch) {
            $pdo = $dbPool->get();
            defer(static function () use ($dbPool, $pdo) {
                $dbPool->put($pdo);
            });
            $stmt = $pdo->query('SELECT * FROM books WHERE id = 1');
            $ch->push($stmt ? $stmt->fetchObject() : null);
        });

        go(static function () use ($dbPool, $ch) {
            $pdo = $dbPool->get();
            defer(static function () use ($dbPool, $pdo) {
                $dbPool->put($pdo);
            });
            $stmt = $pdo->query('SELECT * FROM books WHERE id = 2');
            $ch->push($stmt ? $stmt->fetchObject() : null);
        });
    }
});

\Swoole\Runtime::enableCoroutine();

$http->start();
