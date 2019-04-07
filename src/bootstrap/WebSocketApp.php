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
use tsingsun\swoole\di\Container;
use tsingsun\swoole\web\Application;
use yii\base\Event;
use yii\web\ForbiddenHttpException;

class WebSocketApp extends WebApp
{

    /**
     * @var bool whether use http server,if true the OnRequest is like webApp
     */
    private $useHttpServer = false;
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
     * @var callable handle return data
     */
    public $formatData;

    public function onRequest($request, $response)
    {
        if ($this->useHttpServer) {
            return parent::onRequest($request, $response);
        }
        return false;
    }

    /**
     * 客户端连接
     * @param Server $ws
     * @param Request $request
     */
    public function onOpen(Server $ws, Request $request)
    {
        $pathInfo = $request->server['path_info'];
        $request->server['path_info'] = $pathInfo . '/open';
        $data = $this->handleWsRequest($request, null);
        $ws->push($request->fd, $this->formatResponse($data));
        if ($data instanceof \Throwable) {
            $ws->close($request->fd);
        }
        $this->routes[$request->fd] = $pathInfo;
    }

    /**
     * 消息通讯接口
     * @param Server $ws
     * @param Frame $frame
     */
    public function onMessage(Server $ws, Frame $frame)
    {
        $psd = $this->parseFrameProtocol($frame);
        $data = $this->handleWsRequest(null, null);
        $ws->push($frame->fd, $data);
    }

    public function onClose(Server $ws, $fd)
    {
        if (isset($this->routes[$fd])) {
            $this->dataRoute = $this->routes[$fd] . '/close';
            $this->handleWsRequest(null, null);
            unset($this->routes[$fd]);
        }
    }

    /**
     * 协议转为路由,如果协议不包含路由信息,则表示在启动类中执行请求业务处理
     * Frame协议为针对Yii MVC方式进行定义
     * @param Frame $frame
     */
    protected function parseFrameProtocol($frame)
    {
        $data = json_decode($frame->data, true);
        if (json_last_error() == JSON_ERROR_NONE) {
            if (isset($data['route']) && isset($data['content'])) {
                return [$data['route'],$data['content']];
            }
        }
        return [$this->routes[$frame->fd] . '/message',$frame->data];
    }

    /**
     * @param Request $request
     * @param $response
     * @return \Exception|mixed|\Throwable|ForbiddenHttpException
     */
    public function handleWsRequest($request, $response)
    {
        if (COROUTINE_ENV) {
            //协程环境每次都初始化容器,以做协程隔离
            \Yii::$context->setContainer(new Container());
        }

        try {
            $app = new Application($this->appConfig);
            if ($request) {
                $app->getRequest()->setSwooleRequest($request);
            } else {

            }
            $app->on(Application::EVENT_AFTER_RUN, [$this, 'onHandleRequestEnd']);

//            $app->beforeRun();
            $app->state = $app::STATE_BEFORE_REQUEST;
            $app->trigger($app::EVENT_BEFORE_REQUEST);

            $app->state = $app::STATE_HANDLING_REQUEST;
            $response = $app->handleRequest($app->getRequest());

            $app->state = $app::STATE_AFTER_REQUEST;
            $app->trigger($app::EVENT_AFTER_REQUEST);
            $app->state = $app::STATE_SENDING_RESPONSE;
            $app->trigger($app::EVENT_AFTER_RUN);
            return $response->data;
        } catch (ForbiddenHttpException $fe) {
            $app->getErrorHandler()->logException($fe);
            return $fe;
        } catch (\Exception $e) {
            $app->getErrorHandler()->logException($e);
            return $e;
        } catch (\Throwable $t) {
            $app->getErrorHandler()->logException($t);
            return $t;
        } finally {
            if (COROUTINE_ENV) {
                \Yii::$context->removeCurrentCoroutineData();
            }
        }
    }

    /**
     * 请求处理结束事件
     * @param Event $event
     */
    public function onHandleRequestEnd(Event $event)
    {
        /** @var Application $app */
        $app = $event->sender;
        if ($app->state == -1) {
            $app->getLog()->logger->flush(true);
        }
    }

    /**
     * @param mixed|ForbiddenHttpException|\Exception|\Throwable $data
     * @return mixed
     */
    public function formatResponse($data)
    {
        $func = $this->formatData;
        if (is_callable($func)) {
            $result = $func($data);
            if (!is_string($result)) {
                $result = json_encode(['errors' => [['code' => '500', 'message' => 'the formatData call return value must be string']]]);
            }
            return $result;
        } elseif ($data instanceof \Throwable) {
            $result = ['errors' => [['code' => $data->getCode(), 'message' => $data->getMessage()]]];
        } else {
            $result = ['data' => $data];
        }

        return json_encode($result);
    }
}