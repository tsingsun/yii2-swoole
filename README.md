关于 Yii2 Swoole
==================

yii2 swoole是基于[swoole扩展](www.swoole.com),使yii项目运行在swoole上的一个方案,除了提高Yii的并发性能外,为YIIer做种服务提供便利.
简单的说我们可以做什么呢,如WebSocket服务器,定时任务服务器,TCP服务器,大部分服务端应用都可以做,已经不再让PHP仅做前端.

目前版本可在协程与非协程环境中运行,但建议开启swoole协程支持以达到最高的性能提升.

## 安装
```php
    composer require tsingsun\yii2-swoole
```
## 特点

- 在不改变原项目代码的基础上,引用本包,即可以享受swoole + 协程带来的高性能
- 本地化mysql链接池 

## 受限

部分Yii的功能在swoole环境,在代码开发时产生限制.具体请查阅[限制说明文档](doc/limit.md)  
在协程环境下与xdebug产生冲突,导致无法断点只能用log查问题,希望swoole能在调试便利性上下功能.

## 执行流程

1.  服务启动: 服务端代码不依赖YII,这样保证在swoole启动动,进程中的PHP文件不包含有Yii内容.
2.  workder进程创建: 在worker进程中初始化DI容器与上下文环境,让container持久化
3.  onRequest中将创建新的Application对象,通过装饰Application::$app对象来支持协程.
4.  新创建的Application对象将变成上下文容器,通过执行run方法响应客户端.
5.  销毁Application对象,Application对象生命周期结束.

## 改写的组件

为了适应swoole的内存常驻机制,对Yii的一部分组件的进行了改写,尽量的保持用户不产生额外的代码修改,无感迁移.  
在协程环境下,对各类组件需要区分哪些是上下文组件,哪些可以做为全局组件.

一些组件的改写说明请参阅[组件改写说明](doc/component_changes.md)

## 使用方法

- swoole启动文件    
启动文件为服务启动脚本,根据不同的服务类型定制,也可以根据业务来定制,具体请查看运行方式中的各服务器说明.
```php
use \tsingsun\swoole\server\Server;

defined('WEBROOT') or define('WEBROOT', __DIR__);
defined('YII_DEBUG') or define('YII_DEBUG', true);
defined('YII_ENV') or define('YII_ENV', 'dev');

require(__DIR__ . '/../../vendor/autoload.php');
$config = [
    'class'=>'tsingsun\swoole\server\HttpServer',
    'setting' => [
        'daemonize'=>0,
        'max_coro_num'=>3000,
//        'reactor_num'=>1,
        'worker_num'=>1,
        'task_worker_num'=>1,
        'pid_file' => __DIR__ . '/../runtime/testHttp.pid',
        'log_file' => __DIR__.'/../runtime/logs/swoole.log',
        'debug_mode'=> 1,
        'user'=>'tsingsun',
        'group'=>'staff',
    ],
];

Server::run($config,function (Server $server){
    $starter = new \tsingsun\swoole\bootstrap\WebApp($server);
    //初始化函数独立,为了在启动时,不会加载Yii相关的文件,在库更新时采用reload平滑启动服务器
    $starter->init = function (\tsingsun\swoole\bootstrap\BaseBootstrap $bootstrap) {
        require(__DIR__ . '/../../src/Yii.php');

        $config = yii\helpers\ArrayHelper::merge(
            require(__DIR__ . '/../config/main.php'),
            require(__DIR__ . '/../config/main-local.php')
        );
        Yii::setAlias('@yiiunit/extension/swoole', __DIR__ . '/../');
        $bootstrap->appConfig = $config;
    };
    $server->bootstrap = $starter;
    $server->start();
});
```

- cli控制命令  
Usage: php [startScript] [command]

```php
//启动
php http_server.php start
//重启 
php http_server.php reload
//关闭
php http_server.php stop
```
- 运行方式

* [HttpServer](doc/swooleHttpServer.md):把swoole当成http服务器运行.
    
* WebSocketServer  
实现可以通过controller方式进行websocket服务编写,待补充文档
* TCP/UDP Server  --TODO

- 开发调试  
  - 仍然可用基于集成环境如XAMPP等进行调试
  - 基于swoole,只需要配置PHP环境,可用XDEBUG,如果是PHPSTORM,在Debug配置swoole运行脚本,点下Debug运行即可.
  - 在OnWorkStart断点时，请求会被阻塞
  - 启用task时,如果断点于task中,则调试请求会被阻塞
  - 如果出现页面信息输出至控制台,一般是被直接echo了,可跟踪各输出出口.

> 由于swoole2.0与xdebug产生冲突(主要是一些协程的客户端类上),导致无法在IDE中调试,比较好的实践应该是在普通PHP环境下开发好,在swoole环境再测试

### 联系我
QQ: 21997272