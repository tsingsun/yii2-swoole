<?php
/**
 * Created by PhpStorm.
 * User: tsingsun
 * Date: 2017/9/8
 * Time: 下午4:11
 */

namespace tsingsun\swoole\helper;

use tsingsun\swoole\coroutine\Signal;
use tsingsun\swoole\coroutine\SysCall;
use tsingsun\swoole\coroutine\Task;
use tsingsun\swoole\server\Timer;

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