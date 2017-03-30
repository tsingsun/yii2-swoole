<?php
namespace yii\swoole\promise\Promise;

use Yii;
use yii\swoole\eventloop\EventLoop;
use yii\swoole\eventloop\StreamSelectLoop;
use yii\swoole\promise\Coroutine;
use yii\swoole\promise\Promise;
use yii\swoole\promise\PromiseInterface;
use yii\swoole\promise\FulfilledPromise;
use yii\swoole\promise\RejectedPromise;
use yii\swoole\promise\EachPromise;
use yii\swoole\promise\RejectionException;
use yii\swoole\promise\AggregateException;
use yii\swoole\promise\CancellablePromiseInterface;
use yii\swoole\eventLoop\LoopInterface;
use yii\swoole\promise\TimeoutException;

/**
 * @return LoopInterface
 */
function eventloop()
{
    if(Yii::$app->has('eventloop')){
        return Yii::$app->get('eventloop')->loop;
    }else{
        $el = new EventLoop();
        $el->setLoop(new StreamSelectLoop());
        Yii::$app->set('eventloop',$el);
    }
}

/**
 * 将符合nodeCallable标准的方法转为promise
 * @param $fn
 * @param $waitfn function($primose){}
 * @return \Closure
 */
function promisify($fn,callable $waitfn = null) {
    return function() use ($fn,$waitfn) {
        $args = func_get_args();
        if($waitfn ===null){
            $promise = new Promise();
        }else{
            $promise = new Promise(
                function()use(&$promise,$waitfn){
                    if(is_callable($waitfn)){
                        $waitfn($promise);
                    }
                });
        }
        $callback = function() use ($promise) {
            switch (func_num_args()) {
                case 0: $promise->resolve(NULL); break;
                case 1: $promise->resolve(func_get_arg(0)); break;
                default: $promise->resolve(func_get_args()); break;
            }
        };
        $args[] = &$callback;
        try {
            call_user_func_array($fn, $args);
        }
        catch (\Exception $e) {
            $promise->reject($e);
        }
        catch (\Throwable $e) {
            $promise->reject($e);
        }
        return $promise;
    };
}

/**
 * 将一个非promise的值转化为promise对象
 *
 * @param mixed $value Promise or value.
 *
 * @return PromiseInterface
 */
function promise_for($value)
{
    if ($value instanceof PromiseInterface) {
        return $value;
    }
    if($value instanceof \Generator){
        return new Coroutine($value);
    }

    // 对符合promise规范的对象也可以进行转化
    if (method_exists($value, 'then')) {
        $resolver = method_exists($value, 'wait') ? [$value, 'wait'] : null;
        $cfn = method_exists($value, 'cancel') ? [$value, 'cancel'] : null;
        $promise = new Promise($resolver, $cfn);
        $value->then([$promise, 'wait'], [$promise, 'reject']);
        return $promise;
    }

    return new FulfilledPromise($value);
}

/**
 * 创建RejectionPromise对象
 *
 * @param mixed $reason Promise or reason.
 *
 * @return PromiseInterface
 */
function rejection_for($reason)
{
    if ($reason instanceof PromiseInterface) {
        return $reason;
    }

    return new RejectedPromise($reason);
}

/**
 * 根据rejected promise的原因的异常
 *
 * @param mixed $reason
 *
 * @return \Exception|\Throwable
 */
function exception_for($reason)
{
    return $reason instanceof \Exception || $reason instanceof \Throwable
        ? $reason
        : new RejectionException($reason);
}

/**
 * 返回对应输入的迭代器
 *
 * @param mixed $value
 *
 * @return \Iterator
 */
function iter_for($value)
{
    if ($value instanceof \Iterator) {
        return $value;
    } elseif (is_array($value)) {
        return new \ArrayIterator($value);
    } else {
        return new \ArrayIterator([$value]);
    }
}

/**
 * Given an array of promises, return a promise that is fulfilled when all the
 * items in the array are fulfilled.
 *
 * The promise's fulfillment value is an array with fulfillment values at
 * respective positions to the original array. If any promise in the array
 * rejects, the returned promise is rejected with the rejection reason.
 *
 * @param mixed $promises Promises or values.
 *
 * @return PromiseInterface
 */
function all($promises)
{
    $results = [];
    return each(
        $promises,
        function ($value, $idx) use (&$results) {
            $results[$idx] = $value;
        },
        function ($reason, $idx, Promise $aggregate) {
            $aggregate->reject($reason);
        }
    )->then(function () use (&$results) {
        ksort($results);
        return $results;
    });
}

/**
 * Initiate a competitive race between multiple promises or values (values will
 * become immediately fulfilled promises).
 *
 * When count amount of promises have been fulfilled, the returned promise is
 * fulfilled with an array that contains the fulfillment values of the winners
 * in order of resolution.
 *
 * This prommise is rejected with a {@see yii\swoole\promise\AggregateException}
 * if the number of fulfilled promises is less than the desired $count.
 *
 * @param int   $count    Total number of promises.
 * @param mixed $promises Promises or values.
 *
 * @return PromiseInterface
 */
function some($count, $promises)
{
    $results = [];
    $rejections = [];

    return each(
        $promises,
        function ($value, $idx, PromiseInterface $p) use (&$results, $count) {
            if ($p->getState() !== PromiseInterface::PENDING) {
                return;
            }
            $results[$idx] = $value;
            if (count($results) >= $count) {
                $p->resolve(null);
            }
        },
        function ($reason) use (&$rejections) {
            $rejections[] = $reason;
        }
    )->then(
        function () use (&$results, &$rejections, $count) {
            if (count($results) !== $count) {
                throw new AggregateException(
                    'Not enough promises to fulfill count',
                    $rejections
                );
            }
            ksort($results);
            return array_values($results);
        }
    );
}

/**
 * Like some(), with 1 as count. However, if the promise fulfills, the
 * fulfillment value is not an array of 1 but the value directly.
 *
 * @param mixed $promises Promises or values.
 *
 * @return PromiseInterface
 */
function any($promises)
{
    return some(1, $promises)->then(function ($values) { return $values[0]; });
}

/**
 * Returns a promise that is fulfilled when all of the provided promises have
 * been fulfilled or rejected.
 *
 * The returned promise is fulfilled with an array of inspection state arrays.
 *
 * @param mixed $promises Promises or values.
 *
 * @return PromiseInterface
 * @see yii\swoole\promise\inspect for the inspection state array format.
 */
function settle($promises)
{
    $results = [];

    return each(
        $promises,
        function ($value, $idx) use (&$results) {
            $results[$idx] = ['state' => PromiseInterface::FULFILLED, 'value' => $value];
        },
        function ($reason, $idx) use (&$results) {
            $results[$idx] = ['state' => PromiseInterface::REJECTED, 'reason' => $reason];
        }
    )->then(function () use (&$results) {
        ksort($results);
        return $results;
    });
}

/**
 * Given an iterator that yields promises or values, returns a promise that is
 * fulfilled with a null value when the iterator has been consumed or the
 * aggregate promise has been fulfilled or rejected.
 *
 * $onFulfilled is a function that accepts the fulfilled value, iterator
 * index, and the aggregate promise. The callback can invoke any necessary side
 * effects and choose to resolve or reject the aggregate promise if needed.
 *
 * $onRejected is a function that accepts the rejection reason, iterator
 * index, and the aggregate promise. The callback can invoke any necessary side
 * effects and choose to resolve or reject the aggregate promise if needed.
 *
 * @param mixed    $iterable    Iterator or array to iterate over.
 * @param callable $onFulfilled
 * @param callable $onRejected
 *
 * @return PromiseInterface
 */
function each(
    $iterable,
    callable $onFulfilled = null,
    callable $onRejected = null
) {
    return (new EachPromise($iterable, [
        'fulfilled' => $onFulfilled,
        'rejected'  => $onRejected
    ]))->promise();
}

/**
 * Like each, but only allows a certain number of outstanding promises at any
 * given time.
 *
 * $concurrency may be an integer or a function that accepts the number of
 * pending promises and returns a numeric concurrency limit value to allow for
 * dynamic a concurrency size.
 *
 * @param mixed        $iterable
 * @param int|callable $concurrency
 * @param callable     $onFulfilled
 * @param callable     $onRejected
 *
 * @return PromiseInterface
 */
function each_limit(
    $iterable,
    $concurrency,
    callable $onFulfilled = null,
    callable $onRejected = null
) {
    return (new EachPromise($iterable, [
        'fulfilled'   => $onFulfilled,
        'rejected'    => $onRejected,
        'concurrency' => $concurrency
    ]))->promise();
}

/**
 * Like each_limit, but ensures that no promise in the given $iterable argument
 * is rejected. If any promise is rejected, then the aggregate promise is
 * rejected with the encountered rejection.
 *
 * @param mixed        $iterable
 * @param int|callable $concurrency
 * @param callable     $onFulfilled
 *
 * @return PromiseInterface
 */
function each_limit_all(
    $iterable,
    $concurrency,
    callable $onFulfilled = null
) {
    return each_limit(
        $iterable,
        $concurrency,
        $onFulfilled,
        function ($reason, $idx, PromiseInterface $aggregate) {
            $aggregate->reject($reason);
        }
    );
}

/**
 * 指示promise是否完成.
 *
 * @param PromiseInterface $promise
 *
 * @return bool
 */
function is_fulfilled(PromiseInterface $promise)
{
    return $promise->getState() === PromiseInterface::FULFILLED;
}

/**
 * 指示promise是否被rejectd.
 *
 * @param PromiseInterface $promise
 *
 * @return bool
 */
function is_rejected(PromiseInterface $promise)
{
    return $promise->getState() === PromiseInterface::REJECTED;
}

/**
 * Returns true if a promise is fulfilled or rejected.
 *
 * @param PromiseInterface $promise
 *
 * @return bool
 */
function is_settled(PromiseInterface $promise)
{
    return $promise->getState() !== PromiseInterface::PENDING;
}

/**
 * 协程入口
 * @see Coroutine
 *
 * @param $generator
 *
 * @return Coroutine
 */
function coroutine($generator)
{
    if (is_callable($generator)) {
        $args = array_slice(func_get_args(), 1);
        $generator = call_user_func_array($generator, $args);
    }
    if (!($generator instanceof \Generator)) {
//        return promise_for($generator);
    }
    return new Coroutine($generator);
}

function timeout(PromiseInterface $promise, $time, LoopInterface $loop)
{
    // cancelling this promise will only try to cancel the input promise,
    // thus leaving responsibility to the input promise.
    $canceller = null;
    if ($promise instanceof CancellablePromiseInterface) {
        $canceller = array($promise, 'cancel');
    }

    return new Promise(function ($resolve, $reject) use ($loop, $time, $promise) {
        $timer = $loop->addTimer($time, function () use ($time, $promise, $reject) {
            $reject(new TimeoutException($time, 'Timed out after ' . $time . ' seconds'));

            if ($promise instanceof CancellablePromiseInterface) {
                $promise->cancel();
            }
        });

        $promise->then(function ($v) use ($timer, $loop, $resolve) {
            $loop->cancelTimer($timer);
            $resolve($v);
        }, function ($v) use ($timer, $loop, $reject) {
            $loop->cancelTimer($timer);
            $reject($v);
        });
    }, $canceller);
}

function resolve($time, LoopInterface $loop)
{
    return new Promise(function ($resolve) use ($loop, $time, &$timer) {
        // resolve the promise when the timer fires in $time seconds
        $timer = $loop->addTimer($time, function () use ($time, $resolve) {
            $resolve($time);
        });
    }, function ($resolveUnused, $reject) use (&$timer, $loop) {
        // cancelling this promise will cancel the timer and reject
        $loop->cancelTimer($timer);
        $reject(new \RuntimeException('Timer cancelled'));
    });
}

function reject($time, LoopInterface $loop)
{
    return resolve($time, $loop)->then(function ($time) {
        throw new TimeoutException($time, 'Timer expired after ' . $time . ' seconds');
    });
}

/**
 * wait/sleep for $time seconds
 *
 * @param float $time
 * @param LoopInterface $loop
 */
function sleep($time, LoopInterface $loop)
{
    await(resolve($time, $loop), $loop);
}

/**
 * block waiting for the given $promise to resolve
 *
 * Once the promise is resolved, this will return whatever the promise resolves to.
 *
 * Once the promise is rejected, this will throw whatever the promise rejected with.
 *
 * If no $timeout is given and the promise stays pending, then this will
 * potentially wait/block forever until the promise is settled.
 *
 * If a $timeout is given and the promise is still pending once the timeout
 * triggers, this will cancel() the promise and throw a `TimeoutException`.
 *
 * @param PromiseInterface $promise
 * @param LoopInterface    $loop
 * @param null|float       $timeout (optional) maximum timeout in seconds or null=wait forever
 * @return mixed returns whatever the promise resolves to
 * @throws \Throwable when the promise is rejected
 * @throws TimeoutException if the $timeout is given and triggers
 */
function await(PromiseInterface $promise, LoopInterface $loop, $timeout = null)
{
    $wait = true;
    $resolved = null;
    $exception = null;

    if ($timeout !== null) {
        $promise = timeout($promise, $timeout, $loop);
    }

    $promise->then(
        function ($c) use (&$resolved, &$wait, $loop) {
            $resolved = $c;
            $wait = false;
            $loop->stop();
        },
        function ($error) use (&$exception, &$wait, $loop) {
            $exception = $error;
            $wait = false;
            $loop->stop();
        }
    );

    while ($wait) {
        $loop->run();
    }

    if ($exception instanceof \Throwable) {
        throw $exception;
    }

    return $resolved;
}

/**
 * wait for ANY of the given promises to resolve
 *
 * Once the first promise is resolved, this will try to cancel() all
 * remaining promises and return whatever the first promise resolves to.
 *
 * If ALL promises fail to resolve, this will fail and throw an Exception.
 *
 * If no $timeout is given and either promise stays pending, then this will
 * potentially wait/block forever until the last promise is settled.
 *
 * If a $timeout is given and either promise is still pending once the timeout
 * triggers, this will cancel() all pending promises and throw a `TimeoutException`.
 *
 * @param array         $promises
 * @param LoopInterface $loop
 * @param null|float    $timeout (optional) maximum timeout in seconds or null=wait forever
 * @return mixed returns whatever the first promise resolves to
 * @throws \Exception if ALL promises are rejected
 * @throws TimeoutException if the $timeout is given and triggers
 */
function awaitAny(array $promises, LoopInterface $loop, $timeout = null)
{
    try {
        // Promise\any() does not cope with an empty input array, so reject this here
        if (!$promises) {
            throw new \UnderflowException('Empty input array');
        }

        $ret = await(Promise\any($promises)->then(null, function () {
            // rejects with an array of rejection reasons => reject with Exception instead
            throw new \Exception('All promises rejected');
        }), $loop, $timeout);
    } catch (TimeoutException $e) {
        // the timeout fired
        // => try to cancel all promises (rejected ones will be ignored anyway)
        _cancelAllPromises($promises);

        throw $e;
    } catch (\Exception $e) {
        // if the above throws, then ALL promises are already rejected
        // => try to cancel all promises (rejected ones will be ignored anyway)
        _cancelAllPromises($promises);

        throw new \UnderflowException('No promise could resolve', 0, $e);
    }

    // if we reach this, then ANY of the given promises resolved
    // => try to cancel all promises (settled ones will be ignored anyway)
    _cancelAllPromises($promises);

    return $ret;
}

/**
 * wait for ALL of the given promises to resolve
 *
 * Once the last promise resolves, this will return an array with whatever
 * each promise resolves to. Array keys will be left intact, i.e. they can
 * be used to correlate the return array to the promises passed.
 *
 * If ANY promise fails to resolve, this will try to cancel() all
 * remaining promises and throw an Exception.
 *
 * If no $timeout is given and either promise stays pending, then this will
 * potentially wait/block forever until the last promise is settled.
 *
 * If a $timeout is given and either promise is still pending once the timeout
 * triggers, this will cancel() all pending promises and throw a `TimeoutException`.
 *
 * @param array         $promises
 * @param LoopInterface $loop
 * @param null|float    $timeout (optional) maximum timeout in seconds or null=wait forever
 * @return array returns an array with whatever each promise resolves to
 * @throws \Exception when ANY promise is rejected
 * @throws TimeoutException if the $timeout is given and triggers
 */
function awaitAll(array $promises, LoopInterface $loop, $timeout = null)
{
    try {
        return await(Promise\all($promises), $loop, $timeout);
    } catch (\Exception $e) {
        // ANY of the given promises rejected or the timeout fired
        // => try to cancel all promises (rejected ones will be ignored anyway)
        _cancelAllPromises($promises);

        throw $e;
    }
}

/**
 * internal helper function used to iterate over an array of Promise instances and cancel() each
 *
 * @internal
 * @param array $promises
 */
function _cancelAllPromises(array $promises)
{
    foreach ($promises as $promise) {
        if ($promise instanceof CancellablePromiseInterface) {
            $promise->cancel();
        }
    }
}
