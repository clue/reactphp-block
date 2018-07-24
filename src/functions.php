<?php

namespace Clue\React\Block;

use React\EventLoop\LoopInterface;
use React\Promise\PromiseInterface;
use React\Promise\CancellablePromiseInterface;
use UnderflowException;
use Exception;
use React\Promise;
use React\Promise\Timer;
use React\Promise\Timer\TimeoutException;

/**
 * wait/sleep for $time seconds
 *
 * The $time value will be used as a timer for the loop so that it keeps running
 * until the timeout triggers.
 * This implies that if you pass a really small (or negative) value, it will still
 * start a timer and will thus trigger at the earliest possible time in the future.
 *
 * @param float $time
 * @param LoopInterface $loop
 */
function sleep($time, LoopInterface $loop)
{
    await(Timer\resolve($time, $loop), $loop);
}

/**
 * block waiting for the given $promise to resolve
 *
 * Once the promise is resolved, this will return whatever the promise resolves to.
 *
 * Once the promise is rejected, this will throw whatever the promise rejected with.
 * If the promise did not reject with an `Exception`, then this function will
 * throw an `UnexpectedValueException` instead.
 *
 * If no $timeout is given and the promise stays pending, then this will
 * potentially wait/block forever until the promise is settled.
 *
 * If a $timeout is given and the promise is still pending once the timeout
 * triggers, this will cancel() the promise and throw a `TimeoutException`.
 * This implies that if you pass a really small (or negative) value, it will still
 * start a timer and will thus trigger at the earliest possible time in the future.
 *
 * @param PromiseInterface $promise
 * @param LoopInterface    $loop
 * @param null|float       $timeout (optional) maximum timeout in seconds or null=wait forever
 * @return mixed returns whatever the promise resolves to
 * @throws Exception when the promise is rejected
 * @throws TimeoutException if the $timeout is given and triggers
 */
function await(PromiseInterface $promise, LoopInterface $loop, $timeout = null)
{
    $wait = true;
    $resolved = null;
    $exception = null;
    $rejected = false;

    if ($timeout !== null) {
        $promise = Timer\timeout($promise, $timeout, $loop);
    }

    $promise->then(
        function ($c) use (&$resolved, &$wait, $loop) {
            $resolved = $c;
            $wait = false;
            $loop->stop();
        },
        function ($error) use (&$exception, &$rejected, &$wait, $loop) {
            $exception = $error;
            $rejected = true;
            $wait = false;
            $loop->stop();
        }
    );

    // Explicitly overwrite argument with null value. This ensure that this
    // argument does not show up in the stack trace in PHP 7+ only.
    $promise = null;

    while ($wait) {
        $loop->run();
    }

    if ($rejected) {
        if (!$exception instanceof \Exception) {
            $exception = new \UnexpectedValueException(
                'Promise rejected with unexpected value of type ' . (is_object($exception) ? get_class($exception) : gettype($exception)),
                0,
                $exception instanceof \Throwable ? $exception : null
            );
        }

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
 * potentially wait/block forever until the first promise is settled.
 *
 * If a $timeout is given and either promise is still pending once the timeout
 * triggers, this will cancel() all pending promises and throw a `TimeoutException`.
 * This implies that if you pass a really small (or negative) value, it will still
 * start a timer and will thus trigger at the earliest possible time in the future.
 *
 * @param array         $promises
 * @param LoopInterface $loop
 * @param null|float    $timeout (optional) maximum timeout in seconds or null=wait forever
 * @return mixed returns whatever the first promise resolves to
 * @throws Exception if ALL promises are rejected
 * @throws TimeoutException if the $timeout is given and triggers
 */
function awaitAny(array $promises, LoopInterface $loop, $timeout = null)
{
    // Explicitly overwrite argument with null value. This ensure that this
    // argument does not show up in the stack trace in PHP 7+ only.
    $all = $promises;
    $promises = null;

    try {
        // Promise\any() does not cope with an empty input array, so reject this here
        if (!$all) {
            throw new UnderflowException('Empty input array');
        }

        $ret = await(Promise\any($all)->then(null, function () {
            // rejects with an array of rejection reasons => reject with Exception instead
            throw new Exception('All promises rejected');
        }), $loop, $timeout);
    } catch (TimeoutException $e) {
        // the timeout fired
        // => try to cancel all promises (rejected ones will be ignored anyway)
        _cancelAllPromises($all);

        throw $e;
    } catch (Exception $e) {
        // if the above throws, then ALL promises are already rejected
        // => try to cancel all promises (rejected ones will be ignored anyway)
        _cancelAllPromises($all);

        throw new UnderflowException('No promise could resolve', 0, $e);
    }

    // if we reach this, then ANY of the given promises resolved
    // => try to cancel all promises (settled ones will be ignored anyway)
    _cancelAllPromises($all);

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
 * If the promise did not reject with an `Exception`, then this function will
 * throw an `UnexpectedValueException` instead.
 *
 * If no $timeout is given and either promise stays pending, then this will
 * potentially wait/block forever until the last promise is settled.
 *
 * If a $timeout is given and either promise is still pending once the timeout
 * triggers, this will cancel() all pending promises and throw a `TimeoutException`.
 * This implies that if you pass a really small (or negative) value, it will still
 * start a timer and will thus trigger at the earliest possible time in the future.
 *
 * @param array         $promises
 * @param LoopInterface $loop
 * @param null|float    $timeout (optional) maximum timeout in seconds or null=wait forever
 * @return array returns an array with whatever each promise resolves to
 * @throws Exception when ANY promise is rejected
 * @throws TimeoutException if the $timeout is given and triggers
 */
function awaitAll(array $promises, LoopInterface $loop, $timeout = null)
{
    // Explicitly overwrite argument with null value. This ensure that this
    // argument does not show up in the stack trace in PHP 7+ only.
    $all = $promises;
    $promises = null;

    try {
        return await(Promise\all($all), $loop, $timeout);
    } catch (Exception $e) {
        // ANY of the given promises rejected or the timeout fired
        // => try to cancel all promises (rejected ones will be ignored anyway)
        _cancelAllPromises($all);

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
