<?php
/**
 * Created by PhpStorm.
 * User: tsingsun
 * Date: 2017/9/6
 * Time: 下午4:47
 */

namespace tsingsun\swoole\coroutine;


class TaskId
{
    private static $id = 0;

    public static function create()
    {
        if (self::$id >= PHP_INT_MAX) {
            self::$id = 1;
            return self::$id;
        }

        self::$id++;
        return self::$id;
    }
}