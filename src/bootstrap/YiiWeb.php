<?php
/**
 * Created by PhpStorm.
 * User: tsingsun
 * Date: 2017/3/7
 * Time: 上午10:41
 */

namespace yii\swoole\bootstrap;

use Yii;
use yii\swoole\di\Container;
use yii\swoole\log\Dispatcher;
use yii\swoole\log\Logger;
use yii\swoole\server\Server;
use yii\swoole\web\Application;
use Swoole\Http\Request as SwooleRequest;
use Swoole\Http\Response as SwooleResponse;
use yii\swoole\web\ErrorHandle;
use yii\swoole\web\Response;
use Swoole\Server as SwooleServer;

/**
 * Yii starter for swoole server
 * @package yii\swoole\bootstrap
 */
class YiiWeb implements BootstrapInterface
{
    public $index = '/index.php';

    /**
     * @var Server
     */
    private $server;

    /**
     * @var callable
     */
    public $init;
    /**
     * @var \yii\swoole\web\Application
     */
    public $app;

    public function __construct(Server $server)
    {
        $this->server = $server;
    }

    public function onWorkerStart(SwooleServer $server,$worker_id)
    {
        //使application运行时不会报错
        $_SERVER['PHP_SELF'] = $_SERVER['SCRIPT_NAME'] = $this->index;
        $_SERVER['SCRIPT_FILENAME'] = $this->server->root.$this->index;

        $initFunc = $this->init;
        if($initFunc instanceof \Closure){
            $initFunc($this);
        }
        $this->app->server = $this->server;
        Yii::setAlias('@webroot', $this->server->root);
        Yii::setAlias('@web', '/');
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

        $file = $this->server->root . $this->index;

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
            $eh = Yii::$app->getErrorHandler();
            if($eh instanceof ErrorHandle){
                $eh->handleFallbackExceptionMessage($e,$e->getPrevious());
            }
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
            Yii::getLogger()->flush(true);
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
    }

    /**
     * 用户请求结束执行的动作,该方法中执行的任务类似于register_shut_down,不会影响用户请求结果
     */
    protected function onEndRequest()
    {
        if(Yii::$app->has('session',true)){
            Yii::$app->getSession()->close();
        }
        $logger = Yii::getLogger();
        if($logger instanceof Logger && $logger->hasError){
            //如果存在异常，则输出
            $logger->flush(true);
            $logger->hasError = false;
        }else{
            $logger->flush();
        }
        Yii::$app = $this->app;
    }
}