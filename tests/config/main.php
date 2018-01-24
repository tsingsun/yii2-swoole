<?php

use yii\web\Response;

$params = require(__DIR__ . '/params.php');
$db  = yii\helpers\ArrayHelper::merge(
    require(__DIR__ . '/db.php'),
    require(__DIR__ . '/db-local.php')
);
$config = [
    'id' => 'basic',
    'basePath' => dirname(__DIR__),
    'language'=>'en',
    'sourceLanguage'=>'zh-CN',
    'timeZone'=>'Asia/Shanghai',
    'controllerNamespace' => 'yiiunit\extension\swoole\controllers',
    'bootstrap' => [
        'log',
        'contentNegotiator'=>[
            'class' => 'yii\filters\ContentNegotiator',
            'formats' => [
                'text/html' => Response::FORMAT_HTML,
                'application/json' => Response::FORMAT_JSON,
                'application/xml' => Response::FORMAT_XML,
            ],
            'languages' => [
                'en',
                'zh-CN',
            ],
        ],
    ],
    'vendorPath' => __DIR__.'/../../vendor',
    'components' => [
        'request' => [
            // !!! insert a secret key in the following (if it is empty) - this is required by cookie validation
            'cookieValidationKey' => 'B1uah2HVO-CEdFt5o-G46_4-dL3aEo_K',
        ],
        'reponse' =>[
            'class'=>'tsingsun\swoole\web\Response'
        ],
        'session' =>[
//            'class'=> 'tsingsun\swoole\web\Session',
            'class'=> 'yii\web\CacheSession',
        ],
        'cache' => [
            'class' => 'yii\caching\FileCache',
        ],
        'user' => [
            'identityClass' => 'yiiunit\extension\swoole\TestIdentity',
            'enableAutoLogin' => true,
        ],
        'errorHandler' => [
            'class'=> 'tsingsun\swoole\web\ErrorHandler',
//            'errorAction' => 'site/error',
        ],
//        'mailer' => [
//            'class' => 'yii\swiftmailer\Mailer',
//            // send all mails to a file by default. You have to set
//            // 'useFileTransport' to false and configure a transport
//            // for the mailer to send real emails.
//            'useFileTransport' => true,
//        ],
        'log' => [
            'traceLevel' => YII_DEBUG ? 3 : 0,
        ],
        'db' => $db,
        'urlManager' => [
            'enablePrettyUrl' => true,
            'showScriptName' => false,
            'rules' => [
            ],
        ],
    ],
    'params' => $params,
];

if (YII_ENV_DEV) {
}

return $config;
