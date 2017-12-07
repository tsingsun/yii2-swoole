<?php
/**
 * Created by PhpStorm.
 * User: tsingsun
 * Date: 2017/9/8
 * Time: 下午4:11
 */

namespace yii\swoole\helper;

use yii\swoole\coroutine\Signal;
use yii\swoole\coroutine\SysCall;
use yii\swoole\coroutine\Task;
use yii\swoole\server\Timer;

class TaskHelper
{
    public static function taskSleep($ms)
    {
        return new SysCall(function (Task $task) use ($ms) {
            Timer::after($ms, function () use ($task) {
                $task->send(null);
                $task->run();
            });

            return Signal::TASK_SLEEP;
        });
    }
}