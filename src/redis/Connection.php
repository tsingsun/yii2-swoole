<?php
/**
 * Created by PhpStorm.
 * User: tsingsun
 * Date: 2017/12/15
 * Time: 上午10:02
 */

namespace tsingsun\swoole\redis;

use Swoole\Coroutine\Redis;
use tsingsun\swoole\pool\ConnectionManager;
use tsingsun\swoole\pool\DbPool;
use yii\base\Exception;

class Connection extends \yii\redis\Connection
{
    /**
     * @var string redis pool key
     */
    private $poolKey;
    /**
     * @var Redis
     */
    private $_socket;
    /**
     * @var DbPool
     */
    private $pool;
    /**
     * Returns a value indicating whether the DB connection is established.
     * @return boolean whether the DB connection is established
     */
    public function getIsActive()
    {
        return $this->_socket !== null;
    }

    /**
     * Establishes a DB connection.
     * It does nothing if a DB connection has already been established.
     * @throws Exception if connection fails
     */
    public function open()
    {
        if ($this->_socket !== null) {
            return;
        }
        $connection = ($this->unixSocket ?: $this->hostname . ':' . $this->port) . ', database=' . $this->database;
        \Yii::trace('Opening redis DB connection: ' . $connection, __METHOD__);
        $this->_socket = $this->getConnectionFormPool();
        $this->initConnection();
    }

    /**
     * Executes a redis command.
     * For a list of available commands and their parameters see http://redis.io/commands.
     *
     * @param string $name the name of the command
     * @param array $params list of parameters for the command
     * @return array|bool|null|string Dependent on the executed command this method
     * will return different data types:
     *
     * - `true` for commands that return "status reply" with the message `'OK'` or `'PONG'`.
     * - `string` for commands that return "status reply" that does not have the message `OK` (since version 2.0.1).
     * - `string` for commands that return "integer reply"
     *   as the value is in the range of a signed 64 bit integer.
     * - `string` or `null` for commands that return "bulk reply".
     * - `array` for commands that return "Multi-bulk replies".
     *
     * See [redis protocol description](http://redis.io/topics/protocol)
     * for details on the mentioned reply types.
     * @trows Exception for commands that return [error reply](http://redis.io/topics/protocol#error-reply).
     */
    public function executeCommand($name, $params = [], $reconnect = 0)
    {
        $this->open();
        // backup the params for try again when execute fail
        try {
            \Yii::trace("Executing Redis Command: {$name}", __METHOD__);
            $ret = $this->_socket->$name(...$params);
            if ($this->_socket->errCode) {
                throw new Exception("Redis error: {$this->_socket->errMsg} \nRedis command was: " . $name);
            }
            return $ret;
        } finally {
            $this->releaseConnect();
        }
    }

    /**
     * Closes the currently active DB connection.
     * It does nothing if the connection is already closed.
     */
    public function releaseConnect()
    {
        /** @var ConnectionManager $cm */
        $cm = \Yii::$app->getConnectionManager();
        $cm->releaseConnection($this->poolKey, $this->_socket);
        $this->_socket = null;
    }

    /**
     * @return Redis
     */
    protected function getConnectionFormPool()
    {
        /** @var ConnectionManager $cm */
        $cm = \Yii::$app->getConnectionManager();
        $poolKey = $this->buildPoolKey();
        $this->pool = $cm->getPool($poolKey);
        if (!$this->pool) {
            $pc = $cm->poolConfig['redis'] ?? [];
            $dbPool = new DbPool($pc);
            $dbPool->createHandle = function () {
                $client = new Redis();
                return $client;
            };
            $config = [
                'hostname'=>$this->hostname,
                'port'=>$this->port,
                'database'=> $this->database,
                'connectionTimeout'=> $this->connectionTimeout ? $this->connectionTimeout : ini_get('default_socket_timeout'),
                'password'=>$this->password,
            ];
            $dbPool->reConnectHandle = function (Redis $client)use($config){
                $connection = $config['hostname'] . ':' . $config['port'] . ', database=' . $config['database'];
                $isConnected = $client->connect(
                    $config['hostname'],
                    $config['port'],
                    $config['connectionTimeout']
                );
                if (!$isConnected) {
                    \Yii::error("Failed to open redis DB connection ($connection): {$client->errCode} - {$client->errMsg}", __CLASS__);
                    $message = YII_DEBUG ? "Failed to open redis DB connection ($connection): {$client->errCode} - {$client->errMsg}" : 'Failed to open DB connection.';
                    throw new \Exception($message, (int)$client->errCode);
                }
                if ($config['password'] !== null){
                    \Yii::trace("Executing Redis Command: AUTH", __METHOD__);
                    if($client->auth($config['password']) === false){
                        throw new \Exception('incorrect password for redis', $client->errCode);
                    }
                }
                if ($config['database'] !== null) {
                    \Yii::trace("Executing Redis Command: SELECT {$config['database']}", __METHOD__);
                    if($client->select($config['database']) === false){
                        throw new \Exception("incorrect database index:{$config['database']} in redis", $client->errCode);
                    }
                }
            };
            $this->pool = $dbPool;
            $cm->addPool($poolKey, $dbPool);
        }

        return $this->pool->getConnect();
    }

    protected function buildPoolKey()
    {
        if(!$this->poolKey){
            $connection = $this->hostname . ':' . $this->port . ', database=' . $this->database;
            $this->poolKey = md5($connection);
        }
    }
}