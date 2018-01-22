<?php
/**
 * Created by PhpStorm.
 * User: tsingsun
 * Date: 2018/1/22
 * Time: 下午5:13
 */

namespace tsingsun\swoole\web;


class CacheSession extends \yii\web\CacheSession
{
    use SessionTrait;
}