<?php
/**
 * Created by PhpStorm.
 * User: tsingsun
 * Date: 2017/2/24
 * Time: 下午5:26
 */

namespace yiiunit\extension\swoole\server;

use yiiunit\extension\swoole\TestCase;
use tsingsun\swoole\server\Server;


class ServerTest extends TestCase
{
    function testAutoCreate(){
        $config = require __DIR__.'/../config/swoole.php';
        Server::autoCreate($config);
    }
}
