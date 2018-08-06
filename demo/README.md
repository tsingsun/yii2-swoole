DEMO
========
* swoole_http_server.php 启动文件示例,你可将Yii的项目放入做测试,请注意本地配置文件*-local.php是否生成.

测试的目录结构为
```
-- demo
  -- yii2-app-basic   yii2模板项目
  -- swoole_http_server.php app-basic的swoole启动文件
-- vendor
```
### 注意事项
* 出于权限安全,启动脚本不建议放置在web目录下.
* swoole对session有变更，所以请先调整session配置
```
'components'=>
    'session' => [
        //默认cache
        'class' => 'yii\web\CacheSession',
    ],
```
