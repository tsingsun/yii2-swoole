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
use tsingsun\swoole\web\ErrorHandler;
use tsingsun\swoole\web\Response;
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
            //YII对于协程支持有天然缺陷
//            $coroutine = Yii::$app->run();
//            $app->task = new Task($coroutine, Yii::$app);
//            $app->task->run();
//            return;
            $yiiRes = Yii::$app->getResponse();
            if ($yiiRes instanceof Response) {
                $yiiRes->setSwooleResponse($response);
            }
            Yii::$app->run();

        } catch (\Exception $e) {
            Yii::$app->getErrorHandler()->handleException($e);
        } catch (\Throwable $e) {
            $eh = Yii::$app->getErrorHandler();
            if ($eh instanceof ErrorHandler) {
                $eh->handleFallbackExceptionMessage($e, $e->getPrevious());
            }
        }
    }

}