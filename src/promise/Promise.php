<?php
namespace yii\swoole\promise;


/**
 * Promises/A+ implementation that avoids recursion when possible.
 *
 * @link https://promisesaplus.com/
 */
class Promise implements PromiseInterface
{
    private $state = self::PENDING;
    private $result;
    private $canceller;
    private $handlers = [];
    private $waitFn;
    private $waitList;

    /**
     * @param callable $waitFn   Fn that when invoked resolves the promise.
     * @param callable $canceller Fn that when invoked cancels the promise.
     */
    public function __construct(callable $waitFn = null, callable $canceller = null)
    {
//        if($resolver !== null){
//            $this->call($resolver);
//        }
        $this->waitFn = $waitFn;
        $this->canceller = $canceller;
    }

    private function call(callable $callback)
    {
        try {
            $callback(
                function ($value = null) {
                    $this->resolve($value);
                },
                function ($reason = null) {
                    $this->reject($reason);
                }
            );
        } catch (\Throwable $e) {
            $this->reject($e);
        } catch (\Exception $e) {
            $this->reject($e);
        }
    }

    public function then(callable $onFulfilled = null, callable $onRejected = null)
    {
        if ($this->state === self::PENDING) {
            $next = new Promise(null, [$this, 'cancel']);
            $this->handlers[] = [$next, $onFulfilled, $onRejected];
            $next->waitList = $this->waitList;
            $next->waitList[] = $this;
            return $next;
        }

        // Return a fulfilled promise and immediately invoke any callbacks.
        if ($this->state === self::FULFILLED) {
            return $onFulfilled
                ? Promise\promise_for($this->result)->then($onFulfilled)
                : Promise\promise_for($this->result);
        }

        // It's either cancelled or rejected, so return a rejected promise
        // and immediately invoke any callbacks.
        $rejection = Promise\rejection_for($this->result);
        return $onRejected ? $rejection->then(null, $onRejected) : $rejection;
    }

    private function resolver(callable $onFulfilled = null, callable $onRejected = null)
    {
        return function ($resolve, $reject) use ($onFulfilled, $onRejected) {
            $this->handlers[] = function (PromiseInterface $promise) use ($onFulfilled, $onRejected, $resolve, $reject) {
                $promise
                    ->then($onFulfilled, $onRejected)
                    ->done($resolve, $reject);
            };
//            $this->progressHandlers[] = $progressHandler;
        };
    }

    public function otherwise(callable $onRejected)
    {
        return $this->then(null, $onRejected);
    }

    public function getState()
    {
        return $this->state;
    }

    public function cancel()
    {
        if ($this->state !== self::PENDING || null === $this->canceller) {
            return;
        }

        $canceller = $this->canceller;
        $this->canceller = null;

        $this->call($canceller);

        // Reject the promise only if it wasn't rejected in a then callback.
        if ($this->state === self::PENDING) {
            $this->reject(new CancellationException('Promise has been cancelled'));
        }
    }

    public function resolve($value)
    {
        $this->settle(self::FULFILLED, $value);
    }

    public function reject($reason)
    {
        $this->settle(self::REJECTED, $reason);
    }

    private function settle($state, $value)
    {
        if ($this->state !== self::PENDING) {
            // Ignore calls with the same resolution.
            if ($state === $this->state && $value === $this->result) {
                return;
            }
            throw $this->state === $state
                ? new \LogicException("The promise is already {$state}.")
                : new \LogicException("Cannot change a {$this->state} promise to {$state}");
        }

        if ($value === $this) {
            throw new \LogicException('Cannot fulfill or reject a promise with itself');
        }

        // Clear out the state of the promise but stash the handlers.
        $this->state = $state;
        $this->result = $value;
        $handlers = $this->handlers;
        $this->handlers = null;
        $this->waitList = $this->waitFn = null;
        $this->canceller = null;

        if (!$handlers) {
            return;
        }

        // If the value was not a settled promise or a thenable, then resolve
        // it in the task queue using the correct ID.
        if (!method_exists($value, 'then')) {
            $id = $state === self::FULFILLED ? 1 : 2;
            if($this->waitFn ||$this->waitList){
                // It's a success, so resolve the handlers in the queue.
                Promise\eventloop()->futureTick(static function () use ($id, $value, $handlers) {
                    foreach ($handlers as $handler) {
                        self::callHandler($id, $value, $handler);
                    }
                });
            }else{
                foreach ($handlers as $handler) {
                    self::callHandler($id, $value, $handler);
                }

            }
        } elseif ($value instanceof Promise
            && $value->getState() === self::PENDING
        ) {
            // We can just merge our handlers onto the next promise.
            $value->handlers = array_merge($value->handlers, $handlers);
        } else {
            // Resolve the handlers when the forwarded promise is resolved.
            $value->then(
                static function ($value) use ($handlers) {
                    foreach ($handlers as $handler) {
                        self::callHandler(1, $value, $handler);
                    }
                },
                static function ($reason) use ($handlers) {
                    foreach ($handlers as $handler) {
                        self::callHandler(2, $reason, $handler);
                    }
                }
            );
        }
    }

    /**
     * Call a stack of handlers using a specific callback index and value.
     *
     * @param int   $index   1 (resolve) or 2 (reject).
     * @param mixed $value   Value to pass to the callback.
     * @param array $handler Array of handler data (promise and callbacks).
     *
     * @return array Returns the next group to resolve.
     */
    private static function callHandler($index, $value, array $handler)
    {
        /** @var PromiseInterface $promise */
        $promise = $handler[0];

        // The promise may have been cancelled or resolved before placing
        // this thunk in the queue.
        if ($promise->getState() !== self::PENDING) {
            return;
        }

        try {
            if (isset($handler[$index])) {
                $promise->resolve($handler[$index]($value));
            } elseif ($index === 1) {
                // Forward resolution values as-is.
                $promise->resolve($value);
            } else {
                // Forward rejections down the chain.
                $promise->reject($value);
            }
        } catch (\Throwable $reason) {
            $promise->reject($reason);
        } catch (\Exception $reason) {
            $promise->reject($reason);
        }
    }

    public function wait($unwrap = true)
    {
        $this->waitIfPending();

        $inner = $this->result instanceof PromiseInterface
            ? $this->result->wait($unwrap)
            : $this->result;

        if ($unwrap) {
            if ($this->result instanceof PromiseInterface
                || $this->state === self::FULFILLED
            ) {
                return $inner;
            } else {
                // It's rejected so "unwrap" and throw an exception.
                throw Promise\exception_for($inner);
            }
        }
    }

    private function waitIfPending()
    {
        if ($this->state !== self::PENDING) {
            return;
        }
        elseif ($this->waitFn) {
            $this->invokeWaitFn();
        } elseif ($this->waitList) {
            $this->invokeWaitList();
        } else {
            // If there's not wait function, then reject the promise.
            $this->reject('Cannot wait on a promise that has '
                . 'no internal wait function. You must provide a wait '
                . 'function when constructing the promise to be able to '
                . 'wait on a promise.');
        }

        Promise\eventloop()->run();

        if ($this->state === self::PENDING) {
            $this->reject('Invoking the wait callback did not resolve the promise');
        }
    }

    private function invokeWaitFn()
    {
        try {
            $wfn = $this->waitFn;
            $this->waitFn = null;
            $wfn(true);
        } catch (\Exception $reason) {
            if ($this->state === self::PENDING) {
                // The promise has not been resolved yet, so reject the promise
                // with the exception.
                $this->reject($reason);
            } else {
                // The promise was already resolved, so there's a problem in
                // the application.
                throw $reason;
            }
        }
    }

    private function invokeWaitList()
    {
        $waitList = $this->waitList;
        $this->waitList = null;

        foreach ($waitList as $result) {
            while (true) {
                $result->waitIfPending();

                if ($result->result instanceof Promise) {
                    $result = $result->result;
                } else {
                    if ($result->result instanceof PromiseInterface) {
                        $result->result->wait(false);
                    }
                    break;
                }
            }
        }
    }
}
