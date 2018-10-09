<?php
/**
 * Created by PhpStorm.
 * User: tsingsun
 * Date: 2018/10/9
 * Time: 下午3:33
 */

namespace yiiunit\extension\swoole\controllers;

use Swoole\Coroutine\Channel;
use yiiunit\extension\swoole\TestCase;
use tsingsun\swoole\redis\Connection;

class ConnectionTest extends TestCase
{
    /**
     * @var \yii\redis\Connection
     */
    private $redis;

    protected function setUp()
    {
        parent::setUp();
        $this->mockWebApplication();
        \Yii::$app->set("redis", [
            'class' => Connection::class,
            'hostname' => '127.0.0.1',
            'port' => 6379,
            'database' => 0
        ]);
        /** @var $redis \yii\redis\Connection */
        $this->redis = \Yii::$app->redis;

    }

    public function testGetIsActive()
    {
        $this->assertEquals(get_class($this->redis), Connection::class);
        $this->assertEquals($this->redis->getIsActive(), false);
    }

    public function testOpen()
    {
        $chan = new Channel(1);
        go(function () use($chan) {
            $this->redis->open();
            $this->assertEquals($this->redis->getIsActive(), true);
            $chan->push(1);
        });
        sleep(1);
        go(function () use ($chan) {
            $value = $chan->pop();
            if ($value === 1) {
                swoole_event_exit();
            }
        });
        swoole_event_wait();

        echo 1;
    }

    public function testExecuteCommand()
    {
        $this->redis->setex("test", 60, 1);
    }

    public function testReleaseConnect()
    {

    }
}
