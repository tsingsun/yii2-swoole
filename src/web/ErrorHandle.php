<?php
/**
 * Created by PhpStorm.
 * User: tsingsun
 * Date: 2017/3/4
 * Time: 下午10:30
 */

namespace yii\swoole\web;

use Yii;
use yii\base\ErrorException;
use yii\base\UserException;
use yii\web\ErrorHandler;
use yii\base\ExitException;
use yii\web\HttpException;

/**
 * swoole不支持set_exception_handler,在ErrorHandle中退出的方法都需要重写
 * @package yii\swoole\web
 */
class ErrorHandle extends ErrorHandler
{

    public function handleException($exception)
    {
        if ($exception instanceof ExitException) {
            return;
        }

        $this->exception = $exception;

        // disable error capturing to avoid recursive errors while handling exceptions
        // 在swoole中是导常关联是全局函数,不需要解决.
        //$this->unregister();

        // set preventive HTTP status code to 500 in case error handling somehow fails and headers are sent
        // HTTP exceptions will override this value in renderException()
        if (PHP_SAPI !== 'cli') {
            http_response_code(500);
        }

        try {
            $this->logException($exception);
            if ($this->discardExistingOutput) {
                $this->clearOutput();
            }
            $this->renderException($exception);
        } catch (\Exception $e) {
            // an other exception could be thrown while displaying the exception
            $this->handleFallbackExceptionMessage($e, $exception);
        } catch (\Throwable $e) {
            // additional check for \Throwable introduced in PHP 7
            $this->handleFallbackExceptionMessage($e, $exception);
        }

        $this->exception = null;
    }

    /**
     * Handles exception thrown during exception processing in [[handleException()]].
     * @param \Exception|\Throwable $exception Exception that was thrown during main exception processing.
     * @param \Exception $previousException Main exception processed in [[handleException()]].
     * @since 2.0.11
     */
    public function handleFallbackExceptionMessage($exception, $previousException) {
        $msg = "An Error occurred while handling another error:\n";
        $msg .= (string) $exception;
        $msg .= "\nPrevious exception:\n";
        $msg .= (string) $previousException;
        $endResponse = Yii::$app->getResponse();
        if (YII_DEBUG) {
            if (PHP_SAPI === 'cli') {
                if($endResponse instanceof Response && !$endResponse->isSent){
                    $endResponse->getSwooleResponse()->end($msg);
                }else{
                    echo $msg . "\n";
                }
            } else {
                echo '<pre>' . htmlspecialchars($msg, ENT_QUOTES, Yii::$app->charset) . '</pre>';
            }
        } else {
            $msg = 'An internal server error occurred.';
            if($endResponse instanceof Response){
                $endResponse->getSwooleResponse()->end($msg);
            }else{
                echo $msg;
            }
        }
        $msg .= "\n\$_SERVER = " . print_r($_SERVER, true);
        error_log($msg);
        if (defined('HHVM_VERSION')) {
            flush();
        }
    }

    public function handleError($code, $message, $file, $line)
    {
        if (error_reporting() & $code) {
            // load ErrorException manually here because autoloading them will not work
            // when error occurs while autoloading a class
            if (!class_exists('yii\\base\\ErrorException', false)) {
                require_once(\Yii::getAlias('@yii/base/ErrorException.php'));
            }
            $exception = new ErrorException($message, $code, $code, $file, $line);

            // in case error appeared in __toString method we can't throw any exception
            $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
            array_shift($trace);
            foreach ($trace as $frame) {
                if ($frame['function'] === '__toString') {
                    $this->handleException($exception);
                    return true;
                }
            }

            throw $exception;
        }
        return false;
    }

    public function handleFatalError()
    {
//        unset($this->_memoryReserve);

        // load ErrorException manually here because autoloading them will not work
        // when error occurs while autoloading a class

        if (!class_exists('yii\\base\\ErrorException', false)) {
            require_once(\Yii::getAlias('@yii/base/ErrorException.php'));
        }

        $error = error_get_last();

        if (ErrorException::isFatalError($error)) {
            $exception = new ErrorException($error['message'], $error['type'], $error['type'], $error['file'], $error['line']);
            $this->exception = $exception;

            $this->logException($exception);

            if ($this->discardExistingOutput) {
                $this->clearOutput();
            }
            $this->renderException($exception);
        }
    }

    public function renderException($exception)
    {
        parent::renderException($exception);
    }
}