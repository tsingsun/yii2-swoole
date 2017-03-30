<?php
/**
 * Created by PhpStorm.
 * User: tsingsun
 * Date: 2017/3/15
 * Time: 下午6:13
 */

namespace yii\swoole\eventloop;


use yii\base\Component;

class EventLoop extends Component
{
    private $loop = '';

    public function getLoop()
    {
        return $this->loop;
    }

    public function setLoop($value)
    {
        if(!is_object($value)){
            $this->loop = \Yii::createObject($value);
        }
        return $this->loop;
    }
}