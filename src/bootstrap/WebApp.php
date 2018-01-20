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
            $app = Yii::$app;
            $app->getRequest()->setSwooleRequest($request);
            $app->getResponse()->setSwooleResponse($response);

            $app->run();

        } catch (\Exception $e) {
            $app->getErrorHandler()->handleException($e);
        } catch (\Throwable $e) {
            $eh = $app->getErrorHandler();
            if ($eh instanceof ErrorHandler) {
                $eh->handleFallbackExceptionMessage($e, $e->getPrevious());
            }
        } finally {
            $app->trigger(Application::EVENT_AFTER_RUN);
        }
    }

}