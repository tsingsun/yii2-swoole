<?php

use \tsingsun\swoole\server\Server;

defined('WEBROOT') or define('WEBROOT', __DIR__ . '/yii2-app-basic/web');
defined('YII_DEBUG') or define('YII_DEBUG', true);
defined('YII_ENV') or define('YII_ENV', 'dev');
defined('COROUTINE_ENV') or define('COROUTINE_ENV', true);

require(__DIR__ . '/yii2-app-basic/vendor/autoload.php');

$config = [
    'class'=>'tsingsun\swoole\server\WebSocketServer',
    'port'=>9502,
    'setting' => [
        'daemonize'=>0,
        'worker_num'=>1,
        'task_worker_num' => 2,
        'pid_file' => __DIR__ . '/yii2-app-basic/runtime/testHttp.pid',
        'log_file' => __DIR__ . '/yii2-app-basic/runtime/logs/swoole.log',
        'debug_mode'=> 1,
        'enable_coroutine' => COROUTINE_ENV
    ],
];

Server::run($config,function (Server $server){
    $starter = new \tsingsun\swoole\bootstrap\WebSocketApp($server);
    //初始化函数独立,为了在启动时,不会加载Yii相关的文件,在库更新时采用reload平滑启动服务器
    $starter->init = function ($bootstrap) {
        require(__DIR__ . '/yii2-app-basic/vendor/tsingsun/yii2-swoole/src/Yii.php');

        $config = yii\helpers\ArrayHelper::merge(
            require(__DIR__ . '/yii2-app-basic/config/web.php'),
            require(__DIR__ . '/yii2-app-basic/config/web-local.php')        );
        Yii::setAlias('@webroot', WEBROOT);
        Yii::setAlias('@web', '/');
        //如果需要原生的swoole Server，可以这样
        Yii::$swooleServer = $bootstrap->getServer()->getSwoole();
        $bootstrap->appConfig = $config;

    };
    $starter->formatData = function ($data) {
        //print_r($data);
        if($data instanceof \yii\web\ForbiddenHttpException){
            return ['errors' => [['code' => $data->getCode(), 'message' => $data->getMessage()]]];
        } elseif($data instanceof \Throwable){
            return ['errors' => [['code' => $data->getCode(), 'message' => $data->getMessage()]]];
        }
            return json_encode($data);
    };
    $server->bootstrap = $starter;
//    $server->getSwoole()->
    $server->start();
});