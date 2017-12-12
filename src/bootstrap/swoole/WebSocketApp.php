<?php
/**
 * Created by PhpStorm.
 * User: tsingsun
 * Date: 2017/12/9
 * Time: 上午11:27
 */

namespace tsingsun\daemon\bootstrap\swoole;

use Swoole\WebSocket\Frame;
use Swoole\WebSocket\Server;
use Swoole\Http\Request;
use Yii;
use yii\base\Event;
use yii\web\ForbiddenHttpException;

class WebSocketApp extends WebApp
{
    /**
     * @var string current message route
     */
    private $dataRoute;
    /**
     * @var mixed current message content
     */
    private $dataContent;
    /**
     * @var array the format use fd=>controller
     */
    private $routes;
    /**
     * 客户端连接
     * @param Server $ws
     * @param Request $request
     */
    public function onOpen(Server $ws,Request $request)
    {
        $this->dataRoute = $request->server['path_info'].'/open';
        $data = $this->onRequest($request,null);
        $this->routes[$request->fd] = $request->server['path_info'];
        $ws->push($request->fd, $data);
    }

    /**
     * 消息通讯接口
     * @param Server $ws
     * @param Frame $frame
     */
    public function onMessage(Server $ws,Frame $frame)
    {
        $this->parseFrameProtocol($frame);
        $data = $this->onRequest(null,null);
        $ws->push($frame->fd, $data);
    }

    public function onClose(Server $ws, $fd)
    {
        if(isset($this->routes[$fd])){
            $this->dataRoute = $this->routes[$fd].'/close';
            $this->onRequest(null,null);
            unset($this->routes[$fd]);
        }
    }

    /**
     * 协议转为路由,如果协议不包含路由信息,则表示在启动类中执行请求业务处理
     * Frame协议为针对Yii MVC方式进行定义
     * @param $frame
     */
    protected function parseFrameProtocol($frame)
    {
        $data = json_decode($frame->data,true);
        if(isset($data['route']) && isset($data['content'])){
            $this->dataRoute = $data['route'];
            $this->dataContent = $data['content'];
        }
    }

    /**
     * 请求前事件
     * @param Event $event
     */
    public function onBeforeRequest(Event $event)
    {
        parent::onBeforeRequest($event);
        Yii::$app->request->setPathInfo($this->dataRoute);
        Yii::$app->request->setBodyParams($this->dataContent);
    }


    /**
     * @inheritdoc
     */
    public function handleRequest($request,$response)
    {
        try{
            Yii::$app->beforeRun();
            Yii::$app->state = Yii::$app::STATE_BEFORE_REQUEST;
            Yii::$app->trigger(Yii::$app::EVENT_BEFORE_REQUEST);

            Yii::$app->state = Yii::$app::STATE_HANDLING_REQUEST;
            $response = Yii::$app->handleRequest(Yii::$app->getRequest());

            Yii::$app->state = Yii::$app::STATE_AFTER_REQUEST;
            Yii::$app->trigger(Yii::$app::EVENT_AFTER_REQUEST);
            Yii::$app->state = Yii::$app::STATE_SENDING_RESPONSE;

            return json_encode(['data'=>$response->data]);
        }catch (ForbiddenHttpException $fe){
            Yii::$app->getErrorHandler()->logException($fe);
            return json_encode(['errors'=>[['code'=>$fe->getCode(),'message'=>$fe->getMessage()]]]);
        }catch (\Exception $e){
            Yii::$app->getErrorHandler()->logException($e);
            return json_encode(['errors'=>[['code'=>$e->getCode(),'message'=>$e->getMessage()]]]);
        }catch (\Throwable $t){
            Yii::$app->getErrorHandler()->logException($t);
            return json_encode(['errors'=>[['code'=>$t->getCode(),'message'=>$t->getMessage()]]]);
        }finally{
            Yii::$app->trigger(Yii::$app::EVENT_AFTER_RUN);
        }
    }
}