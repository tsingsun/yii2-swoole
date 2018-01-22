<?php
/**
 * Created by PhpStorm.
 * User: tsingsun
 * Date: 2018/1/22
 * Time: 下午5:13
 */

namespace tsingsun\swoole\web;


class DbSession extends \yii\web\DbSession
{
    use SessionTrait;
}