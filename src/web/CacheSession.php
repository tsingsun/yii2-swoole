<?php
/**
 * Created by PhpStorm.
 * User: tsingsun
 * Date: 2018/1/22
 * Time: 下午5:13
 */

namespace tsingsun\swoole\web;


use yii\di\Instance;

class CacheSession extends \yii\web\CacheSession
{
    use SessionTrait;

    public function init()
    {
        if ($this->getIsActive()) {
            \Yii::warning('Session is already started in swoole', __METHOD__);
            $this->updateFlashCounters();
        }
        $this->cache = Instance::ensure($this->cache, 'yii\caching\CacheInterface');
    }


}