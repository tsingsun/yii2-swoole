<?php
/**
 * Created by PhpStorm.
 * User: tsingsun
 * Date: 2017/2/24
 * Time: 下午5:26
 */

namespace yiiunit\extension\swoole\server;

use yiiunit\extension\swoole\TestCase;
use tsingsun\swoole\server\Server;
use Yii;

class MysqlTest extends TestCase
{
    protected function setUp()
    {
        parent::setUp();
        $this->mockWebApplication();
    }

    function testQuery(){
        $val = Yii::$app->getDb()->createCommand('select 1')->query();
        $this->assertEquals(1,$val);
    }
}
