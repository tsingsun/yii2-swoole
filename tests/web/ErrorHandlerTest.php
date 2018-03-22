<?php
/**
 * Created by PhpStorm.
 * User: tsingsun
 * Date: 2018/3/17
 * Time: 上午8:56
 */

namespace yiiunit\extension\swoole\server;

use tsingsun\swoole\web\ErrorHandler;
use yiiunit\extension\swoole\TestCase;

class ErrorHandlerTest extends TestCase
{
    protected function setUp()
    {
        parent::setUp();
        $this->mockWebApplication();
        \Yii::$app->getErrorHandler()->register();
    }

    public function testHandleException()
    {
        \Yii::$app->setVersion(function () {
            require 'abc.php';
        });
        \Yii::$app->getVersion();
    }

    public function testHandleFallbackExceptionMessage()
    {

    }

    public function testHandleError()
    {
        \Yii::$app->setVersion(function () {
            $a = $b;
        });
        \Yii::$app->getVersion();
    }

    public function testHandleFatalError()
    {

    }
}
