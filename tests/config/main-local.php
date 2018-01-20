<?php
/**
 * Created by PhpStorm.
 * User: tsingsun
 * Date: 2017/2/19
 * Time: 下午6:18
 */
return [
    'components'=>[
//        'session' => [
//            'class' => 'yii\redis\Session',
//            'redis' => [
//                'hostname'=>'127.0.0.1',
//                'database'=>0,
//            ],
//        ],
        'log' => [
            'targets' => [
                [
                    'class' => 'yii\log\FileTarget',
                    'exportInterval'=> 1,
//                    'levels' => ['error'],
                    'logFile' => '@runtime/logs/'.date('ymd').'.log',
                    'logVars'=>[],
                ],
            ],
        ],
    ],
];