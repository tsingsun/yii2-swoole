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
    'controllerNamespace' => 'swooleunit\controllers',
    'controllerMap' => [],
    'components' => [
        'log' => [
            'targets' => [
                [
                    'class' => 'yii\log\FileTarget',
                    'levels' => ['error', 'warning','trace'],
                    'logVars'=>[],
                ],
            ],
        ],
        'db' => $db,
    ],
    'params' => $params,
];