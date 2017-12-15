<?php
/**
 * Created by PhpStorm.
 * User: tsingsun
 * Date: 2017/12/15
 * Time: 上午11:16
 */

namespace tsingsun\swoole\web;

use Yii;
use yii\base\InvalidParamException;

/**
 * swoole对原生php session的部分操作存在部分不支持,如session_set_cookie_params
 * @package tsingsun\swoole\web
 */
trait SessionTrait
{
    private $_hasSessionId = null;
    /**
     * @var array parameter-value pairs to override default session cookie parameters that are used for session_set_cookie_params() function
     * Array may have the following possible keys: 'lifetime', 'path', 'domain', 'secure', 'httponly'
     * @see http://www.php.net/manual/en/function.session-set-cookie-params.php
     */
    private $_cookieParams = ['httponly' => true];

    public function close()
    {
        parent::close();
        //在session_write_close后,清除当前进程session数据
        $_SESSION = [];
        session_abort();
        $this->setHasSessionId(null);
        $this->setCookieParams(['httponly' => true]);
    }

    public function open()
    {
        if ($this->getIsActive()) {
            return;
        }

        $this->registerSessionHandler();

        $this->setCookieParamsInternal();
        $sid = $_COOKIE[$this->getName()] ?? null;
        if ($sid) {
            $this->setId($sid);
        } else {
            //7.1,有新的方法
            if (version_compare(PHP_VERSION, '7.1', '<')) {
                $sid = md5($_SERVER['REMOTE_ADDR'] . microtime() . rand(0, 100000));
            } else {
                $sid = session_create_id();
            }
            $this->setId($sid);

        }

        @session_start();

        if ($this->getIsActive()) {
            Yii::info('Session started', __METHOD__);
            $this->updateFlashCounters();
            $this->setPHPSessionID();
        } else {
            $error = error_get_last();
            $message = isset($error['message']) ? $error['message'] : 'Failed to start session.';
            Yii::error($message, __METHOD__);
        }
    }

    private function setPHPSessionID()
    {
        if ($this->getHasSessionId() === false) {
            /** @var Response $response */
            $response = \Yii::$app->getResponse();
            $data = $this->getCookieParams();
            if (isset($data['lifetime'], $data['path'], $data['domain'], $data['secure'], $data['httponly'])) {
                $expire = $data['lifetime'] ? time() + $data['lifetime'] : 0;
                $response->getSwooleResponse()->cookie($this->getName(), $this->getId(), $expire, $data['path'], $data['domain'], $data['secure'], $data['httponly']);
            } else {
                $response->getSwooleResponse()->cookie($this->getName(), $this->getId());
            }
        }
    }

    /**
     * Sets the session cookie parameters.
     * This method is called by [[open()]] when it is about to open the session.
     * @throws InvalidParamException if the parameters are incomplete.
     * @see http://us2.php.net/manual/en/function.session-set-cookie-params.php
     */
    private function setCookieParamsInternal()
    {
        $data = $this->getCookieParams();
        if (isset($data['lifetime'], $data['path'], $data['domain'], $data['secure'], $data['httponly'])) {
            session_set_cookie_params($data['lifetime'], $data['path'], $data['domain'], $data['secure'], $data['httponly']);
        } else {
            throw new InvalidParamException('Please make sure cookieParams contains these elements: lifetime, path, domain, secure and httponly.');
        }
    }
}