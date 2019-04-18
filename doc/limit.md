### swoole reload
在swoole的reload操作只能载入Worker进程启动后加载的PHP文件,PHPer习惯的热部署变得有一些限制.
如果采用的是Yii的cli方式进行启动,那在启动前Yii Console相关的库文件将被加载,如果Yii涉及的库更新的话,将不得不将进程kill后再启动,
这显示对维护人员来说是个灾难.谁也不能保证在kill时,对业务产生怎样的影响.因此在启动前最低限度或者不加载Yii框架是基本的要求.如果采用了Yii的
控制台命令方式启动,那将加载Yii,如果采用php命令方式启动,将不会加载Yii.

### Response
* 直接的echo输出至黑屏,输出控制到客户端时请采用
```php
    ob_start()
    ob_implicit_flush(false);
    $data = ob_get_clean();
    
```

### Exception
* 异常捕获
  - swoole不支持set_exception_handler函数,需要在回调函数顶层进行捕获.
* 代码控制:sleep/exit/die是需要严格控制的语法.

### DEBUG

在协程环境下,当触发协程时,会与xdebug产生冲突,导致无法断点只能用log查问题,希望swoole能在调试便利性上下功能.

### bootstrap

在swoole中，尽量不要使用yii的boostrap配置，目前仅对ContentNegotiator做了测试。不保证其他引导组件有效.

### Mysql

swoole协程mysql可能还存在问题.对于connected的状态维持不准确.使得程序中优雅判断连接状态存在部分问题,采用粗暴的方式进行一次重试连接.



