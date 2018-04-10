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
        'daemonize'=>1,
        'reactor_num'=>1,
        'worker_num'=>1,
        'pid_file' => __DIR__ . '/../runtime/http.pid',
        'log_file' => __DIR__.'/../runtime/logs/swoole.log',
        'debug_mode'=> 1,
        'user'=>'tsingsun',
        'group'=>'staff',
    ],
];
```
2.  启动文件:
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
        //与Web相关时，需要在此先设置资源有关的别名
        Yii::setAlias('@webroot', WEBROOT);
        Yii::setAlias('@web', '/');                
    };
    $server->bootstrap = $starter;
    $server->start();
});
```
3. nginx配置例子：
```
server{
        listen       80;
        root         /home/www/web/;
        index        index.php;
        
        location / {
                proxy_http_version 1.1;
                proxy_set_header Host $http_host;
                proxy_set_header Connection "keep-alive";
                proxy_set_header X-Real-IP $remote_addr;
                if (!-e $request_filename) {
                        #rewrite ^(.*)$ /index.php/$1 last;
                        proxy_pass http://127.0.0.1:9501;
                }
        }
        location ~ /.*\.(gif|jpg|jpeg|png|ico)$ {
                expires 1d;
        }

        location ~ /.*\.(js|css)$ {
                expires 10m;
        }

        error_page  404              /404.html;

        error_page   500 502 503 504  /50x.html;
        location = /50x.html {
                root   html;
        }

        location ~ \.php$ {
                try_files $uri =404;
                proxy_http_version 1.1;
                proxy_set_header Host $http_host;
                proxy_set_header Connection "keep-alive";
                proxy_set_header X-Real-IP $remote_addr;
                proxy_pass http://127.0.0.1:9501;
        }
}
```