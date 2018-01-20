<?php

$http = new swoole_http_server("127.0.0.1", 9501);

$http->on("request", function ($request, $response) {
    $db = new Swoole\Coroutine\MySQL();
    $db->connect([
        'host' => '127.0.0.1',
        'port' => 3306,
        'user' => 'root',
        'password' => '',
        'database' => 'test',
    ]);
    $a = $db->prepare('select * from new_table');
    if($db->error){
        echo $db->error;
    }
    $ret = $a->execute([]);
//    $ret = $swoole_mysql->query('select 1');
    print_r($ret);
    $response->header("Content-Type", "text/plain");
    $response->end(json_encode($ret));
});

$http->start();