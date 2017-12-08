<?php
/**
 * Created by PhpStorm.
 * User: tsingsun
 * Date: 2017/9/8
 * Time: 下午4:11
 */

namespace tsingsun\daemon\helper;

use tsingsun\daemon\coroutine\Signal;
use tsingsun\daemon\coroutine\SysCall;
use tsingsun\daemon\coroutine\Task;
use tsingsun\daemon\server\Timer;

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