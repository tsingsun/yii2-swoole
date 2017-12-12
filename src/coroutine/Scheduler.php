<?php
/**
 * Created by PhpStorm.
 * User: tsingsun
 * Date: 2017/9/6
 * Time: 下午4:50
 */

namespace tsingsun\swoole\coroutine;

/**
 * Class Scheduler
 * scheduler类负责：
 * 1. 获取Task里的协程函数跑完一轮的返回值
 * 2. 根据返回值的类型采取不同的处理方式，如系统调用、子协程、普通yield值、检查协程栈等等。
 * 3. 在子协程的调用过程中，负责父子协程的进栈出栈，yield值的传递等等。
 * @package tsingsun\swoole\coroutine
 */
class Scheduler
{
    private $task = null;
    private $stack = null;

    public function __construct(Task $task)
    {
        $this->task = $task;
        $this->stack = new \SplStack();
    }

    public function schedule()
    {
        $coroutine = $this->task->getCoroutine();

        $value = $coroutine->current();

        $signal = $this->handleSysCall($value);
        if ($signal !== null) return $signal;

        $signal = $this->handleCoroutine($value);
        if ($signal !== null) return $signal;

        $signal = $this->handleAsyncJob($value);
        if ($signal !== null) return $signal;

        $signal = $this->handleYieldValue($value);
        if ($signal !== null) return $signal;

        $signal = $this->handleTaskStack($value);
        if ($signal !== null) return $signal;


        $signal = $this->checkTaskDone($value);
        if ($signal !== null) return $signal;

        return Signal::TASK_DONE;
    }

    public function isStackEmpty()
    {
        return $this->stack->isEmpty();
    }

    public function throwException($e, $isFirstCall = false, $isAsync = false)
    {
        if ($this->isStackEmpty()) {
            $parent = $this->task->getParentTask();
            if (null !== $parent && $parent instanceof Task) {
                $parent->sendException($e);
            } else {
                $this->task->getCoroutine()->throw($e);
            }
            return;
        }

        try{
            if ($isFirstCall) {
                $coroutine = $this->task->getCoroutine();
            } else {
                $coroutine = $this->stack->pop();
            }

            $this->task->setCoroutine($coroutine);
            $coroutine->throw($e);

            if ($isAsync) {
                $this->task->run();
            }
        } catch (\Throwable $t){
            $this->throwException($t, false, $isAsync);
        } catch (\Exception $e){
            $this->throwException($e, false, $isAsync);
        }
    }

    public function asyncCallback($response, $exception = null)
    {
        // 兼容PHP7 & PHP5
        if ($exception instanceof \Throwable || $exception instanceof \Exception) {
            $this->throwException($exception, true, true);

            if (Signal::TASK_DONE == $this->task->getStatus()) {
                return ;
            }
        } else {
            if (Signal::TASK_DONE == $this->task->getStatus()) {
                return ;
            }
            $this->task->send($response);
            $this->task->run();
        }
    }
    /**
     * 处理系统调用
     * @param $value
     * @return mixed|null
     */
    private function handleSysCall($value)
    {
        if (!($value instanceof SysCall)
            && !is_subclass_of($value, SysCall::class)
        ) {
            return null;
        }

        $signal = call_user_func($value, $this->task);
        if (Signal::isSignal($signal)) {
            return $signal;
        }

        return null;
    }
    /**
     * 处理子协程
     * @param $value
     * @return int|null
     */
    private function handleCoroutine($value)
    {
        if (!($value instanceof \Generator)) {
            return null;
        }
        //获取当前的协程 入栈
        $coroutine = $this->task->getCoroutine();
        $this->stack->push($coroutine);
        //将新的协程设为当前的协程
        $this->task->setCoroutine($value);

        return Signal::TASK_CONTINUE;
    }

    private function handleAsyncJob($value)
    {
        if (!is_subclass_of($value, Async::class)) {
            return null;
        }

        /** @var $value Async */
        $value->execute([$this, 'asyncCallback'], $this->task);

        return Signal::TASK_WAIT;
    }
    /**
     * 处理协程栈
     * @param $value
     * @return int|null
     */
    private function handleTaskStack($value)
    {
        //能够跑到这里说明当前协程已经跑完了 valid()==false了 需要看下栈里是否还有以前的协程
        if ($this->isStackEmpty()) {
            return null;
        }
        //出栈 设置为当前运行的协程
        $coroutine = $this->stack->pop();
        $this->task->setCoroutine($coroutine);
        //这个sendvalue可能是从刚跑完的协程那里得到的 把它当做send值传给老协程 让他继续跑
        $value = $this->task->getSendValue();
        $this->task->send($value);

        return Signal::TASK_CONTINUE;
    }
    /**
     * 处理普通的yield值
     * @param $value
     * @return int|null
     */
    private function handleYieldValue($value)
    {
        $coroutine = $this->task->getCoroutine();
        if (!$coroutine->valid()) {
            return null;
        }
        //如果协程后面没有yield了 这里发出send以后valid就变成false了 并且current变成NULL
        $status = $this->task->send($value);
        return Signal::TASK_CONTINUE;
    }

    private function checkTaskDone($value)
    {
        $coroutine = $this->task->getCoroutine();
        if ($coroutine->valid()) {
            return null;
        }

        return Signal::TASK_DONE;
    }
}