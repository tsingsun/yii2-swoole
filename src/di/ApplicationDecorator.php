<?php
/**
 * Created by PhpStorm.
 * User: tsingsun
 * Date: 2018/1/15
 * Time: 下午6:05
 */

namespace tsingsun\swoole\di;


use Yii;

/**
 * 以该类来替换Yii::$app的引用,以实现协程态下$app的隔离
 * @package tsingsun\swoole\coroutine
 */
class ApplicationDecorator
{
    public function &__get($name)
    {
        $application = $this->getApplication();
        if (property_exists($application, $name)) {
            return $application->{$name};
        } else {
            $value = $application->{$name};
            return $value;
        }

    }

    public function __set($name, $value)
    {
        $application = $this->getApplication();
        $application->{$name} = $value;
    }

    public function __call($name, $arguments)
    {
        $application = $this->getApplication();
        return $application->$name(...$arguments);
    }

    /**
     * 根据协程ID
     * @param $coroutineId
     * @return \Yii\base\Application
     *
     */
    public function getApplication($coroutineId = null)
    {
        return Yii::$context->getApplication($coroutineId);
    }
}