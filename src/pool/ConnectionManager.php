<?php
/**
 * Created by PhpStorm.
 * User: tsingsun
 * Date: 2018/1/18
 * Time: 上午11:51
 */

namespace tsingsun\swoole\pool;

use yii\base\Component;
use yii\base\InvalidParamException;

/**
 * 连接池管理门面
 * @package tsingsun\swoole\pool
 */
class ConnectionManager extends Component
{
    public $poolConfig = [];
    /**
     * 连接池
     * @var ConnectionPool[]
     */
    protected static $poolMap = [];

    /**
     * @param $connectionKey
     * @return null|object
     */
    public function get($connectionKey)
    {
        if(isset(self::$poolMap[$connectionKey])){
            return $this->getFromPool($connectionKey);
        }
    }

    public function getFromPool($connectionKey)
    {
        $pool = self::$poolMap[$connectionKey];

        $conn = $pool->getConnect();
        return $conn;
    }

    public function getPool($poolKey)
    {
        if(!$this->hasPool($poolKey)){
            return null;
        }
        return self::$poolMap[$poolKey];
    }

    public function hasPool($poolKey)
    {
        return isset(self::$poolMap[$poolKey]);
    }

    public function addPool($poolKey,$pool)
    {
        if ($pool instanceof ConnectionPool) {
            self::$poolMap[$poolKey] = $pool;
        } else {
            throw new InvalidParamException("invalid pool type, poolKey=$poolKey");
        }
    }

    public function releaseConnection($connectionKey,$connection)
    {
        if(isset(self::$poolMap[$connectionKey])){
            $pool = self::$poolMap[$connectionKey];
            return $pool->release($connection);
        }
    }
}