<?php

namespace tsingsun\daemon\web;

use Yii;
use yii\base\BootstrapInterface;
use yii\base\InvalidConfigException;
use yii\base\ExitException;
use yii\base\InvalidRouteException;
use yii\helpers\Url;
use tsingsun\daemon\coroutine\Task;
use yii\web\NotFoundHttpException;
use yii\web\UrlNormalizerRedirectException;

/**
 * 使用该类来替换Yii2的Web Application
 */
class Application extends \yii\web\Application
{
    const EVENT_AFTER_RUN = 'after_run';

    private $bootstrapComponents = [];

    /**
     * @var Task 调度任务
     */
    public $task;

    public function __construct(array $config = [])
    {
        parent::__construct($config);
    }

    /**
     * 默认的component组件复制时,不对event,behavior进行复制.需要取消该限制
     */
    public function __clone()
    {
    }

    /**
     * @return \Generator
     * @throws \Exception
     */
    public function run()
    {
        try {

            $this->beforeRun();

            $this->state = self::STATE_BEFORE_REQUEST;
            $this->trigger(self::EVENT_BEFORE_REQUEST);

            $this->state = self::STATE_HANDLING_REQUEST;
            $response = $this->handleRequest($this->getRequest());

            $this->state = self::STATE_AFTER_REQUEST;
            $this->trigger(self::EVENT_AFTER_REQUEST);

            $this->state = self::STATE_SENDING_RESPONSE;

            $response->send();

            $this->state = self::STATE_END;

            return $response->exitStatus;

        } catch (ExitException $e) {
            $this->end($e->statusCode, isset($response) ? $response : null);
            return $e->statusCode;
        } finally {
            $this->trigger(self::EVENT_AFTER_RUN);
        }
    }

    /**
     * 在run开始前执行配置文件启动组件
     */
    private function beforeRun()
    {
        $this->runComponentBootstrap();
    }

    /**
     * 重写引导组件方法
     * @throws InvalidConfigException
     */
    protected function bootstrap()
    {
        if ($this->extensions === null) {
            $file = Yii::getAlias('@vendor/yiisoft/extensions.php');
            $this->extensions = is_file($file) ? include($file) : [];
        }
        foreach ($this->extensions as $extension) {
            if (!empty($extension['alias'])) {
                foreach ($extension['alias'] as $name => $path) {
                    Yii::setAlias($name, $path);
                }
            }
            if (isset($extension['bootstrap'])) {
                $component = Yii::createObject($extension['bootstrap']);
                if ($component instanceof BootstrapInterface) {
                    $this->bootstrapComponents[] = $component;
                } else {
                    Yii::trace('Bootstrap with ' . get_class($component), __METHOD__);
                }
            }
        }
        foreach ($this->bootstrap as $class) {
            $component = null;
            if (is_string($class)) {
                if ($this->has($class)) {
                    $component = $this->get($class);
                } elseif ($this->hasModule($class)) {
                    $component = $this->getModule($class);
                } elseif (strpos($class, '\\') === false) {
                    throw new InvalidConfigException("Unknown bootstrapping component ID: $class");
                }
            }
            if (!isset($component)) {
                $component = Yii::createObject($class);
            }

            if ($component instanceof BootstrapInterface) {
                //记录组件,不重启
                $this->bootstrapComponents[] = $component;
            } else {
                Yii::trace('Bootstrap with ' . get_class($component), __METHOD__);
            }
        }
    }

    protected function runComponentBootstrap()
    {
        foreach ($this->bootstrapComponents as $component) {
            if ($component instanceof BootstrapInterface) {
                Yii::trace('Bootstrap with ' . get_class($component) . '::bootstrap()', __METHOD__);
                $component->bootstrap($this);
            }
        }
    }

    public function end($status = 0, $response = null)
    {
        if ($this->state === self::STATE_BEFORE_REQUEST || $this->state === self::STATE_HANDLING_REQUEST) {
            $this->state = self::STATE_AFTER_REQUEST;
            $this->trigger(self::EVENT_AFTER_REQUEST);
        }

        if ($this->state !== self::STATE_SENDING_RESPONSE && $this->state !== self::STATE_END) {
            $this->state = self::STATE_END;
            $response = $response ?: $this->getResponse();
            $response->send();
        }

        if (YII_ENV_TEST) {
            throw new ExitException($status);
        } else {
            return $status;
        }
    }
}