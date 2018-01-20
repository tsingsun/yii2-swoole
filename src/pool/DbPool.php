<?php
/**
 * Created by PhpStorm.
 * User: tsingsun
 * Date: 2018/1/18
 * Time: 下午2:31
 */

namespace tsingsun\swoole\pool;


class DbPool extends ConnectionPool
{
    /**
     * @var callable
     */
    public $createHandle;

    public function createConnect()
    {
        if($this->createHandle instanceof \Closure){
            $conn = call_user_func($this->createHandle);
            return $conn;
        }
    }

    public function release($connect)
    {
        parent::release($connect);
    }

    public function reConnect($client)
    {

    }

}