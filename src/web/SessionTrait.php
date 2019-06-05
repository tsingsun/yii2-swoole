<?php
/**
 * Created by PhpStorm.
 * User: tsingsun
 * Date: 2017/12/15
 * Time: 上午11:16
 */

namespace tsingsun\swoole\web;

use Yii;
use yii\base\InvalidArgumentException;

/**
 * 协程模式下的session处理类
 *
 * @see \tsingsun\swoole\web\cm\SessionTrait 非协程下的引用的类
 * @package tsingsun\swoole\web
 */
trait SessionTrait
{
    /**
     * @var null|bool 客户端是否存在phpsessid
     */
    private $_hasSessionId = null;

    private $_isActive = false;

    private $session_id;

    protected $session = [];
    /**
     * @var array parameter-value pairs to override default session cookie parameters that are used for session_set_cookie_params() function
     * Array may have the following possible keys: 'lifetime', 'path', 'domain', 'secure', 'httponly'
     * @see http://www.php.net/manual/en/function.session-set-cookie-params.php
     */
    private $_cookieParams = ['httponly' => true];

    public function close()
    {
        if ($this->getIsActive()) {
            Yii::debug('Session closed in swoole', __METHOD__);
            YII_DEBUG ? $this->sessionWriteClose() : @$this->sessionWriteClose();
        }
    }

    public function destroy()
    {
        $this->session = [];
        if ($this->getIsActive()) {
            $this->destroySession($this->getId());
        }
    }

    public function getIsActive()
    {
        return $this->_isActive;
    }


    public function open()
    {
        if ($this->getIsActive()) {
            return;
        }
//        $this->registerSessionHandler();
        $this->setCookieParamsInternal();

        @$this->sessionStart();
//        @session_start();
        if ($this->getIsActive()) {
            Yii::info('Session started in swoole', __METHOD__);
            $this->updateFlashCounters();
            $this->implantSessionId();
        } else {
            $error = error_get_last();
            $message = isset($error['message']) ? $error['message'] : 'Failed to start session in swoole.';
            Yii::error($message, __METHOD__);
        }
    }

    public function getId()
    {
        return $this->session_id;
    }

    public function setId($value)
    {
        $this->session_id = $value;
    }

    /**
     * Returns a value indicating whether the current request has sent the session ID.
     * The default implementation will check cookie and $_GET using the session name.
     * If you send session ID via other ways, you may need to override this method
     * or call [[setHasSessionId()]] to explicitly set whether the session ID is sent.
     * @return bool whether the current request has sent the session ID.
     */
    public function getHasSessionId()
    {
        if ($this->_hasSessionId === null) {
            $name = $this->getName();
            /** @var Request $request */
            $request = Yii::$app->getRequest();
            $cookie = $request->getSwooleRequest()->cookie;
            if (!empty($cookie[$name]) && ini_get('session.use_cookies')) {
                $this->_hasSessionId = true;
            } elseif (!ini_get('session.use_only_cookies') && ini_get('session.use_trans_sid')) {
                $this->_hasSessionId = $request->get($name) != '';
            } else {
                $this->_hasSessionId = false;
            }
        }

        return $this->_hasSessionId;
    }

    /**
     * Sets the value indicating whether the current request has sent the session ID.
     * This method is provided so that you can override the default way of determining
     * whether the session ID is sent.
     * @param bool $value whether the current request has sent the session ID.
     */
    public function setHasSessionId($value)
    {
        $this->_hasSessionId = $value;
    }

    /**
     * 将生成的sessionID发送至客户端
     */
    private function implantSessionId()
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
     * @inheritdoc
     */
    public function regenerateID($deleteOldSession = false)
    {
        if ($this->getIsActive()) {
            // add @ to inhibit possible warning due to race condition
            // https://github.com/yiisoft/yii2/pull/1812
            if (YII_DEBUG && !headers_sent()) {
                $this->sessionRegenerateId($deleteOldSession);
            } else {
                @$this->sessionRegenerateId($deleteOldSession);
            }
        }
    }

    /**
     * session_regenerate_id的实现
     * @param $deleteOldSession
     */
    protected function sessionRegenerateId($deleteOldSession)
    {
        if($deleteOldSession){
            $this->destroySession($this->getId());
            $this->closeSession();
        }
        $this->_hasSessionId = false;
        $id = $this->newSessionId();
        $this->setId($id);
        $this->implantSessionId();
    }

    /**
     * Sets the session cookie parameters.
     * This method is called by [[open()]] when it is about to open the session.
     * @throws InvalidArgumentException if the parameters are incomplete.
     * @see http://us2.php.net/manual/en/function.session-set-cookie-params.php
     */
    private function setCookieParamsInternal()
    {
        $data = $this->getCookieParams();
        if (isset($data['lifetime'], $data['path'], $data['domain'], $data['secure'], $data['httponly'])) {
            //swoole 4 该方法会引起问题，不需要,此处只做检查
            //session_set_cookie_params($data['lifetime'], $data['path'], $data['domain'], $data['secure'], $data['httponly']);
        } else {
            throw new InvalidArgumentException('Please make sure cookieParams contains these elements: lifetime, path, domain, secure and httponly.');
        }
    }

    public function getCount()
    {
        $this->open();
        return count($this->session);
    }

    public function get($key, $defaultValue = null)
    {
        $this->open();
        return isset($this->session[$key]) ? $this->session[$key] : $defaultValue;
    }

    public function set($key, $value)
    {
        $this->open();
        $this->session[$key] = $value;
    }

    public function remove($key)
    {
        $this->open();
        if (isset($this->session[$key])) {
            $value = $this->session[$key];
            unset($this->session[$key]);

            return $value;
        }

        return null;
    }

    public function removeAll()
    {
        $this->open();
        foreach (array_keys($this->session) as $key) {
            unset($this->session[$key]);
        }
    }

    public function has($key)
    {
        $this->open();
        return isset($this->session[$key]);
    }

    protected function updateFlashCounters()
    {
        $counters = $this->get($this->flashParam, []);
        if (is_array($counters)) {
            foreach ($counters as $key => $count) {
                if ($count > 0) {
                    unset($counters[$key], $this->session[$key]);
                } elseif ($count == 0) {
                    $counters[$key]++;
                }
            }
            $this->session[$this->flashParam] = $counters;
        } else {
            // fix the unexpected problem that flashParam doesn't return an array
            unset($this->session[$this->flashParam]);
        }
    }

    public function getFlash($key, $defaultValue = null, $delete = false)
    {
        $counters = $this->get($this->flashParam, []);
        if (isset($counters[$key])) {
            $value = $this->get($key, $defaultValue);
            if ($delete) {
                $this->removeFlash($key);
            } elseif ($counters[$key] < 0) {
                // mark for deletion in the next request
                $counters[$key] = 1;
                $this->session[$this->flashParam] = $counters;
            }

            return $value;
        }

        return $defaultValue;
    }

    public function getAllFlashes($delete = false)
    {
        $counters = $this->get($this->flashParam, []);
        $flashes = [];
        foreach (array_keys($counters) as $key) {
            if (array_key_exists($key, $this->session)) {
                $flashes[$key] = $this->session[$key];
                if ($delete) {
                    unset($counters[$key], $this->session[$key]);
                } elseif ($counters[$key] < 0) {
                    // mark for deletion in the next request
                    $counters[$key] = 1;
                }
            } else {
                unset($counters[$key]);
            }
        }

        $this->session[$this->flashParam] = $counters;

        return $flashes;
    }

    public function setFlash($key, $value = true, $removeAfterAccess = true)
    {
        $counters = $this->get($this->flashParam, []);
        $counters[$key] = $removeAfterAccess ? -1 : 0;
        $this->session[$key] = $value;
        $this->session[$this->flashParam] = $counters;
    }

    public function addFlash($key, $value = true, $removeAfterAccess = true)
    {
        $counters = $this->get($this->flashParam, []);
        $counters[$key] = $removeAfterAccess ? -1 : 0;
        $this->session[$this->flashParam] = $counters;
        if (empty($this->session[$key])) {
            $this->session[$key] = [$value];
        } else {
            if (is_array($this->session[$key])) {
                $this->session[$key][] = $value;
            } else {
                $this->session[$key] = [$this->session[$key], $value];
            }
        }
    }

    public function removeFlash($key)
    {
        $counters = $this->get($this->flashParam, []);
        $value = isset($this->session[$key], $counters[$key]) ? $this->session[$key] : null;
        unset($counters[$key], $this->session[$key]);
        $this->session[$this->flashParam] = $counters;

        return $value;
    }

    public function removeAllFlashes()
    {
        $counters = $this->get($this->flashParam, []);
        foreach (array_keys($counters) as $key) {
            unset($this->session[$key]);
        }
        unset($this->session[$this->flashParam]);
    }

    public function offsetExists($offset)
    {
        $this->open();

        return isset($this->session[$offset]);
    }

    public function offsetGet($offset)
    {
        $this->open();

        return isset($this->session[$offset]) ? $this->session[$offset] : null;
    }

    public function offsetSet($offset, $item)
    {
        $this->open();
        $this->session[$offset] = $item;
    }

    public function offsetUnset($offset)
    {
        $this->open();
        unset($this->session[$offset]);
    }

    /**
     * 生成新的session Id
     * @return string
     */
    protected function newSessionId()
    {
        //7.1,有新的方法
        if (version_compare(PHP_VERSION, '7.1', '<')) {
            $sid = md5(Yii::$app->getRequest()->getUserIP() . microtime() . rand(0, 100000));
        } else {
            $sid = session_create_id();
        }
        return $sid;
    }

    /**
     * 内部实现session_start函数,调用openSession与readSession
     * @param null $option
     */
    public function sessionStart($option = null)
    {
        $cookie = Yii::$app->request->getSwooleRequest()->cookie;
        $sid = $cookie[$this->getName()] ?? $this->session_id;
        if ($sid) {
            $this->setId($sid);
        } else {
            $sid = $this->newSessionId();
            $this->setId($sid);
        }
        //TODO 根据GC设置启动GC进程
//        parent::openSession(session_save_path(),$this->getId());
        $data = $this->readSession($this->getId());
        $this->session = unserialize($data);
        $this->_isActive = true;
    }

    /**
     * 内部实现session_write_close方法,调用writeSession与close
     * @param string $sessionId
     * @param string $data
     */
    public function sessionWriteClose()
    {
        if (!empty($this->session)) {
            $data = serialize($this->session);
            $this->writeSession($this->getId(), $data);
        }
        $this->closeSession();
        $this->_isActive = false;
    }
}