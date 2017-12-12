关于 Yii2 Swoole
==================

yii2 swoole是基于[swoole扩展](www.swoole.com),使yii项目运行在swoole上的一个方案,除了提高Yii的并发性能外,为YIIer做种服务提供便利.
简单的说我们可以做什么呢,如WebSocket服务器,定时任务服务器,TCP服务器,大部分服务端应用都可以做,已经不再让PHP仅做前端.

本项目尽量不改变Yii项目源码来实现Yii2在swoole的运行.

在编写本项目时,同时参考了swoole-yii2这个项目,给了我集成swoole的好思路,感谢!

## 安装方法

最近的更新移除了runkit,但做为热更新的一种方案，先保留对runkit的说明。

* Pear环境,项目依赖swoole与runkit扩展,通过pear包管理工具可以方便的进行安装

        wget http://pear.php.net/go-pear.phar
        php go-pear.phar
    
* swoole

    采用默认安装,也可以结合pecl.php.net上的包名

        pecl install swoole
        pecl install swoole-2.0.6
       
* runkit:官方版本不支持php7,此处用的是非官方版本,但抛弃了runkit_import这个方法,导致无法重新加载文件,热部署功能缺失,
希望待后续版本能提供,没有启用该扩展还会引起对自定义程度较高的Yii,如采用的自己扩展Yii框架中的组件(像ErrorHandle),无法
AOP进swoole的处理方式,也会造成运行中的问题.

        git clone https://github.com/runkit7/runkit7.git
        cd runkit7
        phpize
        ./configure
        make
        make install
      
* IDE Helper

    swoole: [swoole-ide-helper](https://github.com/eaglewu/swoole-ide-helper)   
    runkit: 获取的[runkit](https://github.com/runkit7/runkit7)根目录中的runkit-api.php文件


## 受限

* swoole的reload机制
在swoole的reload操作只能载入Worker进程启动后加载的PHP文件,PHPer习惯的热部署变得有一些限制.
如果采用的是Yii的cli方式进行启动,那在启动前Yii Console相关的库文件将被加载,如果Yii涉及的库更新的话,将不得不将进程kill后再启动,
这显示对维护人员来说是个灾难.谁也不能保证在kill时,对业务产生怎样的影响.因此在启动前最低限度或者不加载Yii框架是基本的要求.如果采用了Yii的
控制台命令方式启动,那将加载Yii,如果采用php命令方式启动,将不会加载Yii.
* 由于swoole的方式是采用常驻内存方式,或者可以理解为Yii中的组件是单例,比如Response,这样的结果导致上下文输出混乱.上一次输出的结果并没有清除,
影响下一次输出.因此某些组件需要重新改写
* echo输出受限于stdout,原来想通过重新实现,但发现该语法PHP保护的语言特性,无法重写,echo一直输出至黑屏.只能重写Response.又后找到另一种方式可以控制echo的输出
```php
    ob_start()
    ob_implicit_flush(false);
    $data = ob_get_clean();
    
```
但该方式还有缺陷,无法平滑支持大文件的输出,因此如果是大文件输出的情况下,需要采用tsingsun\swoole\web\Response,获取方式为;
```php
    $response = Yii::$app->getResponse();//不要采用new 方式
    $response->sendFile($file);
```
* 异常捕获
    * swoole不支持set_exception_handler函数,需要在回调函数顶层进行捕获.
* 代码控制:sleep/exit/die是需要严格控制的语法,也导致了ErrorHandle需要重写.
* Component组件的clone方法不复制event与behavior,因此目前只在Application和Response重写了该方法,其他组件暂时不需要

## 执行流程

1.  服务端代码不依赖YII,这样保证在swoole启动动,进程中的PHP文件不包含有Yii内容.
2.  在worker进程中创建Application对象,Application对象由各服务器决定采用哪种.
3.  Server接收请求时,复制worker中的Application对象及其组件.
4.  执行Yii run

## 改写的组件

为了适应swoole的内存处理机制,不得不对Yii组件的进行改写,改写的原则是最小化,比如异常处理,可以改写ErrorHandle进行处理,但发现改写Response
也可以达到目标,就只保留必须改写的Response.

* yii\di\Container yii的核心组件容器,重写以针对类读取控制,后期扩展热部署支持
* yii\web\Application 改变了bootstrap方式,初始化阶段不再运行BootStrapInterface->bootstrap方法.延迟在run方法中,防止一些初始化问题
* yii\web\Response 替换该组件以使用swoole的输出,可以启用以支持大文件
* yii\web\ErrorHandle 代码中包含了exit语法,因此需要重写.
* yii\web\Session 取消初步化注册php关闭,session需要显示在配置文件中声明,才可识别.
* yii\log\Logger Exception不能被序列化,需要重写log的实现,在日志配置需要注意exportInterval,根据服务器环境设置

> 默认启用了自动组件替换,如果你自己定义了组件,请在启动脚本中初始化Container后,关闭autoReplace

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
2.  启动文件  
启动文件为服务启动脚本,根据不同的服务类型定制,也可以根据业务来定制,具体请查看运行方式中的各服务器说明.

3.  cli控制命令 

Usage: php [startScript] [command]

```php
//启动
php http_server.php start
//重启 
php http_server.php reload
//关闭
php http_server.php stop
```
4.  运行方式

* [HttpServer](doc/swooleHttpServer.md):把swoole当成http服务器运行.
    
* WebSocketServer  
实现可以通过controller方式进行websocket服务编写,待补充文档
* TCP/UDP Server  --TODO

5.  开发调试

* 仍然可用基于集成环境如XAMPP等进行调试
* 基于swoole,只需要配置PHP环境,可用XDEBUG,如果是PHPSTORM,在Debug配置swoole运行脚本,点下Debug运行即可.
* 在OnWorkStart断点时，请求会被阻塞
* 启用task时,如果断点于task中,则调试请求会被阻塞
* 如果出现页面信息输出至控制台,一般是被直接echo了,可跟踪各输出出口.
