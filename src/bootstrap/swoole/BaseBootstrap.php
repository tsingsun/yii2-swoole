<?php
/**
 * Created by PhpStorm.
 * User: tsingsun
 * Date: 2017/9/6
 * Time: 下午3:45
 */

namespace tsingsun\daemon\bootstrap\swoole;

use Swoole\Http\Request as SwooleRequest;
use Swoole\Http\Response as SwooleResponse;
use Yii;
use tsingsun\daemon\coroutine\Signal;
use tsingsun\daemon\server\swoole\Server;
use tsingsun\daemon\server\swoole\Timer;
use tsingsun\daemon\web\Application;
use yii\web\HttpException;

abstract class BaseBootstrap implements BootstrapInterface
{
    /**
     * @var Server
     */
    protected $server;

    /**
     * @var \tsingsun\daemon\web\Application
     */
    public $app;

    /**
     * 在该方法中实际处理请求
     * @param $request
     * @param $response
     * @return mixed
     */
    public abstract function handleRequest($request, $response);

    /**
     * 根据请求,构建Yii的环境变量
     * @param SwooleRequest $request
     * @return mixed
     */
    protected abstract function setupEnvironment(SwooleRequest $request,SwooleResponse $response);

    public function __construct(Server $server)
    {
        $this->server = $server;
        $this->init();
    }

    public function init()
    {
    }

    /**
     * @inheritdoc
     */
    public function onRequest($request, $response)
    {
        $this->setupEnvironment($request,$response);
        Timer::after($this->server->timeout, [$this, 'handleTimeout'], $this->getRequestTimeoutJobId());
        $this->app->on(Application::EVENT_AFTER_RUN,[$this,'onRequestEnd']);
        $this->handleRequest($request,$response);
        $this->onRequestEnd();
    }

    /**
     * @inheritdoc
     */
    public function onRequestEnd()
    {
        Timer::clearAfterJob($this->getRequestTimeoutJobId());
        if (Yii::$app->has('session', true)) {
            Yii::$app->getSession()->close();
        }
        $logger = Yii::getLogger();
        $logger->flush(true);
//        Yii::$app = $this->app;
    }

    public function onWorkerError($swooleServer, $workerId, $workerPid, $exitCode, $sigNo)
    {
        Yii::error("worker error happening [workerId=$workerId, workerPid=$workerPid, exitCode=$exitCode, signalNo=$sigNo]...", 'monitor');
    }


    public function onTask(\Swoole\Server $serv, int $task_id, int $src_worker_id, $data)
    {
        $func = array_shift($data);
        if (is_callable($func)) {
            $params[] = array_shift($data);
            call_user_func_array($func, $params);
        }
        return 1;
    }

    /**
     * 处理超时请求
     */
    public function handleTimeout()
    {
        try {
            $exception = new HttpException(408,'服务器超时');
            //handleException中已经初步处理了各类异常
            Yii::$app->getErrorHandler()->handleException($exception);
        } finally {
            $this->onRequestEnd();
        }
    }

    private function getRequestTimeoutJobId()
    {
        return spl_object_hash(Yii::$app->request) . '_handle_timeout';
    }
}