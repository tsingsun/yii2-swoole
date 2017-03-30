<?php

return [
    'class'=>'yii\db\ConnectionManager',
    'connections'=>[
        'yak'=>[
            'class' => 'yii\db\Connection',
            'dsn' => '',
            'username' => '',
            'password' => '',
            'charset' => 'utf8mb4',
//            'attributes'=>[
//                PDO::ATTR_STRINGIFY_FETCHES  => false,
//                PDO::ATTR_EMULATE_PREPARES  => false,
//            ],
            'enableSchemaCache' => true,
            'schemaCache'=>'schemaCache',
            'schemaCacheDuration'=>0,
        ]
    ],
    'connectionMap'=>[
        'app\modules\platform\models*' => [
            'connectionsKey' => 'yak',
            'tablePrefix' => '',
            'schemaPrefix' => '',
        ],
        'app\modules\ucenter\models*' => [
            'connectionsKey' => 'yak',
            'tablePrefix' => '',
            'schemaPrefix' => '',
        ],
        'app\modules\rbac\models*' => [
            'connectionsKey' => 'yak',
            'tablePrefix' => '',
            'schemaPrefix' => '',
        ]
    ],
];
