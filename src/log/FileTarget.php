<?php
/**
 * Created by PhpStorm.
 * User: tsingsun
 * Date: 2017/9/5
 * Time: 下午6:34
 */

namespace yii\swoole\log;


use yii\base\InvalidConfigException;

/**
 * 使用异步的方式来实现日志写入操作
 * @package yii\swoole\log
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
            @swoole_async_writefile($this->logFile,$text,null,FILE_APPEND);
        }
    }
}