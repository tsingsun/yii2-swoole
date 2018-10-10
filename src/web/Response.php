<?php
/**
 * Created by PhpStorm.
 * User: tsingsun
 * Date: 2017/3/1
 * Time: 下午3:04
 */

namespace tsingsun\swoole\web;

use Yii;
use yii\base\InvalidConfigException;

/**
 * Class Response for swoole
 * @package tsingsun\swoole\web
 */
class Response extends \yii\web\Response
{
    /**
     * @var \Swoole\Http\Response
     */
    protected $swooleResponse;

    /**
     * 重置Response,重置时清变量
     * @param $res
     */
    public function setSwooleResponse($res)
    {
        $this->swooleResponse = $res;
        $this->clear();
    }

    public function getSwooleResponse()
    {
        return $this->swooleResponse;
    }

    public function send()
    {
        return parent::send();
    }

    /**
     * @inheritdoc
     */
    protected function sendHeaders()
    {
        if (!$this->swooleResponse) {
            parent::sendHeaders();
            return;
        }

        $headers = $this->getHeaders();
        if ($headers->count > 0) {
            foreach ($headers as $name => $values) {
                $name = str_replace(' ', '-', ucwords(str_replace('-', ' ', $name)));
                foreach ($values as $value) {
                    $this->swooleResponse->header($name, $value);
                }
            }
        }
        $this->swooleResponse->status($this->getStatusCode());
        $this->sendCookies();
    }

    /**
     * @inheritdoc
     */
    protected function sendCookies()
    {
        if (!$this->swooleResponse) {
            return parent::sendCookies();
        }

        if ($this->getCookies()->count == 0) {
            return;
        }
        $request = Yii::$app->getRequest();
        if ($request->enableCookieValidation) {
            if ($request->cookieValidationKey == '') {
                throw new InvalidConfigException(get_class($request) . '::cookieValidationKey must be configured with a secret key.');
            }
            $validationKey = $request->cookieValidationKey;
        }
        foreach ($this->getCookies() as $cookie) {
            $value = $cookie->value;
            if ($cookie->expire != 1 && isset($validationKey)) {
                $value = Yii::$app->getSecurity()->hashData(serialize([$cookie->name, $value]), $validationKey);
            }
            $this->swooleResponse->cookie($cookie->name, $value, $cookie->expire, $cookie->path, $cookie->domain, $cookie->secure, $cookie->httpOnly);
        }

    }

    /**
     * @inheritdoc
     */
    protected function sendContent()
    {
        if (!$this->swooleResponse) {
            return parent::sendContent();
        }
        if ($this->stream === null) {
            if ($this->content) {
                $this->swooleResponse->end($this->content);
            } else {
                $this->swooleResponse->end();
            }
            return;
        }

        set_time_limit(0); // Reset time limit for big files
        $chunkSize = 2 * 1024 * 1024; // 2MB per chunk swoole limit

        if (is_array($this->stream)) {
            list ($handle, $begin, $end) = $this->stream;
            fseek($handle, $begin);
            while (!feof($handle) && ($pos = ftell($handle)) <= $end) {
                if ($pos + $chunkSize > $end) {
                    $chunkSize = $end - $pos + 1;
                }
                $this->swooleResponse->write(fread($handle, $chunkSize));
                flush(); // Free up memory. Otherwise large files will trigger PHP's memory limit.
            }
            fclose($handle);
        } else {
            while (!feof($this->stream)) {
                $this->swooleResponse->write(fread($this->stream, $chunkSize));
                flush();
            }
            fclose($this->stream);
        }
        $this->swooleResponse->end();
    }
}