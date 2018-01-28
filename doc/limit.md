### swoole reload
在swoole的reload操作只能载入Worker进程启动后加载的PHP文件,PHPer习惯的热部署变得有一些限制.
如果采用的是Yii的cli方式进行启动,那在启动前Yii Console相关的库文件将被加载,如果Yii涉及的库更新的话,将不得不将进程kill后再启动,
这显示对维护人员来说是个灾难.谁也不能保证在kill时,对业务产生怎样的影响.因此在启动前最低限度或者不加载Yii框架是基本的要求.如果采用了Yii的
控制台命令方式启动,那将加载Yii,如果采用php命令方式启动,将不会加载Yii.

### Response
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
### Exception
* 异常捕获
  - swoole不支持set_exception_handler函数,需要在回调函数顶层进行捕获.
* 代码控制:sleep/exit/die是需要严格控制的语法,也导致了ErrorHandle需要重写.
