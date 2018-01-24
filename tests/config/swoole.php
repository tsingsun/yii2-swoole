<?php
/**
 * Created by PhpStorm.
 * User: tsingsun
 * Date: 2017/2/22
 * Time: 下午5:03
 */
return [
    'setting' => [
        'daemonize'=>0,
//        'reactor_num'=>1,
        'worker_num'=>1,
//        'task_worker_num'=>1,
//        'pid_file' => __DIR__ . '/../runtime/testHttp.pid',
//        'log_file' => __DIR__.'/../runtime/logs/swoole.log',
        'debug_mode'=> 1,
        'user'=>'tsingsun',
        'group'=>'staff',
    ],
];