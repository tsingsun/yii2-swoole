<?php
/**
 * Created by PhpStorm.
 * User: tsingsun
 * Date: 2017/12/9
 * Time: 上午11:27
 */

namespace tsingsun\swoole\bootstrap;

use Swoole\WebSocket\Frame;
use Swoole\WebSocket\Server;
use Swoole\Http\Request;
use tsingsun\swoole\web\Application;
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
    public function onOpen(Server $ws, Request $request)
    {
        $this->dataRoute = $request->server['path_info'] . '/open';
        $data = $this->onRequest($request, null);
        $this->routes[$request->fd] = $request->server['path_info'];
        $ws->push($request->fd, json_encode($data));
    }

    /**
     * 消息通讯接口
     * @param Server $ws
     * @param Frame $frame
     */
    public function onMessage(Server $ws, Frame $frame)
    {
        $this->parseFrameProtocol($frame);
        $data = $this->onRequest(null, null);
        $ws->push($frame->fd, json_encode($data));
    }

    public function onClose(Server $ws, $fd)
    {
        if (isset($this->routes[$fd])) {
            $this->dataRoute = $this->routes[$fd] . '/close';
            $this->onRequest(null, null);
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
        $data = json_decode($frame->data, true);
        if (isset($data['route']) && isset($data['content'])) {
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
    public function handleRequest($request, $response)
    {
        try {
            $app = new Application($this->appConfig);
            $app->beforeRun();
            $app->state = $app::STATE_BEFORE_REQUEST;
            $app->trigger($app::EVENT_BEFORE_REQUEST);

            $app->state = $app::STATE_HANDLING_REQUEST;
            $response = $app->handleRequest($app->getRequest());

            $app->state = $app::STATE_AFTER_REQUEST;
            $app->trigger($app::EVENT_AFTER_REQUEST);
            $app->state = $app::STATE_SENDING_RESPONSE;

            return ['data' => $response->data];
        } catch (ForbiddenHttpException $fe) {
            $app->getErrorHandler()->logException($fe);
            return ['errors' => [['code' => $fe->getCode(), 'message' => $fe->getMessage()]]];
        } catch (\Exception $e) {
            $app->getErrorHandler()->logException($e);
            return ['errors' => [['code' => $e->getCode(), 'message' => $e->getMessage()]]];
        } catch (\Throwable $t) {
            $app->getErrorHandler()->logException($t);
            return ['errors' => [['code' => $t->getCode(), 'message' => $t->getMessage()]]];
        } finally {
            $app->trigger($app::EVENT_AFTER_RUN);
        }
    }
}