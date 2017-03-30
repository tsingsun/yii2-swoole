<?php
/**
 * Created by PhpStorm.
 * User: tsingsun
 * Date: 2017/3/7
 * Time: 上午11:13
 */

namespace yii\swoole\bootstrap;

use Swoole\Server;

interface BootstrapInterface
{
    public function onWorkerStart(Server $server,$worker_id);
    public function onWorkerStop(Server $server,$worker_id);
    public function onRequest($request, $response);
    public function onTask(Server $serv, int $task_id, int $src_worker_id, $data);
    public function onFinish(Server $serv, int $task_id, string $data);
}