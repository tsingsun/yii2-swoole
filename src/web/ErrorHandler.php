<?php
/**
 * Created by PhpStorm.
 * User: tsingsun
 * Date: 2017/3/4
 * Time: 下午10:30
 */

namespace tsingsun\swoole\web;

use Yii;
use yii\base\ErrorException;
use yii\base\ExitException;

/**
 * swoole不支持set_exception_handler,在ErrorHandle中退出的方法都需要重写
 * @package tsingsun\swoole\web
 */
class ErrorHandler extends \yii\web\ErrorHandler
{
    //不需要预先分配内存了
    public $memoryReserveSize = 0;

    public function register()
    {
        ini_set('display_errors', false);
        //set_exception_handler([$this, 'handleException']);
        set_error_handler([$this, 'handleError']);
        register_shutdown_function([$this, 'handleFatalError']);
    }

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
    public function handleFallbackExceptionMessage($exception, $previousException)
    {
        $msg = "An Error occurred while handling another error:\n";
        $msg .= "worker:#" . ($_SERVER['WORKER_ID'] ?? '');
        $msg .= (string)$exception;
        $msg .= "\nPrevious exception:\n";
        $msg .= (string)$previousException;
        if (YII_DEBUG) {
            $msg = '<pre>' . htmlspecialchars($msg, ENT_QUOTES, Yii::$app->charset) . '</pre>';
        } else {
            $msg = 'An internal server error occurred.';
        }
        $this->clearOutput();
        /** @var \swoole_http_response $res */
        $res = Yii::$app->response->getSwooleResponse();
        $res->end($msg);
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

    /**
     * 重写基类的,本方法在swoole进程异常退出时触发.
     * @param bool $isShow
     */
    function handleFatalError()
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
            @$this->renderException($exception);

        }
    }
}
