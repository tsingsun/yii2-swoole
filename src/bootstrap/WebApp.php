<?php
/**
 * Created by PhpStorm.
 * User: tsingsun
 * Date: 2017/3/7
 * Time: 上午10:41
 */

namespace tsingsun\swoole\bootstrap;

use Swoole\Http\Request as SwooleRequest;
use Swoole\Http\Response as SwooleResponse;
use tsingsun\swoole\web\Application;
use tsingsun\swoole\web\ErrorHandler;
use Yii;
use yii\base\Event;

/**
 * Yii starter for swoole server
 * @package tsingsun\swoole\bootstrap
 */
class WebApp extends BaseBootstrap
{

    /**
     * @param SwooleRequest $request
     * @param SwooleResponse $response
     * @throws \Exception
     * @throws \Throwable
     */
    public function handleRequest($request, $response)
    {
        try {
            $app = new Application($this->appConfig);
            $app->getRequest()->setSwooleRequest($request);
            $app->getResponse()->setSwooleResponse($response);
            $app->on(Application::EVENT_AFTER_RUN, [$this, 'onHandleRequestEnd']);

            $app->run();
            $app->trigger(Application::EVENT_AFTER_RUN);

        } catch (\Exception $e) {
            $app->getErrorHandler()->handleException($e);
        } catch (\Throwable $e) {
            $eh = $app->getErrorHandler();
            if ($eh instanceof ErrorHandler) {
                $eh->handleFatalError(true);
            }
        }
    }

    public function onHandleRequestEnd(Event $event)
    {
        /** @var Application $app */
        $app = $event->sender;
        if ($app->has('session', true)) {
            $app->getSession()->close();
        }
    }

}