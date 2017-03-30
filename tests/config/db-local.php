<?php

return [
    'connections'=>[
        'yak'=>[
            'dsn' => 'mysql:host=117.29.166.222:4360;dbname={schemaPrefix}yak',
            'username' => 'dev_app',
            'password' => 'eping',
//            'dsn' => 'mysql:host=localhost:3306;dbname={schemaPrefix}yak',
//            'username' => 'root',
//            'password' => '',
//            'on afterOpen' => function($event) {
//                $event->sender->createCommand("SET time_zone = '+9:00'")->execute();
//            }
        ],
        'roboAdvisor'=>[
            'class' => 'yii\db\Connection',
            'dsn' => 'mysql:host=192.168.0.12:3306;dbname={schemaPrefix}robo_demo',
            'username' => 'roboweb',
            'password' => 'roboweb123456!',
            'enableSchemaCache' => true,
            'schemaCache'=>'schemaCache',
            'schemaCacheDuration'=>0,
        ],
        'robo_chat'=>[
            'class' => 'yii\db\Connection',
            'dsn' => 'mysql:host=117.29.166.222:1206;dbname={schemaPrefix}robo_chat',
            'charset' => 'utf8',
            'username' => 'yiping',
            'password' => 'Yiping@123',
            'enableSchemaCache' => true,
            'schemaCache'=>'schemaCache',
            'schemaCacheDuration'=>0,
        ],
    ],
    'connectionMap'=>[
        'app\modules\roboAdvisor\models*' => [
            'connectionsKey' => 'roboAdvisor',
            'tablePrefix' => '',
            'schemaPrefix' => '',
        ],
        'app\modules\relationship\models*' => [
            'connectionsKey' => 'robo_chat',
            'tablePrefix' => '',
            'schemaPrefix' => '',
        ],

    ]
];
