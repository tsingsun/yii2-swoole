<?php
/**
 * Created by PhpStorm.
 * User: tsingsun
 * Date: 2018/1/15
 * Time: 下午9:21
 */

namespace tsingsun\swoole\di;

use Yii;

/**
 * Class ContainerDecorator
 * @package tsingsun\swoole\di
 */
class ContainerDecorator
{
    function __get($name)
    {
        $container = $this->getContainer();
        return $container->{$name};
    }

    function __call($name, $arguments)
    {
        $container = $this->getContainer();
        return $container->$name(...$arguments);
    }

    /**
     * @return Container
     */
    protected function getContainer()
    {
        return Yii::$context->getContainer();
    }
}