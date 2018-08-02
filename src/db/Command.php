<?php
/**
 * Created by PhpStorm.
 * User: tsingsun
 * Date: 2017/12/15
 * Time: 上午9:25
 */

namespace tsingsun\swoole\db;

use Yii;
use yii\base\Exception;
use yii\db\DataReader;

class Command extends \yii\db\Command
{
    /**
     * @var array pending parameters to be bound to the current PDO statement.
     */
    protected $_pendingParams = [];
    /**
     * @var string the SQL statement that this command represents
     */
    private $_sql;
    /**
     * @var string name of the table, which schema, should be refreshed after command execution.
     */
    private $_refreshTableName;
    protected $errorCount = 0;
    public $maxErrorTimes = 2;
    /**
     * Returns the SQL statement for this command.
     * @return string the SQL statement to be executed
     */
    public function getSql()
    {
        return $this->_sql;
    }
    /**
     * Specifies the SQL statement to be executed.
     * The previous SQL execution (if any) will be cancelled, and [[params]] will be cleared as well.
     * @param string $sql the SQL statement to be set.
     * @return $this this command instance
     */
    public function setSql($sql)
    {
        if ($sql !== $this->_sql) {
            $this->cancel();
            $this->_sql              = $this->db->quoteSql($sql);
            $this->_pendingParams    = [];
            $this->params            = [];
            $this->_refreshTableName = null;
        }
        return $this;
    }
    /**
     * Specifies the SQL statement to be executed. The SQL statement will not be modified in any way.
     * The previous SQL (if any) will be discarded, and [[params]] will be cleared as well. See [[reset()]]
     * for details.
     *
     * @param string $sql the SQL statement to be set.
     * @return $this this command instance
     * @since 2.0.13
     * @see reset()
     * @see cancel()
     */
    public function setRawSql($sql)
    {
        if ($sql !== $this->_sql) {
            $this->cancel();
            $this->reset();
            $this->_sql = $sql;
        }
        return $this;
    }
    /**
     * Returns the raw SQL by inserting parameter values into the corresponding placeholders in [[sql]].
     * Note that the return value of this method should mainly be used for logging purpose.
     * It is likely that this method returns an invalid SQL due to improper replacement of parameter placeholders.
     * @return string the raw SQL with parameter values inserted into the corresponding placeholders in [[sql]].
     */
    public function getRawSql()
    {
        if (empty($this->params)) {
            return $this->_sql;
        }
        $params = [];
        foreach ($this->params as $name => $value) {
            if (is_string($name) && strncmp(':', $name, 1)) {
                $name = ':' . $name;
            }
            if (is_string($value)) {
                $params[$name] = $this->db->quoteValue($value);
            } elseif (is_bool($value)) {
                $params[$name] = ($value ? 'TRUE' : 'FALSE');
            } elseif ($value === null) {
                $params[$name] = 'NULL';
            } elseif (!is_object($value) && !is_resource($value)) {
                $params[$name] = $value;
            }
        }
        if (!isset($params[1])) {
            return strtr($this->_sql, $params);
        }
        $sql = '';
        foreach (explode('?', $this->_sql) as $i => $part) {
            $sql .= (isset($params[$i]) ? $params[$i] : '') . $part;
        }
        return $sql;
    }
    /**
     * Binds pending parameters that were registered via [[bindValue()]] and [[bindValues()]].
     * Note that this method requires an active [[pdoStatement]].
     */
    protected function bindPendingParams()
    {
        foreach ($this->_pendingParams as $name => $value) {
            $this->pdoStatement->bindValue($name, $value[0], $value[1]);
        }
        $this->_pendingParams = [];
    }
    /**
     * Binds a value to a parameter.
     * @param string|integer $name Parameter identifier. For a prepared statement
     * using named placeholders, this will be a parameter name of
     * the form `:name`. For a prepared statement using question mark
     * placeholders, this will be the 1-indexed position of the parameter.
     * @param mixed $value The value to bind to the parameter
     * @param integer $dataType SQL data type of the parameter. If null, the type is determined by the PHP type of the value.
     * @return $this the current command being executed
     * @see http://www.php.net/manual/en/function.PDOStatement-bindValue.php
     */
    public function bindValue($name, $value, $dataType = null)
    {
        if ($dataType === null) {
            $dataType = $this->db->getSchema()->getPdoType($value);
        }
        $this->_pendingParams[$name] = [$value, $dataType];
        $this->params[$name]         = $value;
        return $this;
    }
    /**
     * Binds a list of values to the corresponding parameters.
     * This is similar to [[bindValue()]] except that it binds multiple values at a time.
     * Note that the SQL data type of each value is determined by its PHP type.
     * @param array $values the values to be bound. This must be given in terms of an associative
     * array with array keys being the parameter names, and array values the corresponding parameter values,
     * e.g. `[':name' => 'John', ':age' => 25]`. By default, the PDO type of each value is determined
     * by its PHP type. You may explicitly specify the PDO type by using an array: `[value, type]`,
     * e.g. `[':name' => 'John', ':profile' => [$profile, \PDO::PARAM_LOB]]`.
     * @return $this the current command being executed
     */
    public function bindValues($values)
    {
        if (empty($values)) {
            return $this;
        }
        $schema = $this->db->getSchema();
        foreach ($values as $name => $value) {
            if (is_array($value)) {
                $this->_pendingParams[$name] = $value;
                $this->params[$name]         = $value[0];
            } else {
                $type                        = $schema->getPdoType($value);
                $this->_pendingParams[$name] = [$value, $type];
                $this->params[$name]         = $value;
            }
        }
        return $this;
    }
    /**
     * 检查指定的异常是否为可以重连的错误类型
     *
     * @param \Exception $exception
     * @return bool
     */
    public function isConnectionError($exception)
    {
        if ($exception instanceof \PDOException) {
            $errorInfo = $this->pdoStatement->errorInfo();
            if ($errorInfo[1] == 70100 || $errorInfo[1] == 2006 || $errorInfo[1] == 2013) {
                return true;
            }
        }
        $message = $exception->getMessage();
        if (strpos($message, 'Error while sending QUERY packet.') !== false) {
            return true;
        }
        // Error reading result set's header
        if (strpos($message, 'Error reading result set\'s header') !== false) {
            return true;
        }
        // MySQL server has gone away
        if (strpos($message, 'MySQL server has gone away') !== false) {
            return true;
        }
        return false;
    }

    public function prepare($forRead = null)
    {
        if ($this->pdoStatement) {
            $this->bindPendingParams();
            return false;
        }
        $sql = $this->getSql();
        if ($this->db->getTransaction()) {
            // master is in a transaction. use the same connection.
            $forRead = false;
        }
        if ($forRead || $forRead === null && $this->db->getSchema()->isReadQuery($sql)) {
            $pdo = $this->db->getSlavePdo();
        } else {
            $pdo = $this->db->getMasterPdo();
        }
        try {
            $this->pdoStatement = $pdo->prepare($sql);
            $this->bindPendingParams();
            return true;
        } catch (\Throwable $e) {
            if ($this->isConnectionError($e) && $this->errorCount < $this->maxErrorTimes) {
                $this->cancel();
                $this->db->close();
                $this->db->open();
                $this->errorCount++;
                return $this->prepare($forRead);
            }
            $message   = $e->getMessage() . "\nFailed to prepare SQL: $sql";
            $errorInfo = $e instanceof \PDOException ? $e->errorInfo : null;
            throw new Exception($message, $errorInfo, (int) $e->getCode(), $e);
        }
    }

    public function queryInternal($method, $fetchMode = null, $reconnect = 0)
    {
        $rawSql       = $this->getRawSql();
        $oldMethod    = $method;
        $oldFetchMode = $fetchMode;
        Yii::info($rawSql, 'yii\db\Command::query');
        if ($method !== '') {
            $info = $this->db->getQueryCacheInfo($this->queryCacheDuration, $this->queryCacheDependency);
            if (is_array($info)) {
                /* @var $cache \yii\caching\Cache */
                $cache    = $info[0];
                $cacheKey = [
                    __CLASS__,
                    $method,
                    $fetchMode,
                    $this->db->dsn,
                    $this->db->username,
                    $rawSql,
                ];
                $result = $cache->get($cacheKey);
                if (is_array($result) && isset($result[0])) {
                    Yii::trace('Query result served from cache', 'yii\db\Command::query');
                    return $result[0];
                }
            }
        }
        $bakPendingParams = $this->_pendingParams;
        $this->prepare(true);
        $token = $rawSql;
        try {
            YII_DEBUG && Yii::beginProfile($token, 'yii\db\Command::query');
            // @link https://bugs.php.net/bug.php?id=74401
            $this->pdoStatement->execute();
            if ($method === '') {
                $result = new DataReader($this);
            } else {
                if ($fetchMode === null) {
                    $fetchMode = $this->fetchMode;
                }
                $result = $this->pdoStatement->$method($fetchMode);
                $this->pdoStatement->closeCursor();
            }
            YII_DEBUG && Yii::endProfile($token, 'yii\db\Command::query');
        } catch (\Throwable $e) {
            if ($this->isConnectionError($e) && $this->errorCount < $this->maxErrorTimes) {
                $this->cancel();
                $this->db->close();
                $this->db->open();
                $this->_pendingParams = $bakPendingParams;
                $this->errorCount++;
                return $this->queryInternal($oldMethod, $oldFetchMode);
            }
            YII_DEBUG && Yii::endProfile($token, 'yii\db\Command::query');
            throw $this->db->getSchema()->convertException($e, $rawSql);
        }
        if (isset($cache, $cacheKey, $info)) {
            $cache->set($cacheKey, [$result], $info[1], $info[2]);
            Yii::trace('Saved query result in cache', 'yii\db\Command::query');
        }
        return $result;
    }

    public function execute()
    {
        $sql = $this->getSql();
        $rawSql = $this->getRawSql();
        Yii::info($rawSql, __METHOD__);
        if ($sql == '') {
            return 0;
        }
        $bakPendingParams = $this->_pendingParams;
        $this->prepare(false);
        $token = $rawSql;
        try {
            YII_DEBUG && Yii::beginProfile($token, __METHOD__);
            // @link https://bugs.php.net/bug.php?id=74401
            $this->pdoStatement->execute();
            $n = $this->pdoStatement->rowCount();
            YII_DEBUG && Yii::endProfile($token, __METHOD__);
            $this->refreshTableSchema();
            return $n;
        } catch (\Throwable $e) {
            if ($this->isConnectionError($e) && $this->errorCount < $this->maxErrorTimes) {
                $this->cancel();
                $this->db->close();
                $this->db->open();
                $this->_pendingParams = $bakPendingParams;
                $this->errorCount++;
                return $this->execute();
            }
            YII_DEBUG && Yii::endProfile($token, __METHOD__);
            throw $this->db->getSchema()->convertException($e, $rawSql);
        }
    }
    /**
     * Marks a specified table schema to be refreshed after command execution.
     * @param string $name name of the table, which schema should be refreshed.
     * @return $this this command instance
     * @since 2.0.6
     */
    protected function requireTableSchemaRefresh($name)
    {
        $this->_refreshTableName = $name;
        return $this;
    }
    /**
     * Refreshes table schema, which was marked by [[requireTableSchemaRefresh()]]
     * @since 2.0.6
     */
    protected function refreshTableSchema()
    {
        if ($this->_refreshTableName !== null) {
            $this->db->getSchema()->refreshTableSchema($this->_refreshTableName);
        }
    }
}