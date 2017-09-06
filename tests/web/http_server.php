<?php
/**
 * Created by PhpStorm.
 * User: tsingsun
 * Date: 2017/2/28
 * Time: 上午11:15
 */
defined('WEBROOT') or define('WEBROOT', __DIR__);

require(__DIR__ . '/../../vendor/autoload.php');
$config = require(__DIR__ . '/../config/swoole.php');

\yii\swoole\server\Server::run($config,function ($nodeConfig){
    $server = \yii\swoole\server\Server::autoCreate($nodeConfig);
    $starter = new \yii\swoole\bootstrap\YiiWeb($server);
    //初始化函数独立,为了在启动时,不会加载Yii相关的文件,在库更新时采用reload平滑启动服务器
    $starter->init = function ($bootstrap) {
        defined('YII_DEBUG') or define('YII_DEBUG', true);
        defined('YII_ENV') or define('YII_ENV', 'dev');
        require(__DIR__ . '/../../vendor/yiisoft/yii2/Yii.php');

        $config = yii\helpers\ArrayHelper::merge(
            require(__DIR__ . '/../config/main.php'),
            require(__DIR__ . '/../config/main-local.php')
        );
        Yii::$container = new \yii\swoole\di\Container();
        $bootstrap->app = new \yii\swoole\web\Application($config);
        Yii::setAlias('@swooleunit', __DIR__ . '/../');
    };
    $server->bootstrap = $starter;
    $server->start();
});