<?php
/**
 * Created by PhpStorm.
 * User: tsingsun
 * Date: 2018/10/9
 * Time: 下午6:57
 */

namespace tsingsun\swoole\redis;


class Cache extends \yii\redis\Cache
{
    /**
     * @inheritdoc
     */
    protected function setValue($key, $value, $expire)
    {
        if ($expire == 0) {
            return (bool)$this->redis->executeCommand('SET', [$key, $value]);
        } else {
            return (bool)$this->redis->executeCommand('SETEX', [$key, $expire, $value]);
        }
    }

    /**
     * @inheritdoc
     */
    protected function addValue($key, $value, $expire)
    {
        if ($expire == 0) {
            return (bool)$this->redis->executeCommand('SETNX', [$key, $value]);
        } else {
            return (bool) $this->redis->executeCommand('SET', [$key, $value, ['NX','EX'=>$expire]]);
        }
    }
}