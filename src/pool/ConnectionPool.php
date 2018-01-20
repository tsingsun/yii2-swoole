<?php
/**
 * Created by PhpStorm.
 * User: tsingsun
 * Date: 2018/1/18
 * Time: 上午11:38
 */

namespace tsingsun\swoole\pool;


use yii\base\Component;

abstract class ConnectionPool extends Component
{
    /**
     * @var int max active connections
     */
    public $maxActive = 100;
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
    public function getConnect()
    {
        if ($this->queue == null) {
            $this->queue = new \SplQueue();
        }
        $connect = null;
        if ($this->currentCount > $this->maxActive) {
            return null;
        }
        if (!$this->queue->isEmpty()) {
            $connect = $this->queue->shift();
            return $connect;
        }
        $connect = $this->createConnect();
        if ($connect !== null) {
            $this->currentCount++;
        }
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