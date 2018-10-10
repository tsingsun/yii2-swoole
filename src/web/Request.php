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

    public $ipHeaders = [
        'X-Forwarded-For', // Common
        'X-Real-Ip',
    ];

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

        if ($this->headers->has('X-Http-Method-Override')) {
            return strtoupper($this->headers->get('X-Http-Method-Override'));
        }

        if (isset($this->swooleRequest->server['REQUEST_METHOD'])) {
            return strtoupper($this->swooleRequest->server['REQUEST_METHOD']);
        }

        return 'GET';
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
            $this->_queryParams = $this->swooleRequest ? $this->swooleRequest->get : [];
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
        if ($this->headers->has('X-Rewrite-Url')) { // IIS
            $requestUri = $this->headers->get('X-Rewrite-Url');
        } elseif (isset($this->swooleRequest->server['REQUEST_URI'])) {
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
        if (isset($this->swooleRequest->server['HTTPS']) && (strcasecmp($this->swooleRequest->server['HTTPS'], 'on') === 0 || $this->swooleRequest->server['HTTPS'] == 1)) {
            return true;
        }
        foreach ($this->secureProtocolHeaders as $header => $values) {
            if (($headerValue = $this->headers->get($header, null)) !== null) {
                foreach ($values as $value) {
                    if (strcasecmp($headerValue, $value) === 0) {
                        return true;
                    }
                }
            }
        }
        return false;
    }

    /**
     * @inheritdoc
     */
    public function getServerName()
    {
        return $this->headers->get('SERVER_NAME');
    }

    /**
     * @inheritdoc
     */
    public function getServerPort()
    {
        return isset($this->swooleRequest->server['SERVER_PORT']) ? (int)$this->swooleRequest->server['SERVER_PORT'] : null;
    }

    /**
     * @inheritdoc
     */
    public function getRemoteIP()
    {
        return isset($this->swooleRequest->server['REMOTE_ADDR']) ? $this->swooleRequest->server['REMOTE_ADDR'] : null;
    }

    /**
     * @inheritdoc
     */
    public function getRemoteHost()
    {
        return isset($this->swooleRequest->server['REMOTE_HOST']) ? $this->swooleRequest->server['REMOTE_HOST'] : null;
    }

    /**
     * @inheritdoc
     */
    public function getAuthCredentials()
    {
        $auth_token = $this->getHeaders()->get('Authorization');
        if ($auth_token !== null && strncasecmp($auth_token, 'basic', 5) === 0) {
            $parts = array_map(function ($value) {
                return strlen($value) === 0 ? null : $value;
            }, explode(':', base64_decode(mb_substr($auth_token, 6)), 2));

            if (count($parts) < 2) {
                return [$parts[0], null];
            }

            return $parts;
        }

        return [null, null];
    }

    /**
     * 清理变量
     */
    public function clear()
    {
        $this->_headers = null;
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