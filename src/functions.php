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
 * @throws Exception when the promise is rejected
 * @throws TimeoutException if the $timeout is given and triggers
 */
function await(PromiseInterface $promise, LoopInterface $loop, $timeout = null)
{
    $wait = true;
    $resolved = null;
    $exception = null;

    if ($timeout !== null) {
        $promise = Timer\timeout($promise, $timeout, $loop);
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

    if ($exception !== null) {
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
 * @throws Exception if ALL promises are rejected
 * @throws TimeoutException if the $timeout is given and triggers
 */
function awaitAny(array $promises, LoopInterface $loop, $timeout = null)
{
    try {
        // Promise\any() does not cope with an empty input array, so reject this here
        if (!$promises) {
            throw new UnderflowException('Empty input array');
        }

        $ret = await(Promise\any($promises)->then(null, function () {
            // rejects with an array of rejection reasons => reject with Exception instead
            throw new Exception('All promises rejected');
        }), $loop, $timeout);
    } catch (TimeoutException $e) {
        // the timeout fired
        // => try to cancel all promises (rejected ones will be ignored anyway)
        _cancelAllPromises($promises);

        throw $e;
    } catch (Exception $e) {
        // if the above throws, then ALL promises are already rejected
        // => try to cancel all promises (rejected ones will be ignored anyway)
        _cancelAllPromises($promises);

        throw new UnderflowException('No promise could resolve', 0, $e);
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
 * @throws Exception when ANY promise is rejected
 * @throws TimeoutException if the $timeout is given and triggers
 */
function awaitAll(array $promises, LoopInterface $loop, $timeout = null)
{
    try {
        return await(Promise\all($promises), $loop, $timeout);
    } catch (Exception $e) {
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
