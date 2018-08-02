# 连接池

针对协程的作业方式,默认对连接进行池化操作.现内置池包括mysql,redis

## ConnectionManager组件

ConnectionManager做为连接管理器,具备池功能

components组件引入ConnectionManager组件
```
'connectionManager'=>[
    'poolConfig'=>[
        'mysql'=>[
            //池容量
            'maxActive'=>10,
            //当链接数满时,重新获取的等待时间,秒为单位
            'waitTime'=> 0.01
        ],
    ],
],
```
* 属性
  - poolConfig Array池配置,Key指向具体配置.
    * key mapper,目前默认支持两种数据库池操作,池操作依赖于swoole本身的连接驱动实现
      - mysql: 对应mysql数据库
      - redis: 对应redis数据库
    * value array 配置如下
      - maxActive int 池容量
      - waitTime float 当链接数满时,重新获取的等待时间,秒为单位 可通过小数配置毫秒
      
### 数据库自定义

之前说明默认支持两种数据库,但Connection类可通过配置指向独立的poolConfig,这个在多连接个性配置比较有用
```
'class'=>'yii\db\Connection',
'poolKey' => 'mysqlConfig'
```