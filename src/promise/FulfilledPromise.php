<?php
namespace yii\swoole\promise;

/**
 * A promise that has been fulfilled.
 *
 * Thenning off of this promise will invoke the onFulfilled callback
 * immediately and ignore other callbacks.
 */
class FulfilledPromise implements PromiseInterface
{
    private $value;

    public function __construct($value)
    {
        if (method_exists($value, 'then')) {
            throw new \InvalidArgumentException(
                'You cannot create a FulfilledPromise with a promise.');
        }

        $this->value = $value;
    }

    public function then(callable $onFulfilled = null, callable $onRejected = null)
    {
        // Return itself if there is no onFulfilled function.
        if (!$onFulfilled) {
            return $this;
        }

        $queue = Promise\eventloop();
        $p = new Promise([$queue, 'run']);
        $value = $this->value;
        $queue->futureTick(static function () use ($p, $value, $onFulfilled) {
            if ($p->getState() === self::PENDING) {
                try {
                    $p->resolve($onFulfilled($value));
                } catch (\Throwable $e) {
                    $p->reject($e);
                } catch (\Exception $e) {
                    $p->reject($e);
                }
            }
        });

        return $p;

//        try {
//            return Promise\promise_for($onFulfilled($this->value));
//        } catch (\Throwable $exception) {
//            return new RejectedPromise($exception);
//        } catch (\Exception $exception) {
//            return new RejectedPromise($exception);
//        }
    }

    public function otherwise(callable $onRejected)
    {
        return $this->then(null, $onRejected);
    }

    public function wait($unwrap = true, $defaultDelivery = null)
    {
        return $unwrap ? $this->value : null;
    }

    public function getState()
    {
        return self::FULFILLED;
    }

    public function resolve($value)
    {
        if ($value !== $this->value) {
            throw new \LogicException("Cannot resolve a fulfilled promise");
        }
    }

    public function reject($reason)
    {
        throw new \LogicException("Cannot reject a fulfilled promise");
    }

    public function cancel()
    {
        // pass
    }
}
