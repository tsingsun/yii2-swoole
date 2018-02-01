<?php
/**
 * Created by PhpStorm.
 * User: tsingsun
 * Date: 2017/12/15
 * Time: 上午10:02
 */

namespace tsingsun\swoole\redis\cm;

use yii\base\Exception;

class Connection extends \yii\redis\Connection
{
    /**
     * @var resource redis socket connection
     */
    private $_socket;
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
        $this->_socket = @stream_socket_client(
            $this->unixSocket ? 'unix://' . $this->unixSocket : 'tcp://' . $this->hostname . ':' . $this->port,
            $errorNumber,
            $errorDescription,
            $this->connectionTimeout ? $this->connectionTimeout : ini_get("default_socket_timeout")
        );
        if ($this->_socket) {
            if ($this->dataTimeout !== null) {
                stream_set_timeout($this->_socket, $timeout = (int) $this->dataTimeout, (int) (($this->dataTimeout - $timeout) * 1000000));
            }
            if ($this->password !== null) {
                $this->executeCommand('AUTH', [$this->password]);
            }
            $this->executeCommand('SELECT', [$this->database]);
            $this->initConnection();
        } else {
            \Yii::error("Failed to open redis DB connection ($connection): $errorNumber - $errorDescription", __CLASS__);
            $message = YII_DEBUG ? "Failed to open redis DB connection ($connection): $errorNumber - $errorDescription" : 'Failed to open DB connection.';
            throw new \Exception($message, (int) $errorNumber);
        }
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
        $oldName   = $name;
        $oldParams = $params;
        array_unshift($params, $name);
        $command = '*' . count($params) . "\r\n";
        foreach ($params as $arg) {
            $command .= '$' . mb_strlen($arg, '8bit') . "\r\n" . $arg . "\r\n";
        }
        \Yii::trace("Executing Redis Command: {$name}", __METHOD__);
        $length = @fwrite($this->_socket, $command);
        if ($length === false || $length < mb_strlen($command)) {
            if ($reconnect == 0) {
                $this->forceClose();
                return $this->executeCommand($oldName, $oldParams, ++$reconnect);
            } else {
                throw new Exception('Try to send command to redis server fail. Maybe redis server has gone away.');
            }
        }
        return $this->parseResponse(implode(' ', $params), $oldName, $oldParams);
    }
    /**
     * Closes the currently active DB connection.
     * It does nothing if the connection is already closed.
     */
    public function forceClose()
    {
        if ($this->_socket !== null) {
            try {
                $connection = ($this->unixSocket ?: $this->hostname . ':' . $this->port) . ', database=' . $this->database;
                \Yii::trace('Closing DB connection: ' . $connection, __METHOD__);
                stream_socket_shutdown($this->_socket, STREAM_SHUT_RDWR);
            } catch (\Exception $e) {
            }
            $this->_socket = null;
        }
    }
    /**
     * @param string $command
     * @return mixed
     * @throws Exception on error
     */
    private function parseResponse($command, $name = '', $params = [], $reconnect = 0)
    {
        if (($line = fgets($this->_socket)) === false) {
            if ($reconnect == 0) {
                $this->forceClose();
                return $this->executeCommand($name, $params, ++$reconnect);
            } else {
                throw new Exception("Failed to read from socket.\nRedis command was: " . $command);
            }
        }
        $type = $line[0];
        $line = mb_substr($line, 1, -2, '8bit');
        switch ($type) {
            case '+': // Status reply
                if ($line === 'OK' || $line === 'PONG') {
                    return true;
                } else {
                    return $line;
                }
            case '-': // Error reply
                throw new Exception("Redis error: " . $line . "\nRedis command was: " . $command);
            case ':': // Integer reply
                // no cast to int as it is in the range of a signed 64 bit integer
                return $line;
            case '$': // Bulk replies
                if ($line == '-1') {
                    return null;
                }
                $length = $line + 2;
                $data   = '';
                while ($length > 0) {
                    if (($block = fread($this->_socket, $length)) === false) {
                        throw new Exception("Failed to read from socket.\nRedis command was: " . $command);
                    }
                    $data .= $block;
                    $length -= mb_strlen($block, '8bit');
                }
                return mb_substr($data, 0, -2, '8bit');
            case '*': // Multi-bulk replies
                $count = (int) $line;
                $data  = [];
                for ($i = 0; $i < $count; $i++) {
                    $data[] = $this->parseResponse($command, $name, $params);
                }
                return $data;
            default:
                throw new Exception('Received illegal data from redis: ' . $line . "\nRedis command was: " . $command);
        }
    }
}