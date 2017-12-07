<?php
/**
 * Created by PhpStorm.
 * User: tsingsun
 * Date: 2017/9/6
 * Time: 下午4:46
 */

namespace yii\swoole\coroutine;

/**
 * Singal类里包含了系统调用所需的信号量。指明了协程在一轮运行之后应该处于的状态。
 *
 * @package yii\swoole\coroutine
 */
class Signal
{
    const TASK_SLEEP        = 1;
    const TASK_AWAKE        = 2;
    const TASK_CONTINUE     = 3;
    const TASK_KILLED       = 4;
    const TASK_RUNNING      = 5;
    const TASK_WAIT         = 6;
    const TASK_DONE         = 7;

    public static function isSignal($signal) {
        if(!$signal) {
            return false;
        }

        if (!is_int($signal)) {
            return false;
        }

        if($signal < 1 ) {
            return false;
        }

        if($signal > 7) {
            return false;
        }

        return true;
    }
}