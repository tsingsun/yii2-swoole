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


class SessionTest extends TestCase
{
    protected function setUp()
    {
        parent::setUp();
        $this->mockWebApplication();
    }

    function testSessionLife(){
        $a = \Yii::$app->request->getSwooleRequest();
        $a->cookie['PHPSESSID'] = 'ol1aqbgptllbnq8i9jlbp96nc4';
        $session = \Yii::$app->getSession();
        //设置true防止找不到链接
        $session->setHasSessionId(true);
        $session->set('a','b');
        $sid = $session->getId();
        $this->assertNotEmpty($sid);
        $file = $session->getSavePath();
        $session->close();
        $this->assertFalse($session->getIsActive());
        $session->setId(null);
        $session->open();
        $this->assertEquals('b',$session->get('a'));
        $this->assertTrue($session->getIsActive());
    }

    function testSessionDestroy()
    {
//        $a = \Yii::$app->request->getSwooleRequest();
        $sid = 'ol1aqbgptllbnq8i9jlbp96nc4';
//        $a->cookie['PHPSESSID'] = $sid;
        $session = \Yii::$app->getSession();
        $session->setId($sid);
        $session->setHasSessionId(true);
        $session->open();
        $result = $session->destroy();
        $this->assertNull($result);
    }

    function testRegenerate()
    {
        $a = \Yii::$app->request->getSwooleRequest();
        $a->cookie['PHPSESSID'] = 'ol1aqbgptllbnq8i9jlbp96nc4';
        $session = \Yii::$app->getSession();
        $session->set('a','b');
        $session->regenerateID(false);
    }
}
