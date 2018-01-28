<?php
/**
 * Created by PhpStorm.
 * User: tsingsun
 * Date: 2017/9/6
 * Time: 下午2:59
 */

namespace tsingsun\swoole\server;


use Swoole\Coroutine;
use yii\base\Exception;
use yii\base\InvalidParamException;

/**
 * 支持协程的定时器 Timer
 * 定时器启动时,是启用新的协程,因此在通过Coroutine::getid()时需要注意,回调函数提供了定时器启动的协程ID
 * 回调函数
 *      function(Callback $cb,$jobId,$coroutineID)
 *      - $coroutineID 启动的协程ID
 * @package tsingsun\swoole\server
 */
class Timer
{
    private static $tickMap = [];
    private static $afterMap = [];
    private static $counter = 0;

    /**
     * 添加一个每隔 {$interval} 毫秒 执行一次的计时器任务
     * @param int        $interval  单位: 毫秒
     * @param callable   $callback
     * @param string     $jobId   标识任务的唯一标识符，必须唯一
     *
     * @return string    $jobId   timer job id
     * @throws InvalidParamException
     * @throws Exception
     */
    public static function tick($interval, Callable $callback, $jobId='')
    {
        self::valid($interval);
        $jobId = self::formatJobId($jobId);

        if (isset(self::$tickMap[$jobId])) {
            throw new Exception('job name is exist!');
        }
        $coroutineId = Coroutine::getuid();
        $timerId = swoole_timer_tick($interval, self::formatTickCallback($jobId, $callback,$coroutineId));
        self::$tickMap[$jobId] = $timerId;

        return $jobId;
    }

    /**
     * 添加一个 {$interval} 毫秒后仅执行一次的计时器任务
     * @param int        $interval  单位: 毫秒
     * @param callable   $callback
     * @param string     $jobId   标识任务的唯一标识符，必须唯一
     *
     * @return string    $jobId timer job id
     * @throws InvalidParamException
     * @throws Exception
     */
    public static function after($interval, callable $callback, $jobId='')
    {
        self::valid($interval);
        $jobId = self::formatJobId($jobId);

        if (isset(self::$afterMap[$jobId])) {
            throw new Exception('job name is exist!');
        }
        $coroutineId = Coroutine::getuid();
        $timerId = swoole_timer_after($interval, self::formatAfterCallback($jobId, $callback,$coroutineId));
        self::$afterMap[$jobId] = $timerId;

        return $jobId;
    }

    /**
     * 根据tick timer job id 清除一个计时器任务
     *
     * @param string $jobId
     * @return bool
     */
    public static function clearTickJob($jobId)
    {
        if(!isset(self::$tickMap[$jobId])){
            return false;
        }

        $timerId = self::$tickMap[$jobId];
        $isCleared = swoole_timer_clear($timerId);

        if($isCleared){
            unset(self::$tickMap[$jobId]);
        }

        return $isCleared;
    }

    /**
     * 根据after timer job id 清除一个计时器任务
     *
     * @param string $jobId
     *
     * @return bool
     */
    public static function clearAfterJob($jobId)
    {
        if(!isset(self::$afterMap[$jobId])){
            return false;
        }

        $timerId = self::$afterMap[$jobId];
        $isCleared = swoole_timer_clear($timerId);

        if($isCleared){
            unset(self::$afterMap[$jobId]);
        }

        return $isCleared;
    }

    /**
     * @param $key
     * @return bool
     */
    public static function clearTickMap($key)
    {
        if(!$key) {
            return false;
        }

        unset(self::$tickMap[$key]);
    }

    /**
     * @param $key
     * @return bool
     */
    public static function clearAfterMap($key)
    {
        if(!$key) {
            return false;
        }

        unset(self::$afterMap[$key]);
    }

    /**
     * @param $jobId
     * @param callable $callback
     * @param $coroutineId
     *
     * @return \Closure
     */
    private static function formatTickCallback($jobId, Callable $callback,$coroutineId)
    {
        //创建当前任务的协程ID.

        return function() use ($jobId, $callback,$coroutineId) {
            $callback($jobId,$coroutineId);
        };
    }

    /**
     * @param $jobId
     * @param callable $callback
     * @return \Closure
     */
    private static function formatAfterCallback($jobId, Callable $callback,$coroutineId)
    {
        return function() use ($jobId, $callback,$coroutineId) {
            Timer::clearAfterMap($jobId);
            $callback($jobId,$coroutineId);
        };
    }

    /**
     * @param $interval
     * @throws InvalidParamException
     */
    private static function valid($interval)
    {
        if (!is_int($interval)) {
            throw new InvalidParamException('interval must be a int!');
        }

        if ($interval <= 0) {
            throw new InvalidParamException('interval must be greater than 0!');
        }
    }

    /**
     * @param $jobId
     * @return string
     */
    private static function formatJobId($jobId){
        if($jobId){
            return $jobId;
        }
        return self::createJobId();
    }


    /**
     * @return string
     */
    private static function createJobId()
    {
        if(self::$counter >= PHP_INT_MAX){
            self::$counter = 0;
        }

        self::$counter++;
        return 'j_' . self::$counter;
    }
}
