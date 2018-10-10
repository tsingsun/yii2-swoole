<?php
/**
 * Created by PhpStorm.
 * User: tsingsun
 * Date: 2018/1/15
 * Time: 下午5:27
 */

namespace tsingsun\swoole;

class BaseYii extends \yii\BaseYii
{
    /**
     * 由于Yii的静态化,需要另一个上下文对象来处理协程对象
     * @var \tsingsun\swoole\di\Context
     */
    public static $context;
    /**
     * @var \Swoole\Server
     */
    public static $swooleServer;
}