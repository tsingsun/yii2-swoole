<?php

use \tsingsun\swoole\server\Server;
/**
 * Created by PhpStorm.
 * User: tsingsun
 * Date: 2017/2/28
 * Time: 上午11:15
 */
defined('WEBROOT') or define('WEBROOT', __DIR__);

require(__DIR__ . '/../../vendor/autoload.php');
$config = require(__DIR__ . '/../config/swoole.php');

Server::run($config,function ($nodeConfig){
    $server = Server::autoCreate($nodeConfig);
    $starter = new \tsingsun\swoole\bootstrap\WebApp($server);
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