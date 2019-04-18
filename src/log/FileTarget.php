<?php
/**
 * Created by PhpStorm.
 * User: tsingsun
 * Date: 2017/9/5
 * Time: 下午6:34
 */

namespace tsingsun\swoole\log;


use tsingsun\swoole\web\Request;
use yii\base\InvalidConfigException;
use yii\helpers\ArrayHelper;
use Swoole\Coroutine;

/**
 * 使用异步的方式来实现日志写入操作
 * @package tsingsun\swoole\log
 */
class FileTarget extends \yii\log\FileTarget
{
    public function export()
    {
        $text = implode("\n", array_map([$this, 'formatMessage'], $this->messages)) . "\n";

        if ($this->enableRotation) {
            // clear stat cache to ensure getting the real current file size and not a cached one
            // this may result in rotating twice when cached file size is used on subsequent calls
            clearstatcache();
        }
        if ($this->enableRotation && @filesize($this->logFile) > $this->maxFileSize * 1024) {
            if (($fp = @fopen($this->logFile, 'a')) === false) {
                throw new InvalidConfigException("Unable to append to log file: {$this->logFile}");
            }
            @flock($fp, LOCK_EX);
            $this->rotateFiles();
            if ($this->fileMode !== null) {
                @chmod($this->logFile, $this->fileMode);
            }
            @flock($fp, LOCK_UN);
            @fclose($fp);
            @file_put_contents($this->logFile, $text, FILE_APPEND | LOCK_EX);
        } else {
            if (COROUTINE_ENV) {
                @Coroutine::writeFile($this->logFile,$text,FILE_APPEND);
            } else {
                @swoole_async_writefile($this->logFile,$text,null,FILE_APPEND);
            }
        }
    }

    /**
     * TODO 格式化参数,有个缺点,当log输出时.都会重复输出问题
     * @return string
     * @throws InvalidConfigException
     */
    protected function getContextMessage()
    {
        if(empty($this->logVars)){
            return '';
        }
        /** @var Request $request */
        $request = \Yii::$app->getRequest();
        $context = ArrayHelper::filter([
            '_GET' => $request->isGet ? $request->getQueryParams() : [],
            '_POST' => !$request->isGet ? $request->getBodyParams() : [],
            '_SERVER' => $request->swooleRequest->server,
            '_FILES' => $request->swooleRequest->files,
            '_COOKIE' => $request->swooleRequest->cookie,
        ], $this->logVars);
        $result = [];
        foreach ($context as $key => $value) {
            //vardumper有性能问题及导致进程退出,var_export,但都能看
            //$result[] = "\${$key} = " . VarDumper::dumpAsString($value);
            $result[] = "\${$key} = " . var_export($value,true);
        }
        return implode("\n\n", $result);
    }
}