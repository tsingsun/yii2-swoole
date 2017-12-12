<?php

use \tsingsun\swoole\server\WebSocketServer;
/**
 * Created by PhpStorm.
 * User: tsingsun
 * Date: 2017/2/28
 * Time: 上午11:15
 */
defined('WEBROOT') or define('WEBROOT', __DIR__);

require(__DIR__ . '/../../vendor/autoload.php');
$config = [
    'class'=>'tsingsun\swoole\server\swoole\WebSocketServer',
    'serverType'=>'websocket',
    'port'=>9502,
    'setting' => [
        'daemonize'=>0,
//        'reactor_num'=>1,
        'worker_num'=>1,
//        'task_worker_num'=>1,
        'pid_file' => __DIR__ . '/../runtime/testHttp.pid',
        'log_file' => __DIR__.'/../runtime/logs/swoole.log',
        'debug_mode'=> 1,
        'user'=>'tsingsun',
        'group'=>'staff',
    ],
];

WebSocketServer::run($config,function ($nodeConfig){
    $server = WebSocketServer::autoCreate($nodeConfig);
    $starter = new \tsingsun\swoole\bootstrap\WebSocketApp($server);
    //初始化函数独立,为了在启动时,不会加载Yii相关的文件,在库更新时采用reload平滑启动服务器
    $starter->init = function ($bootstrap) {
        defined('YII_DEBUG') or define('YII_DEBUG', true);
        defined('YII_ENV') or define('YII_ENV', 'dev');
        require(__DIR__ . '/../../vendor/yiisoft/yii2/Yii.php');

        $config = yii\helpers\ArrayHelper::merge(
            require(__DIR__ . '/../config/main.php'),
            require(__DIR__ . '/../config/main-local.php')
        );
        Yii::setAlias('@yiiunit/extension/swoole', __DIR__ . '/../');
        Yii::$container = new \tsingsun\swoole\di\Container();
        $bootstrap->app = new \tsingsun\swoole\web\Application($config);
    };
    $server->bootstrap = $starter;
    $server->start();
});