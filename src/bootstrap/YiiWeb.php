<?php
/**
 * Created by PhpStorm.
 * User: tsingsun
 * Date: 2017/3/7
 * Time: 上午10:41
 */

namespace yii\swoole\bootstrap;

require_once __DIR__.'/../functions_include.php';

use Yii;
use yii\swoole\di\Container;
use yii\swoole\log\Dispatcher;
use yii\swoole\log\Logger;
use yii\swoole\server\Server;
use yii\swoole\web\Application;
use Swoole\Http\Request as SwooleRequest;
use Swoole\Http\Response as SwooleResponse;
use yii\swoole\web\Response;
use Swoole\Server as SwooleServer;

/**
 * Yii starter for swoole server
 * @package yii\swoole\bootstrap
 */
class YiiWeb implements BootstrapInterface
{
    public $index = '/index.php';

    public $webRoot;

    /**
     * @var Server
     */
    private $server;

    /**
     * @var callable
     */
    public $init;

    /**
     * @var yii config;
     */
    public $config;

    /**
     * @var \yii\web\Application
     */
    public $app;

    public function __construct(Server $server)
    {
        $this->server = $server;
        $this->webRoot = $this->server->root;
    }

    public function onWorkerStart(SwooleServer $server,$worker_id)
    {
        //使application运行时不会报错
        $_SERVER['PHP_SELF'] = $_SERVER['SCRIPT_NAME'] = $this->index;
        $_SERVER['SCRIPT_FILENAME'] = $this->webRoot.$this->index;

        $initFunc = $this->init;
        if($initFunc instanceof \Closure){
            $initFunc($this);
        }
        Yii::$container = new Container();
        $this->app = new Application($this->config);
        Yii::setAlias('@webroot', $this->webRoot);
        Yii::setAlias('@web', '/');

        $this->app->server = $this->server;

        $this->initComponent();
    }

    /**
     * @param SwooleRequest $request
     * @param SwooleResponse $response
     * @throws \Exception
     * @throws \Throwable
     */
    public function onRequest($request, $response)
    {
        $_GET    = isset($request->get) ? $request->get : [];
        $_POST   = isset($request->post) ? $request->post : [];
        $_SERVER = array_change_key_case($request->server, CASE_UPPER);
        $_FILES  = isset($request->files) ? $request->files : [];
        $_COOKIE = isset($request->cookie) ? $request->cookie : [];
        // 备份当前的环境变量
        $tmpServer = $_SERVER;

        if (isset($request->header)) {
            foreach ($request->header as $key => $value) {
                $key = 'HTTP_' . strtoupper(str_replace('-', '_', $key));
                $_SERVER[$key] = $value;
            }
        }

        $file = $this->webRoot . $this->index;

        $_SERVER['PHP_SELF'] = $_SERVER['SCRIPT_NAME'] = $_SERVER['DOCUMENT_URI'] = $this->index;
        $_SERVER['SCRIPT_FILENAME'] = $file;
        $_SERVER['SERVER_ADDR']     = '127.0.0.1';
        $_SERVER['SERVER_NAME']     = 'localhost';
        $this->cloneComponent($response);
        try{
            Yii::$app->run();
        }catch (\Exception $e){
            Yii::$app->getErrorHandler()->handleException($e);
        }catch (\Throwable $e){
            Yii::$app->getErrorHandler()->handleFallbackExceptionMessage($e,$e->getPrevious());
        }finally{
            try{
                $this->onEndRequest();
            }catch (\Throwable $throwable){
                echo $throwable->getMessage();
            }
        }
        $_SERVER = $tmpServer;
    }

    /**
     * @param $response SwooleResponse
     */
    protected function cloneComponent($response)
    {
        $app = clone $this->app;
        Yii::$app = &$app;
        $app->set('request',clone $this->app->getRequest());
        $yiiRes = clone $this->app->getResponse();
        if($yiiRes instanceof Response){
            $yiiRes->setSwooleResponse($response);
        }
        $app->set('response',$yiiRes);
        $app->set('view',clone $this->app->getView());
        $app->set('errorHandle',clone $this->app->getErrorHandler());
    }

    public function onTask(SwooleServer $serv, int $task_id, int $src_worker_id, $data)
    {
        $func = array_shift($data);
        if(is_callable($func)){
            $params[] = array_shift($data);
            call_user_func_array($func,$params);
        }
        return 1;
    }

    public function onFinish(SwooleServer $serv, int $task_id, string $data)
    {
        //echo $data;
    }

    /**
     * @param SwooleServer $server
     * @param $worker_id
     */
    public function onWorkerStop(SwooleServer $server, $worker_id)
    {
        if(!$server->taskworker){
            //TODO 由于日志都保存在work进程中,task进程的日志暂时不处理
            Yii::$app->setIsShutdown(true);
            Yii::getLogger()->flush(Logger::FLUSH_SHUTDOWN);
        }
    }

    /**
     * 初始化一些可以复用的组件
     */
    private function initComponent()
    {
        $this->app->getSecurity();
        $this->app->getUrlManager();
        $this->app->getRequest()->setBaseUrl(null);
        $this->app->getRequest()->setScriptUrl($this->index);
        $this->app->getRequest()->setScriptFile($this->index);
        $this->app->getRequest()->setUrl(null);

        if($this->app->has('session',true)){
            $this->app->getSession();
        }
        $this->app->getView();
        $this->app->getDb();
        $this->app->getUser();
        if($this->app->has('mailer',true)){
            $this->app->getMailer();
        }
        $dispatcher = Yii::getLogger()->dispatcher;
        if($dispatcher instanceof Dispatcher){
            $dispatcher->aopExport();
        }
    }

    /**
     * 用户请求结束执行的动作,该方法中执行的任务类似于register_shut_down,不会影响用户请求结果
     */
    protected function onEndRequest()
    {
        if(Yii::$app->has('session',true)){
            Yii::$app->getSession()->close();
        }
        Yii::getLogger()->flush(true);
        Yii::$app = $this->app;
    }
}