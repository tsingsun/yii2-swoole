## 已经改写的组件范围

> 默认启用了自动组件替换,如果你自己定义了组件,请在启动脚本中初始化Container后,关闭autoReplace

### DI Container
- yii\di\Container   
  yii的核心组件容器,重写以针对类读取控制,后期扩展热部署支持

### Web Application
- yii\web\Application  
  改变了bootstrap方式,初始化阶段不再运行BootStrapInterface->bootstrap方法.延迟在run方法中,防止一些初始化问题

### 请求与响应
- yii\web\Request 请求包装swoole的Request对象
- yii\web\Response 替换该组件以使用swoole的输出,可以启用以支持大文件

### 异常处理
- yii\web\ErrorHandle 代码中包含了exit语法,因此需要重写.

### 日志
- yii\log\Logger Exception不能被序列化,需要重写log的实现,在日志配置需要注意exportInterval,根据服务器环境设置
- 日志仍然保留为全局组件,由进程统一进行日志写入,以减少IO操作的次数,产生的问题就是在协程情况下,日志顺序不规律问题.
但在生产环境下,一般记录error信息.实际影响并不大

### 数据库
- mysql PDO驱动   
  改用Swoole内部的协程mysql客户端  
  存在问题:
     - PDO的setAttribute与getAttribute方法需要PDO进行连接,则swoole并不支持,属性支持如下:
       1.  PDO::ATTR_CASE: 返回PDO::CASE_NATURAL,不对列名进行转换
  
- redis
  改用Swoole内部的协程redis客户端
  
### Session

Yii的session组件相关的如下:

- yii\web\CacheSession 采用Cache组件维护session数据
- yii\web\DbSession 采用Db组件维护session数据
- yii\redis\Session 采用redis数据库维护session数据
- yii\mongo\Session 采用mongo数据库维护session数据 **(少用,暂时先不未实现)**

在协程环境下,session库并不适用,在框架中重新实现了session组件,并可适用于Yii原生组件.
> session的GC功能暂时未实现,因为GC现只作用于session及dbSession方式中,现更多采用CacheSession或者RedisSession

