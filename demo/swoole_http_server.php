<?php

use \tsingsun\swoole\server\Server;

/**
 * Created by PhpStorm.
 * User: tsingsun
 * Date: 2017/2/28
 * Time: 上午11:15
 */
defined('WEBROOT') or define('WEBROOT', __DIR__ . '/yii2-app-basic/web');
defined('YII_DEBUG') or define('YII_DEBUG', true);
defined('YII_ENV') or define('YII_ENV', 'dev');

defined('COROUTINE_ENV') or define('COROUTINE_ENV', true);

require(__DIR__ . '/yii2-app-basic/vendor/autoload.php');
$config = [
    'class' => 'tsingsun\swoole\server\HttpServer',
//    'timeout'=>2,
    'setting' => [
        'daemonize' => 0,
        'max_coro_num' => 3000,
        'reactor_num' => 1,
        'worker_num' => 1,
        'task_worker_num' => 2,
        'pid_file' => __DIR__ . '/yii2-app-basic/runtime/testHttp.pid',
        'log_file' => __DIR__ . '/yii2-app-basic/runtime/logs/swoole.log',
        'debug_mode' => 0,
        'enable_coroutine' => COROUTINE_ENV

    ],
];

Server::run($config, function (Server $server) {
    $starter = new \tsingsun\swoole\bootstrap\WebApp($server);
    //初始化函数独立,为了在启动时,不会加载Yii相关的文件,在库更新时采用reload平滑启动服务器
    $starter->init = function (\tsingsun\swoole\bootstrap\BaseBootstrap $bootstrap) {
        require(__DIR__ . '/yii2-app-basic/vendor/tsingsun/yii2-swoole/src/Yii.php');

//        $config = require(__DIR__ . '/yii2-app-basic/config/web.php');
        //if you has local config
        $config = yii\helpers\ArrayHelper::merge(
            require(__DIR__ . '/yii2-app-basic/config/web.php'),
            require(__DIR__ . '/yii2-app-basic/config/web-local.php')
        );
        Yii::setAlias('@webroot', WEBROOT);
        Yii::setAlias('@web', '/');
        //如果需要原生的swoole Server，可以这样
        Yii::$swooleServer = $bootstrap->getServer()->getSwoole();
        $bootstrap->appConfig = $config;

    };
    //如果需要swoole Server
    $server->getSwoole()->on("Task", function (swoole_server $serv, $task_id, $from_id, $data) {
        echo "Tasker进程接收到数据";
        echo "#{$serv->worker_id}\tonTask: [PID={$serv->worker_pid}]: task_id=$task_id, data_len=" . strlen($data) . "." . PHP_EOL;
        $serv->finish($data);
    }
    );
    $server->getSwoole()->on("Finish", function (swoole_server $serv, $task_id, $data) {
        echo "Task#$task_id finished, data_len=" . strlen($data) . PHP_EOL;
    });
    $server->bootstrap = $starter;
    $server->start();
});