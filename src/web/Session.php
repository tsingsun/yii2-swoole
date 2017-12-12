<?php
/**
 * Created by PhpStorm.
 * User: tsingsun
 * Date: 2017/3/20
 * Time: 下午6:22
 */

namespace tsingsun\swoole\web;

use Yii;
use yii\base\InvalidConfigException;

/**
 * Class Session
 * @package tsingsun\swoole\web
 */
class Session extends \yii\web\Session
{
    public function init()
    {
//        parent::init();
        //register_shutdown_function([$this, 'close']);
        if ($this->getIsActive()) {
            Yii::warning('Session is already started', __METHOD__);
            $this->updateFlashCounters();
        }
    }

    public function close()
    {
        parent::close();
        //在session_write_close后,清除当前进程session数据
        $_SESSION = [];
    }


}