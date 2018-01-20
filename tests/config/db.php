<?php

return [
    'class'=>'yii\db\Connection',
    'dsn' => 'mysql:host=localhost:3306;dbname=test',
    'username' => 'root',
    'password' => '',
    'charset' => 'utf8',
//            'attributes'=>[
//                PDO::ATTR_STRINGIFY_FETCHES  => false,
//                PDO::ATTR_EMULATE_PREPARES  => false,
//            ],
    'enableSchemaCache' => true,
    'schemaCache'=>'schemaCache',
    'schemaCacheDuration'=>0,
    'poolConfig' => [
        'maxActive' => 1,
    ],
];
