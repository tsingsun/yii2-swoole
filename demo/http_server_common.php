<?php

use \tsingsun\swoole\server\Server;
/**
 * Created by PhpStorm.
 * User: tsingsun
 * Date: 2017/2/28
 * Time: 上午11:15
 */
//路径根据实际文件位置
defined('WEBROOT') or define('WEBROOT', __DIR__.'/web');
defined('YII_DEBUG') or define('YII_DEBUG', true);
defined('YII_ENV') or define('YII_ENV', 'dev');

require(__DIR__ . '/../../vendor/autoload.php');
$config = [
    'class'=>'tsingsun\swoole\server\HttpServer',
    'timeout'=>2,
    'setting' => [
        'daemonize'=>0,
        'max_coro_num'=>300,
        'reactor_num'=>1,
        'worker_num'=>1,
        'pid_file' => __DIR__ . '/runtime/testHttp.pid',
        'log_file' => __DIR__.'/runtime/logs/swoole.log',
        'debug_mode'=> 1,
    ],
];

Server::run($config,function (Server $server){
    $starter = new \tsingsun\swoole\bootstrap\WebApp($server);
    //初始化函数独立,为了在启动时,不会加载Yii相关的文件,在库更新时采用reload平滑启动服务器
    $starter->init = function (\tsingsun\swoole\bootstrap\BaseBootstrap $bootstrap) {
        require(__DIR__ . '/../vendor/tsingsun/yii2-swoole/src/Yii.php');
        //原项目的配置文件
        $config = yii\helpers\ArrayHelper::merge(
            require(__DIR__ . '/../config/main.php'),
            require(__DIR__ . '/../config/main-local.php')
        );
        Yii::setAlias('@webroot', WEBROOT);
        Yii::setAlias('@web', '/');
        $bootstrap->appConfig = $config;
    };
    $server->bootstrap = $starter;
    $server->start();
});