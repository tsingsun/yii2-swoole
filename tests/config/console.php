<?php
/**
 * Created by PhpStorm.
 * User: tsingsun
 * Date: 2016/12/29
 * Time: 下午3:21
 */

$params = yii\helpers\ArrayHelper::merge(
    require(__DIR__ . '/params.php'),
    require(__DIR__ . '/params-local.php')
);

$db = yii\helpers\ArrayHelper::merge(
    require(__DIR__ . '/db.php'),
    require(__DIR__ . '/db-local.php')
);
return [
    'id' => 'test-console',
    'basePath' => dirname(__DIR__),
    'bootstrap' => ['log'],
    'controllerNamespace' => 'console\controllers',
    'controllerMap' => [
        'migrate' => [
            'class' => 'yii\console\controllers\MigrateController',
            'migrationNamespaces' => [
            ],
            'db'=>[
                'class' => 'yii\db\Connection',
            ],
            //'migrationPath' => null, // allows to disable not namespaced migration completely
        ],
        'swoole'=>[
            'class'=> 'yii\swoole\controllers\SwooleController'
        ],
    ],
    'aliases'=>[
        '@config'=>'@app/config',
    ],
    'components' => [
        'log' => [
            'targets' => [
                [
                    'class' => 'yii\log\FileTarget',
                    'levels' => ['error', 'warning','trace'],
                ],
            ],
        ],
        'db' => $db,
        'eventloop'=>[
            'class'=>'yii\swoole\eventloop\EventLoop',
            'loop'=>[
                'class'=>'yii\swoole\eventloop\LibEventLoop',
            ],
        ],
    ],
    'params' => $params,
];