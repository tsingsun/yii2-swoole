Swoole Http Server
=================

已经可以做为高性能的服务器运行,但由于swoole_http_server对Http协议的支持并不完整，建议仅作为应用服务器。并且在前端增加Nginx作为代理

为了开发方便,httpserver同时支持了静态文件的请求.

## 使用方法

1.  将swoole配置文件放在配置文件夹中
```php
return [
    'class'=>'tsingsun\swoole\server\HttpServer',
    'setting' => [
    //            'daemonize'=>1,
        'reactor_num'=>1,
        'worker_num'=>1,
        'pid_file' => __DIR__ . '/testHttp.pid',
        'log_file' => __DIR__.'/../runtime/logs/swoole.log',
        'debug_mode'=> 1,
        'user'=>'tsingsun',
        group'=>'staff',
    ],
];
```
2.  启动文件,一般放置在web目录下.如命名为http_server.php,如:
```php
defined('WEBROOT') or define('WEBROOT', __DIR__);

require(__DIR__ . '/../../vendor/autoload.php');
$config = require(__DIR__ . '/../config/swoole.php');

\tsingsun\swoole\server\Server::run($config,function ($nodeConfig){
    $server = \tsingsun\swoole\server\Server::autoCreate($nodeConfig);
    $starter = new \tsingsun\swoole\bootstrap\YiiWeb($server);
    //初始化函数独立,为了在启动时,不会加载Yii相关的文件,在库更新时采用reload平滑启动服务器
    $starter->init = function ($bootstrap) {
        require(__DIR__ . '/../../vendor/yiisoft/yii2/Yii.php');

        $config = yii\helpers\ArrayHelper::merge(
            require(__DIR__ . '/../config/main.php'),
            require(__DIR__ . '/../config/main-local.php')
        );
        //需要在此先设置资源有关的别名
        Yii::setAlias('@webroot', WEBROOT);
        Yii::setAlias('@web', '/');
        //可以自定义实现
        Yii::$container = new \tsingsun\swoole\di\Container();
        $bootstrap->app = new \tsingsun\swoole\web\Application($config);        
    };
    $server->bootstrap = $starter;
    $server->start();
});
```