<?php
// ensure we get report on all possible php errors
error_reporting(-1);
define('YII_ENABLE_ERROR_HANDLER', false);
define('YII_DEBUG', true);

$_SERVER['SCRIPT_NAME'] = '/' . __DIR__;
$_SERVER['SCRIPT_FILENAME'] = __FILE__;

if (is_dir(__DIR__ . '/../vendor/')) {
    $vendorRoot = __DIR__ . '/../vendor'; //this extension has its own vendor folder
} else {
    $vendorRoot = __DIR__ . '/../../..'; //this extension is part of a project vendor folder
}
require_once($vendorRoot . '/autoload.php');
if (\Swoole\Coroutine::getuid() > 1) {
    define('COROUTINE_ENV', true);
} else {
    define('COROUTINE_ENV', false);
}
require_once(__DIR__ . '/../src/Yii.php');

Yii::setAlias('@tsingsun/swoole', __DIR__ . '/../src/');
//Yii::setAlias('@app', __DIR__ . '/../');
Yii::setAlias('@yiiunit/extension/swoole', __DIR__ . '/');
if (COROUTINE_ENV) {
    Yii::$container = new \tsingsun\swoole\di\ContainerDecorator();
    Yii::$context = new \tsingsun\swoole\di\Context();
    Yii::$context->setContainer(new \tsingsun\swoole\di\Container());

} else {
    Yii::$container = new \tsingsun\swoole\di\cm\Container();
}
require_once(__DIR__ . '/TestCase.php');
