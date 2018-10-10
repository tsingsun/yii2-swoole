<?php
/**
 * Created by PhpStorm.
 * User: tsingsun
 * Date: 2018/1/18
 * Time: 上午11:38
 */

namespace tsingsun\swoole\pool;


use yii\base\Component;
use yii\base\Exception;

abstract class ConnectionPool extends Component
{
    /**
     * @var int max active connections
     */
    public $maxActive = 100;
    /**
     * @var float 当链接数满时,重新获取的等待时间,秒为单位
     */
    public $waitTime = 0.01;
    /**
     * the nubmer of current connections
     *
     * @var int
     */
    protected $currentCount = 0;

    /**
     * the queque of connection
     *
     * @var \SplQueue
     */
    protected $queue = null;

    /**
     * 连接池中取一个连接
     *
     * @return object|null
     */
    public function getConnect($retry = 0)
    {
        if ($this->queue == null) {
            $this->queue = new \SplQueue();
        }
        $connect = null;
        if (!$this->queue->isEmpty()) {
            $connect = $this->queue->shift();
        }elseif ($this->currentCount < $this->maxActive) {
            $connect = $this->createConnect();
        }elseif($retry < 3) {
            //重试3次
            \Swoole\Coroutine::sleep($this->waitTime);
            $connect = $this->getConnect(++$retry);
        }

        if ($connect === null) {
            throw new Exception('connection pool is full');
        }
        $this->currentCount++;
        return $connect;
    }

    /**
     * 释放一个连接到连接池
     *
     * @param object $connect 连接
     */
    public function release($connect)
    {
        if ($this->queue->count() < $this->maxActive) {
            $this->queue->push($connect);
            $this->currentCount--;
        }
    }

    abstract public function createConnect();

    abstract public function reConnect($client);
}