<?php
namespace yii\swoole\promise;

/**
 * promise执行中断方法的异常
 */
class CancellationException extends RejectionException
{
}
