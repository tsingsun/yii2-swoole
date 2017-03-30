<?php
namespace yii\swoole\promise;

/**
 * 表示一个被rejected的promise
 *
 * Thenning off of this promise will invoke the onRejected callback
 * immediately and ignore other callbacks.
 */
class RejectedPromise implements PromiseInterface
{
    private $reason;

    public function __construct($reason)
    {
        if (method_exists($reason, 'then')) {
            throw new \InvalidArgumentException(
                'You cannot create a RejectedPromise with a promise.');
        }

        $this->reason = $reason;
    }

    public function then(callable $onFulfilled = null, callable $onRejected = null) {
        // If there's no onRejected callback then just return self.
        if (!$onRejected) {
            return $this;
        }
        try {
            return Promise\promise_for($onRejected($this->reason));
        } catch (\Throwable $exception) {
            return new RejectedPromise($exception);
        } catch (\Exception $exception) {
            return new RejectedPromise($exception);
        }
    }

    public function otherwise(callable $onRejected)
    {
        return $this->then(null, $onRejected);
    }

    public function wait($unwrap = true, $defaultDelivery = null)
    {
        if ($unwrap) {
            throw Promise\exception_for($this->reason);
        }
    }

    public function getState()
    {
        return self::REJECTED;
    }

    public function resolve($value)
    {
        throw new \LogicException("Cannot resolve a rejected promise");
    }

    public function reject($reason)
    {
        if ($reason !== $this->reason) {
            throw new \LogicException("Cannot reject a rejected promise");
        }
    }

    public function cancel()
    {
        // pass
    }
}
