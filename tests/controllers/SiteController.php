<?php

namespace yiiunit\extension\daemon\controllers;

use Yii;
use yii\base\Exception;
use yii\log\Logger;
use tsingsun\daemon\helper\TaskHelper;
use yii\web\Controller;
use yii\web\Response;

class SiteController extends Controller
{
    /**
     * Displays homepage.
     *
     * @return string
     */
    public function actionIndex()
    {
//        if(!Yii::$app->session->has('a')){
//            Yii::$app->session['a'] =  rand();
//        }
        return $this->render('index');
    }

    public function actionJson(){
        Yii::$app->response->format = Response::FORMAT_JSON;
        return ['time' => time(), 'str' => 'hello'];
    }

    public function actionLog(){
        $al = new AccessLog();
        Yii::$app->set('accessLog',$al);
        Yii::getLogger()->log($al,Logger::LEVEL_ACCESS);
    }

    public function actionException(){
        throw new Exception('test error');
    }

    public function actionError()
    {
        return $a;      
    }

    public function actionDb()
    {
        $val = Yii::$app->getDb()->createCommand('select * from new_table')->query();
        return count($val);
    }

    public function actionEcho()
    {
        echo 'hello world';
    }

    public function actionTimeout()
    {
        yield TaskHelper::taskSleep(40000);
    }
}
