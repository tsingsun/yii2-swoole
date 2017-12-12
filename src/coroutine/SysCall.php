<?php
/**
 * Created by PhpStorm.
 * User: tsingsun
 * Date: 2017/9/6
 * Time: 下午5:01
 */

namespace tsingsun\swoole\coroutine;

/**
 * 系统调用类一般作为yield后面跟着的值吐给外层的调用方来执行，并且可能返回响应的信号量，标识这个Task是继续运行还是进入等待状态中。
 *
 * @package tsingsun\swoole\coroutine
 */
class SysCall
{
    protected $callback = null;

    public function __construct(\Closure $callback)
    {
        $this->callback = $callback;
    }

    public function __invoke(Task $task)
    {
        return call_user_func($this->callback, $task);
    }
}