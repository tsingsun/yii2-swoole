<?php
/**
 * Created by PhpStorm.
 * User: tsingsun
 * Date: 2018/1/18
 * Time: 下午8:24
 */
namespace yiiunit\extension\swoole\controllers;

use tsingsun\swoole\pool\ConnectionManager;
use yiiunit\extension\swoole\TestCase;
use Swoole\Coroutine as co;

class ConnectionManagerTest extends TestCase
{
    protected function setUp()
    {
        parent::setUp();
        $this->mockWebApplication();
    }


    public function testGet()
    {
        $db = \Yii::$app->getDb();

        co::create(function() {
            $db = new co\MySQL();
            $server = array(
                'host' => '127.0.0.1',
                'user' => 'root',
                'password' => '',
                'database' => 'test',
            );

            $ret1 = $db->connect($server);
            $stmt = $db->prepare('SELECT * FROM userinfo WHERE id=?');
            if ($stmt == false)
            {
                var_dump($db->errno, $db->error);
            }
            else
            {
                $ret2 = $stmt->execute(array(10));
                var_dump($ret2);
            }
        });
    }
}
