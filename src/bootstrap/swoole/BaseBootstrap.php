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
use tsingsun\daemon\server\swoole\Server;
use tsingsun\daemon\server\swoole\Timer;
use tsingsun\daemon\web\Application;
use Yii;
use yii\base\Event;
use yii\web\HttpException;

abstract class BaseBootstrap implements BootstrapInterface
{
    public $index = '/index.php';
    /**
     * @var Server
     */
    protected $server;

    /**
     * @var \tsingsun\daemon\web\Application
     */
    public $app;

    /**
     * @var callable
     */
    public $init;
    /**
     * @var int worker线程的ID
     */
    protected $workerId;
    /**
     * @var string 一次请求的ID
     */
    protected $requestId;

    public function __construct(Server $server)
    {
        $this->server = $server;
        $this->init();
    }

    public function init()
    {
    }

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
    protected function setupEnvironment($request)
    {
        if($request){
            $_GET = isset($request->get) ? $request->get : [];
            $_POST = isset($request->post) ? $request->post : [];
            $_SERVER = array_change_key_case($request->server, CASE_UPPER);
            $_FILES = isset($request->files) ? $request->files : [];
            $_COOKIE = isset($request->cookie) ? $request->cookie : [];

            if (isset($request->header)) {
                foreach ($request->header as $key => $value) {
                    $key = 'HTTP_' . strtoupper(str_replace('-', '_', $key));
                    $_SERVER[$key] = $value;
                }
            }
        }
        $this->initServerVars();

        $app = clone $this->app;
        Yii::$app = &$app;
        Yii::$app->set('request',clone $this->app->request);
    }

    public function onWorkerStart(\Swoole\Server $server, $worker_id)
    {
        $this->workerId = $worker_id;
        $initFunc = $this->init;
        if ($initFunc instanceof \Closure) {
            $initFunc($this);
        }

        $this->initComponent();
    }

    /**
     * @inheritdoc
     */
    public function onRequest($request, $response)
    {
        $this->setupEnvironment($request);
        Yii::$app->on(Application::EVENT_BEFORE_REQUEST,[$this,'onBeforeRequest']);
        Timer::after($this->server->timeout, [$this, 'handleTimeout'], $this->getRequestTimeoutJobId());
        Yii::$app->on(Application::EVENT_AFTER_RUN,[$this,'onRequestEnd']);
        return $this->handleRequest($request,$response);
    }

    public function onBeforeRequest(Event $event){}

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
        $this->requestId = null;
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

    public function onFinish(\Swoole\Server $serv, int $task_id, string $data)
    {
        //echo $data;
    }

    /**
     * @param \Swoole\Server $server
     * @param $worker_id
     */
    public function onWorkerStop(\Swoole\Server $server, $worker_id)
    {
        if (!$server->taskworker) {
            Yii::getLogger()->flush(true);
        }
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
        if(!$this->requestId){
            $this->requestId = spl_object_hash(Yii::$app->request) . '_handle_timeout';
        }
        return $this->requestId;
    }

    /**
     * 初始化一些可以复用的组件
     */
    private function initComponent()
    {
        $this->initServerVars();
        $this->app->getSecurity();
        $this->app->getUrlManager();
        $this->app->getRequest();

        if ($this->app->has('session', true)) {
            $this->app->getSession();
        }
        $this->app->getView();
        $this->app->getDb();
        $this->app->getUser();
        if ($this->app->has('mailer', true)) {
            $this->app->getMailer();
        }
        //动态方式继承response对象
//        $nativeResponse = $this->app->getComponents(true)['response']['class'];
//        $code = "return new class extends $nativeResponse { use \\tsingsun\daemon\web\Response; };";
//        $response = eval($code);
//        $this->app->set('response',$response);
    }

    /**
     * 初始化Yii框架所需要的$_SERVER变量以及执行过程中所需要的通用变量
     */
    protected function initServerVars()
    {
        //使application运行时不会报错
        $file = $this->server->root . $this->index;

        $_SERVER['PHP_SELF'] = $_SERVER['SCRIPT_NAME'] = $_SERVER['DOCUMENT_URI'] = $this->index;
        $_SERVER['SCRIPT_FILENAME'] = $file;
        $_SERVER['SERVER_ADDR'] = '127.0.0.1';
        $_SERVER['SERVER_NAME'] = 'localhost';
        $_SERVER['WORKER_ID'] = $this->workerId;
    }
}