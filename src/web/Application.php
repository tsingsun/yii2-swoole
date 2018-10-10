<?php

namespace tsingsun\swoole\web;

use tsingsun\swoole\di\ApplicationDecorator;
use Yii;
use yii\base\BootstrapInterface;
use yii\base\Component;
use yii\base\ExitException;
use yii\base\InvalidConfigException;

/**
 * 使用该类来替换Yii2的Web Application
 */
class Application extends \yii\web\Application
{
    /**
     * @var bool 是否已经被引导,对于swoole模式下,只需要引导一次.
     */
    static $_hasBootstrap = false;

    const EVENT_AFTER_RUN = 'afterRun';

    private static $bootstrapComponents = [];

    public function __construct(array $config = [])
    {
        if (COROUTINE_ENV) {
            Yii::$app = new ApplicationDecorator();
            Yii::$context->setApplication($this);
        } else {
            Yii::$app = $this;
        }
        static::setInstance($this);
        $this->state = self::STATE_BEGIN;

        $this->preInit($config);
        if (!self::$_hasBootstrap) {
            $this->registerErrorHandler($config);
        }
        Component::__construct($config);
    }

    public function init()
    {
        $this->state = self::STATE_INIT;
        // 改在初始后,通过beforeRun引导
//        parent::bootstrap();
    }

    /**
     * 在run开始前执行配置文件启动组件
     */
    public function beforeRun()
    {
        $this->bootstrap();
    }

    /**
     * 重写引导组件方法
     */
    protected function bootstrap()
    {
        if (!self::$_hasBootstrap) {
            $this->splitBootstrap();
            self::$_hasBootstrap = true;
        }
        $this->runComponentBootstrap();
    }

    protected function splitBootstrap()
    {
        if (!self::$_hasBootstrap) {
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
                        self::$bootstrapComponents[] = $component;
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
                    self::$bootstrapComponents[] = $component;
                } else {
                    Yii::trace('Bootstrap with ' . get_class($component), __METHOD__);
                }
            }
            self::$_hasBootstrap = true;
        }
    }

    protected function runComponentBootstrap()
    {
        foreach (self::$bootstrapComponents as $component) {
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

    public function getConnectionManager()
    {
        return $this->get('connectionManager');
    }

    /**
     * @inheritdoc
     */
    public function coreComponents()
    {
        return array_merge(parent::coreComponents(), [
            'connectionManager' => ['class' => 'tsingsun\swoole\pool\ConnectionManager'],
        ]);
    }
}