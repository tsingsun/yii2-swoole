关于 Yii2 Swoole
==================

[![Latest Stable Version](https://poser.pugx.org/tsingsun/yii2-swoole/v/stable.svg)](https://packagist.org/packages/tsingsun/yii2-swoole)
[![Build Status](https://travis-ci.org/tsingsun/yii2-swoole.png?branch=master)](https://travis-ci.org/tsingsun/yii2-swoole)
[![Total Downloads](https://poser.pugx.org/tsingsun/yii2-swoole/downloads.svg)](https://packagist.org/packages/tsingsun/yii2-swoole)


本项目是基于[php-swoole扩展](http://www.swoole.com)协程版本,使yii2项目运行在swoole上的一个方案.  
通过本项目扩展,可极大的提高原项目并发性.而且可以通过Yii2的全栈框架开发TCP,UDP,WebSocket等网络服务.  

## 安装
```php
    composer require tsingsun/yii2-swoole
```
## 特点

- 高度兼容Yii2项目,不需要改变项目代码.
- 一行代码切换协程和非协程环境的支持
- 编写启动脚本,即可享受swoole + 协程带来的高性能的并发服务.
- 运行内存表现稳定.
- 本地化mysql,redis连接池(协程环境下,非协程链接池意义不大).
- 实现了在swoole下的session功能.

> 如果你想简单的实现swoole,那可以直接采用非协程环境.在协程环境下时可能还需要做call_user_func的函数替换.

## 使用方法

- swoole启动文件    
启动文件为服务启动脚本,根据不同的服务类型定制,也可以根据业务来定制,具体请查看运行方式中的各服务器说明.  
协程与非协程的切换也在启动脚本中.
```php
use \tsingsun\swoole\server\Server;
//站点根目录,相当于nginx的root配置
defined('WEBROOT') or define('WEBROOT', __DIR__);
defined('YII_DEBUG') or define('YII_DEBUG', true);
defined('YII_ENV') or define('YII_ENV', 'dev');
//协程与非协程的切换
defined('COROUTINE_ENV') or define('COROUTINE_ENV', true);

require(__DIR__ . '/../../vendor/autoload.php');
$config = [
    'class'=>'tsingsun\swoole\server\HttpServer',
    //Swoole的配置,根据实际情况配置
    'setting' => [
        'daemonize'=>0,
        'max_coro_num'=>3000,
        'reactor_num'=>1,
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
        //需要使用Yii-Swoole项目的Yii文件,
        require(__DIR__ . '/vendor/yii2-swoole/src/Yii.php');
        //原项目的配置文件引入
        $config = yii\helpers\ArrayHelper::merge(
            require(__DIR__ . '/../config/main.php'),
            require(__DIR__ . '/../config/main-local.php')
        );        
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
//平滑重启,kill方式,需要root权限, 
php http_server.php reload
//关闭
php http_server.php stop
```
- 运行方式

  - [HttpServer](doc/httpServer.md):把swoole当成http服务器运行.   
  - [WebSocketServer](doc/WebSocketServer.md):实现可以通过controller方式进行websocket服务编写
  - TCP/UDP Server  --TODO

- 开发调试  
  - 仍然可用基于集成环境如XAMPP等进行调试
  - 基于swoole,只需要配置PHP环境,可用XDEBUG,如果是PHPSTORM,在Debug配置swoole运行脚本,点下Debug运行即可.
  - 在OnWorkStart断点时，请求会被阻塞
  - 启用task时,如果断点于task中,则调试请求会被阻塞
  - 如果出现页面信息输出至控制台,一般是被直接echo了,可跟踪各输出出口.

> 由于swoole2.0与xdebug产生冲突(主要是一些协程的客户端类上),导致无法在IDE中调试,比较好的实践应该是在普通PHP环境下开发好,在swoole环境再测试

## 受限

原Yii2的部分功能在swoole环境具有一定限制.具体请查阅[限制说明文档](doc/limit.md)  

请仔细理解[swoole的编程需知](https://wiki.swoole.com/wiki/page/851.html),请求无响应大部分来源于此.     
对于第三方包的call_user_func或call_user_func_array的处理请参考functionReplace.php的处理

## 改写的组件

为了适应swoole的内存常驻机制,对Yii的一部分组件的进行了改写,尽量的保持用户不产生额外的代码修改,无感迁移.  
一些组件的改写说明请参阅[组件改写说明](doc/component_changes.md)

### 联系我
QQ: 21997272  
如果你觉得对您有帮助,欢迎红包鼓励^_^
![支付宝](doc/images/a6x00263kcgmmg3ayg4qb8e.png)