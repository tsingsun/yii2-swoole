<?php

namespace yiiunit\extension\swoole;

use tsingsun\swoole\bootstrap\BaseBootstrap;
use tsingsun\swoole\server\HttpServer;
use tsingsun\swoole\server\Server;
use tsingsun\swoole\web\Application;
use yii\helpers\ArrayHelper;

/**
 * This is the base class for all yii framework unit tests.
 */
abstract class TestCase extends \PHPUnit_Framework_TestCase
{
    protected $loop;

    public static $params;
    /** @var BaseBootstrap */
    public $starter;

    /**
     * Clean up after test.
     * By default the application created with [[mockApplication]] will be destroyed.
     */
    protected function tearDown()
    {
        parent::tearDown();
//        $this->destroyApplication();
    }

    /**
     * Returns a test configuration param from /data/config.php
     * @param  string $name params name
     * @param  mixed $default default value to use when param is not set.
     * @return mixed  the value of the configuration param
     */
    public static function getParam($name, $default = null)
    {
        if (static::$params === null) {
            static::$params = require(__DIR__ . '../config/main.php');
        }

        return isset(static::$params[$name]) ? static::$params[$name] : $default;
    }

    protected function mockApplication($config = [], $appClass = 'tsingsun\swoole\bootstrap\WebApp')
    {
        $cf = ArrayHelper::merge(
            $c1 = require(__DIR__ . '/config/console.php'),
            $c2 = require(__DIR__ . '/config/console-local.php')
        );

        new $appClass(ArrayHelper::merge([
            'id' => 'testapp',
            'basePath' => __DIR__ . '/../',
            'vendorPath' => $this->getVendorPath(),
        ], $config));
        new $appClass(ArrayHelper::merge($cf, $config));
    }

    protected function mockWebApplication($configs = [], $appClass = 'tsingsun\swoole\bootstrap\WebApp')
    {
        $cf = ArrayHelper::merge(
            $c1 = require(__DIR__ . '/config/main.php'),
            $c2 = require(__DIR__ . '/config/main-local.php')
        );
        foreach ($cf['bootstrap'] as $key => $item) {
            if ($item == 'log1' || $item == 'debug') {
                unset($cf['bootstrap'][$key]);
            }
        }
        unset($cf['modules']['debug']);
        $cf['components']['log'] = [
            'targets' => [
                [
                    'class' => 'yii\log\FileTarget',
                    'maxFileSize' => 200,
                    'levels' => ['trace'],
                    'logVars' => [],
                    'logFile' => '@runtime/logs/' . date('ymd') . '.log',
                ],
            ],
        ];
        $swoole = require(__DIR__ . '/config/swoole.php');
        $this->starter = new $appClass();
        $this->starter->appConfig =
            ArrayHelper::merge($cf, [
                'id' => 'testapp',
                'vendorPath' => $this->getVendorPath(),
                'components' => [
                    'request' => [
                        'cookieValidationKey' => 'wefJDF8sfdsfSDefwqdxj9oq',
                        'scriptFile' => __DIR__ . '/../web/index.php',
                        'scriptUrl' => '/index.php',
                    ],
                ]
            ], $configs);
        $this->setCurrentUser();
        \Yii::setAlias('@yiiunit/extension/swoole', __DIR__ . '/');
        $this->starter->onWorkerStart(null,1);
        new Application($this->starter->appConfig);
        $request = new \swoole_http_request();
        $request->header = $request->get = $request->post = $request->files = $request->cookie = [];
        $response = new \swoole_http_response();
        \Yii::$app->request->setSwooleRequest($request);
        \Yii::$app->response->setSwooleResponse($response);
    }

    protected function setCurrentUser()
    {
//        \Yii::$app->user->setIdentity(new TestIdentity());
    }

    protected function getVendorPath()
    {
        $vendor = dirname(dirname(__DIR__)) . '/vendor';
        if (!is_dir($vendor)) {
            $vendor = dirname(dirname(dirname(dirname(__DIR__))));
        }
        return $vendor;
    }

    /**
     * Destroys application in Yii::$app by setting it to null.
     */
    protected function destroyApplication()
    {
        if (\Yii::$app && \Yii::$app->has('session', true)) {
            \Yii::$app->session->close();
        }
        \Yii::$app = null;
    }

    /**
     * Asserting two strings equality ignoring line endings
     *
     * @param string $expected
     * @param string $actual
     */
    public function assertEqualsWithoutLE($expected, $actual)
    {
        $expected = str_replace("\r\n", "\n", $expected);
        $actual = str_replace("\r\n", "\n", $actual);

        $this->assertEquals($expected, $actual);
    }

    /**
     * Invokes a inaccessible method
     * @param $object
     * @param $method
     * @param array $args
     * @param bool $revoke whether to make method inaccessible after execution
     * @return mixed
     * @since 2.0.11
     */
    protected function invokeMethod($object, $method, $args = [], $revoke = true)
    {
        $reflection = new \ReflectionClass($object->className());
        $method = $reflection->getMethod($method);
        $method->setAccessible(true);
        $result = $method->invokeArgs($object, $args);
        if ($revoke) {
            $method->setAccessible(false);
        }
        return $result;
    }

    /**
     * Sets an inaccessible object property to a designated value
     * @param $object
     * @param $propertyName
     * @param $value
     * @param bool $revoke whether to make property inaccessible after setting
     * @since 2.0.11
     */
    protected function setInaccessibleProperty($object, $propertyName, $value, $revoke = true)
    {
        $class = new \ReflectionClass($object);
        while (!$class->hasProperty($propertyName)) {
            $class = $class->getParentClass();
        }
        $property = $class->getProperty($propertyName);
        $property->setAccessible(true);
        $property->setValue($value);
        if ($revoke) {
            $property->setAccessible(false);
        }
    }

    /**
     * Gets an inaccessible object property
     * @param $object
     * @param $propertyName
     * @param bool $revoke whether to make property inaccessible after getting
     * @return mixed
     */
    protected function getInaccessibleProperty($object, $propertyName, $revoke = true)
    {
        $class = new \ReflectionClass($object);
        while (!$class->hasProperty($propertyName)) {
            $class = $class->getParentClass();
        }
        $property = $class->getProperty($propertyName);
        $property->setAccessible(true);
        $result = $property->getValue($object);
        if ($revoke) {
            $property->setAccessible(false);
        }
        return $result;
    }
}
