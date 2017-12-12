<?php

namespace yiiunit\extension\swoole\controllers;

use Yii;
use yii\base\Exception;
use yii\filters\auth\CompositeAuth;
use yii\filters\auth\QueryParamAuth;
use yii\log\Logger;
use tsingsun\swoole\helper\TaskHelper;
use yii\web\Controller;
use yii\web\Response;

class WebSocketController extends Controller
{
    public $enableCsrfValidation = false;
    public function behaviors()
    {
        $behaviors = parent::behaviors();
        $behaviors['authenticator'] =[
            'class'=>QueryParamAuth::className(),
        ];
        return $behaviors;
    }

    /**
     * Displays homepage.
     *
     * @return string
     */
    public function actionOpen()
    {
        return 'hello';
    }

    public function actionMessage()
    {
        return rand(100,1000);
    }

    public function actionClose()
    {
        return 'close';
    }

}
