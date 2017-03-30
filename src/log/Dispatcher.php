<?php
/**
 * Created by PhpStorm.
 * User: tsingsun
 * Date: 2017/3/23
 * Time: 上午9:53
 */

namespace yii\swoole\log;

use Yii;
use yii\helpers\FileHelper;
use yii\log\FileAccessTarget;
use yii\log\FileTarget;
use Swoole\Server;
use yii\base\ErrorHandler;
use yii\swoole\web\Application;

class Dispatcher extends \yii\log\Dispatcher
{
    /**
     * @param $isTaskProcess bool 是否为task进程
     */
    public function aopExport()
    {
        foreach ($this->targets as $target){
            runkit_method_copy($target->className(),'asyncExport',$target->className(),'export');
            runkit_method_redefine($target->className(),'export',function() use ($target){
                /**
                 * @var $app Application
                 */
                $app = Yii::$app;
                if(property_exists($target,'logFile')){
                    //重新定义File;
                    $logPath = dirname($target->logFile);
                    if (!is_dir($logPath)) {
                        FileHelper::createDirectory($logPath, $this->dirMode, true);
                    }
                    $target->logFile = $logPath.'/'.date('ymd') . '.log';
                }
                $swoole = $app->server->getSwoole();
                $callback = [$target,'asyncExport'];
                if($swoole instanceof Server && !$swoole->taskworker && !Yii::$app->isShutdown){
                    //Exception不能被序列化,会报错.
                    $swoole->task([$callback],-1,function (Server $serv, int $task_id, string $data){
                        //不需要处理
                    });
                }else{
                    //task线程不能投递异步任务
                    $target->asyncExport();
                }
            });
        }
    }

    /**
     * @param array $messages
     * @param bool|string $final,if 'shutdown',export all log
     */
    public function dispatch($messages, $final)
    {
        $targetErrors = [];
        foreach ($this->targets as $target) {
            if ($target->enabled) {
                try {
                    if($target instanceof FileTarget){
                        if($final === Logger::FLUSH_SHUTDOWN){
                            //当为进程shutdown时,输出日志
                            $target->collect($messages, true);
                        }
                        //为了较好的处理并发,涉及文件IO的都不立即输出,会先进行缓存.
                        $target->collect($messages, false);
                    }else{
                        //非文件IO型的,暂时允许立即输出.
                        $target->collect($messages, $final);
                    }

                } catch (\Exception $e) {
                    $target->enabled = false;
                    $targetErrors[] = [
                        'Unable to send log via ' . get_class($target) . ': ' . ErrorHandler::convertExceptionToString($e),
                        Logger::LEVEL_WARNING,
                        __METHOD__,
                        microtime(true),
                        [],
                    ];
                } catch (\Error $e) {
                    //in shutdown function, fatal error is throw as Error,it must be handled
                    $target->enabled = false;
                    $targetErrors[] = [
                        'Unable to send log via ' . get_class($target) . ': ' . ErrorHandler::convertExceptionToString($e),
                        Logger::LEVEL_WARNING,
                        __METHOD__,
                        microtime(true),
                        [],
                    ];
                }
            }
        }

        if (!empty($targetErrors)) {
            $this->dispatch($targetErrors, true);
        }
    }


}