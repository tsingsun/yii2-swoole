<?php
/**
 * Created by PhpStorm.
 * User: tsingsun
 * Date: 2018/1/15
 * Time: 下午4:41
 */

namespace tsingsun\swoole\web;

use Yii;
use yii\base\InvalidConfigException;
use yii\web\HeaderCollection;
use yii\web\RequestParserInterface;
use yii\web\NotFoundHttpException;
use yii\web\Cookie;

/**
 * Class Request
 * @package tsingsun\swoole\web
 */
class Request extends \yii\web\Request
{
    /**
     * @var \Swoole\Http\Request
     */
    public $swooleRequest;

    /**
     * 设置swoole请求,并清理变量
     * @param \Swoole\Http\Request $request
     */
    public function setSwooleRequest(\Swoole\Http\Request $request)
    {
        $this->swooleRequest = $request;
        $this->clear();
    }

    /**
     * @return \Swoole\Http\Request
     */
    public function getSwooleRequest()
    {
        return $this->swooleRequest;
    }

    /**
     * @inheritdoc
     */
    public function resolve()
    {
        $result = Yii::$app->getUrlManager()->parseRequest($this);
        if ($result !== false) {
            list ($route, $params) = $result;
            if ($this->getQueryParams() === null) {
                $this->_queryParams = $params;
            } else {
                $this->_queryParams = $params + $this->_queryParams;
            }
            return [$route, $this->getQueryParams()];
        }

        throw new NotFoundHttpException(Yii::t('yii', 'Page not found.'));
    }

    private $_headers;
    /**
     * @inheritdoc
     */
    public function getHeaders()
    {
        if ($this->_headers === null) {
            $this->_headers = new HeaderCollection();
            foreach ($this->swooleRequest->header as $name => $value) {
                $this->_headers->add($name, $value);
            }
        }
        return $this->_headers;
    }

    /**
     * @inheritdoc
     */
    public function getMethod()
    {
        if (isset($this->swooleRequest->post[$this->methodParam])) {
            return strtoupper($this->swooleRequest->post[$this->methodParam]);
        }

        if (isset($this->swooleRequest->server['HTTP_X_HTTP_METHOD_OVERRIDE'])) {
            return strtoupper($this->swooleRequest->server['HTTP_X_HTTP_METHOD_OVERRIDE']);
        }

        if (isset($this->swooleRequest->server['REQUEST_METHOD'])) {
            return strtoupper($this->swooleRequest->server['REQUEST_METHOD']);
        }

        return 'GET';
    }

    /**
     * @inheritdoc
     */
    public function getIsAjax()
    {
        return isset($this->swooleRequest->server['HTTP_X_REQUESTED_WITH']) && $this->swooleRequest->server['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest';
    }

    /**
     * @inheritdoc
     */
    public function getIsPjax()
    {
        return $this->getIsAjax() && !empty($this->swooleRequest->server['HTTP_X_PJAX']);
    }

    /**
     * @inheritdoc
     */
    public function getIsFlash()
    {
        return isset($this->swooleRequest->server['HTTP_USER_AGENT']) &&
            (stripos($this->swooleRequest->server['HTTP_USER_AGENT'], 'Shockwave') !== false || stripos($this->swooleRequest->server['HTTP_USER_AGENT'], 'Flash') !== false);
    }

    private $_rawBody;
    /**
     * @inheritdoc
     */
    public function getRawBody()
    {
        if ($this->_rawBody === null) {
            $this->_rawBody = $this->swooleRequest->rawContent();
        }
        return $this->_rawBody;
    }

    public $_bodyParams;
    /**
     * @inheritdoc
     */
    public function getBodyParams()
    {
        if ($this->_bodyParams === null) {
            if (isset($this->swooleRequest->post[$this->methodParam])) {
                $this->_bodyParams = $this->swooleRequest->post;
                unset($this->_bodyParams[$this->methodParam]);
                return $this->_bodyParams;
            }
            $contentType = $this->getContentType();
            if (($pos = strpos($contentType, ';')) !== false) {
                // e.g. application/json; charset=UTF-8
                $contentType = substr($contentType, 0, $pos);
            }
            if (isset($this->parsers[$contentType])) {
                $parser = Yii::createObject($this->parsers[$contentType]);
                if (!($parser instanceof RequestParserInterface)) {
                    throw new InvalidConfigException("The '$contentType' request parser is invalid. It must implement the yii\\web\\RequestParserInterface.");
                }
                $this->_bodyParams = $parser->parse($this->getRawBody(), $contentType);
            } elseif (isset($this->parsers['*'])) {
                $parser = Yii::createObject($this->parsers['*']);
                if (!($parser instanceof RequestParserInterface)) {
                    throw new InvalidConfigException("The fallback request parser is invalid. It must implement the yii\\web\\RequestParserInterface.");
                }
                $this->_bodyParams = $parser->parse($this->getRawBody(), $contentType);
            } elseif ($this->getMethod() === 'POST') {
                // PHP has already parsed the body so we have all params in $this->swoole->post
                $this->_bodyParams = $this->swooleRequest->post;
            } else {
                $this->_bodyParams = [];
                mb_parse_str($this->getRawBody(), $this->_bodyParams);
            }
        }
        return $this->_bodyParams;
    }

    private $_queryParams;
    /**
     * @inheritdoc
     */
    public function getQueryParams()
    {
        if ($this->_queryParams === null) {
            $this->_queryParams = $this->swooleRequest->get;
        }

        return $this->_queryParams;
    }

    /**
     * @inheritdoc
     */
    protected function loadCookies()
    {
        $cookies = [];
        if ($this->enableCookieValidation) {
            if ($this->cookieValidationKey == '') {
                throw new InvalidConfigException(get_class($this) . '::cookieValidationKey must be configured with a secret key.');
            }
            foreach ($this->getSwooleRequest()->cookie as $name => $value) {
                if (!is_string($value)) {
                    continue;
                }
                $data = Yii::$app->getSecurity()->validateData($value, $this->cookieValidationKey);
                if ($data === false) {
                    continue;
                }
                $data = @unserialize($data);
                if (is_array($data) && isset($data[0], $data[1]) && $data[0] === $name) {
                    $cookies[$name] = new Cookie([
                        'name' => $name,
                        'value' => $data[1],
                        'expire' => null,
                    ]);
                }
            }
        } else {
            foreach ($this->getSwooleRequest()->cookie as $name => $value) {
                $cookies[$name] = new Cookie([
                    'name' => $name,
                    'value' => $value,
                    'expire' => null,
                ]);
            }
        }

        return $cookies;
    }

    /**
     * @inheritdoc
     */
    protected function resolveRequestUri()
    {
        if (isset($this->swooleRequest->server['REQUEST_URI'])) {
            $requestUri = $this->swooleRequest->server['REQUEST_URI'];
            if ($requestUri !== '' && $requestUri[0] !== '/') {
                $requestUri = preg_replace('/^(http|https):\/\/[^\/]+/i', '', $requestUri);
            }
        } else {
            throw new InvalidConfigException('Unable to determine the request URI.');
        }

        return $requestUri;
    }

    /**
     * @inheritdoc
     */
    public function getQueryString()
    {
        return isset($this->swooleRequest->server['QUERY_STRING']) ? $this->swooleRequest->server['QUERY_STRING'] : '';
    }

    /**
     * @inheritdoc
     */
    public function getIsSecureConnection()
    {
        return isset($this->swooleRequest->server['HTTPS']) && (strcasecmp($this->swooleRequest->server['HTTPS'], 'on') === 0 || $_SERVER['HTTPS'] == 1)
            || isset($this->swooleRequest->server['HTTP_X_FORWARDED_PROTO']) && strcasecmp($this->swooleRequest->server['HTTP_X_FORWARDED_PROTO'], 'https') === 0;
    }

    /**
     * @inheritdoc
     */
    public function getServerName()
    {
        return isset($this->swooleRequest->server['SERVER_NAME']) ? $this->swooleRequest->server['SERVER_NAME'] : null;
    }

    /**
     * @inheritdoc
     */
    public function getServerPort()
    {
        return isset($this->swooleRequest->server['SERVER_PORT']) ? (int) $this->swooleRequest->server['SERVER_PORT'] : null;
    }

    /**
     * @inheritdoc
     */
    public function getReferrer()
    {
        return isset($this->swooleRequest->server['HTTP_REFERER']) ? $this->swooleRequest->server['HTTP_REFERER'] : null;
    }

    /**
     * @inheritdoc
     */
    public function getUserAgent()
    {
        return isset($this->swooleRequest->server['HTTP_USER_AGENT']) ? $this->swooleRequest->server['HTTP_USER_AGENT'] : null;
    }

    /**
     * @inheritdoc
     */
    public function getUserIP()
    {
        return isset($this->swooleRequest->server['REMOTE_ADDR']) ? $this->swooleRequest->server['REMOTE_ADDR'] : null;
    }

    /**
     * @inheritdoc
     */
    public function getUserHost()
    {
        return isset($this->swooleRequest->server['REMOTE_HOST']) ? $this->swooleRequest->server['REMOTE_HOST'] : null;
    }

    /**
     * @inheritdoc
     */
    public function getAuthUser()
    {
        return isset($this->swooleRequest->server['PHP_AUTH_USER']) ? $this->swooleRequest->server['PHP_AUTH_USER'] : null;
    }

    /**
     * @inheritdoc
     */
    public function getAuthPassword()
    {
        return isset($this->swooleRequest->server['PHP_AUTH_PW']) ? $this->swooleRequest->server['PHP_AUTH_PW'] : null;
    }

    private $_port;

    /**
     * @inheritdoc
     */
    public function getPort()
    {
        if ($this->_port === null) {
            $this->_port = !$this->getIsSecureConnection() && isset($this->swooleRequest->server['SERVER_PORT']) ? (int) $this->swooleRequest->server['SERVER_PORT'] : 80;
        }

        return $this->_port;
    }

    /**
     * 清理变量
     */
    public function clear()
    {
        $this->_headers = null;
        $this->_port = null;
        $this->_bodyParams = null;
        $this->_queryParams = null;
        $this->_rawBody = null;
        $this->setHostInfo(null);
        $this->setPathInfo(null);
        $this->setUrl(null);
        $this->setAcceptableContentTypes(null);
        $this->setAcceptableLanguages(null);
    }

}