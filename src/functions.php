<?php

namespace Clue\React\Block;

use React\EventLoop\Loop;
use React\EventLoop\LoopInterface;
use React\Promise;
use React\Promise\CancellablePromiseInterface;
use React\Promise\PromiseInterface;
use React\Promise\Timer;
use React\Promise\Timer\TimeoutException;
use Exception;
use UnderflowException;

/**
 * Wait/sleep for `$time` seconds.
 *
 * ```php
 * Clue\React\Block\sleep(1.5, $loop);
 * ```
 *
 * This function will only return after the given `$time` has elapsed. In the
 * meantime, the event loop will run any other events attached to the same loop
 * until the timer fires. If there are no other events attached to this loop,
 * it will behave similar to the built-in [`sleep()`](https://www.php.net/manual/en/function.sleep.php).
 *
 * Internally, the `$time` argument will be used as a timer for the loop so that
 * it keeps running until this timer triggers. This implies that if you pass a
 * really small (or negative) value, it will still start a timer and will thus
 * trigger at the earliest possible time in the future.
 *
 * This function takes an optional `LoopInterface|null $loop` parameter that can be used to
 * pass the event loop instance to use. You can use a `null` value here in order to
 * use the [default loop](https://github.com/reactphp/event-loop#loop). This value
 * SHOULD NOT be given unless you're sure you want to explicitly use a given event
 * loop instance.
 *
 * Note that this function will assume control over the event loop. Internally, it
 * will actually `run()` the loop until the timer fires and then calls `stop()` to
 * terminate execution of the loop. This means this function is more suited for
 * short-lived program executions when using async APIs is not feasible. For
 * long-running applications, using event-driven APIs by leveraging timers
 * is usually preferable.
 *
 * @param float $time
 * @param ?LoopInterface $loop
 * @return void
 */
function sleep($time, LoopInterface $loop = null)
{
    await(Timer\resolve($time, $loop), $loop);
}

/**
 * Block waiting for the given `$promise` to be fulfilled.
 *
 * ```php
 * $result = Clue\React\Block\await($promise, $loop);
 * ```
 *
 * This function will only return after the given `$promise` has settled, i.e.
 * either fulfilled or rejected. In the meantime, the event loop will run any
 * events attached to the same loop until the promise settles.
 *
 * Once the promise is fulfilled, this function will return whatever the promise
 * resolved to.
 *
 * Once the promise is rejected, this will throw whatever the promise rejected
 * with. If the promise did not reject with an `Exception`, then this function
 * will throw an `UnexpectedValueException` instead.
 *
 * ```php
 * try {
 *     $result = Clue\React\Block\await($promise, $loop);
 *     // promise successfully fulfilled with $result
 *     echo 'Result: ' . $result;
 * } catch (Exception $exception) {
 *     // promise rejected with $exception
 *     echo 'ERROR: ' . $exception->getMessage();
 * }
 * ```
 *
 * See also the [examples](../examples/).
 *
 * This function takes an optional `LoopInterface|null $loop` parameter that can be used to
 * pass the event loop instance to use. You can use a `null` value here in order to
 * use the [default loop](https://github.com/reactphp/event-loop#loop). This value
 * SHOULD NOT be given unless you're sure you want to explicitly use a given event
 * loop instance.
 *
 * If no `$timeout` argument is given and the promise stays pending, then this
 * will potentially wait/block forever until the promise is settled. To avoid
 * this, API authors creating promises are expected to provide means to
 * configure a timeout for the promise instead. For more details, see also the
 * [`timeout()` function](https://github.com/reactphp/promise-timer#timeout).
 *
 * If the deprecated `$timeout` argument is given and the promise is still pending once the
 * timeout triggers, this will `cancel()` the promise and throw a `TimeoutException`.
 * This implies that if you pass a really small (or negative) value, it will still
 * start a timer and will thus trigger at the earliest possible time in the future.
 *
 * Note that this function will assume control over the event loop. Internally, it
 * will actually `run()` the loop until the promise settles and then calls `stop()` to
 * terminate execution of the loop. This means this function is more suited for
 * short-lived promise executions when using promise-based APIs is not feasible.
 * For long-running applications, using promise-based APIs by leveraging chained
 * `then()` calls is usually preferable.
 *
 * @param PromiseInterface $promise
 * @param ?LoopInterface   $loop
 * @param ?float           $timeout [deprecated] (optional) maximum timeout in seconds or null=wait forever
 * @return mixed returns whatever the promise resolves to
 * @throws Exception when the promise is rejected
 * @throws TimeoutException if the $timeout is given and triggers
 */
function await(PromiseInterface $promise, LoopInterface $loop = null, $timeout = null)
{
    $wait = true;
    $resolved = null;
    $exception = null;
    $rejected = false;
    $loop = $loop ?: Loop::get();

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
        if (!$exception instanceof \Exception && !$exception instanceof \Throwable) {
            $exception = new \UnexpectedValueException(
                'Promise rejected with unexpected value of type ' . (is_object($exception) ? get_class($exception) : gettype($exception))
            );
        } elseif (!$exception instanceof \Exception) {
            $exception = new \UnexpectedValueException(
                'Promise rejected with unexpected ' . get_class($exception) . ': ' . $exception->getMessage(),
                $exception->getCode(),
                $exception
            );
        }

        throw $exception;
    }

    return $resolved;
}

/**
 * Wait for ANY of the given promises to be fulfilled.
 *
 * ```php
 * $promises = array(
 *     $promise1,
 *     $promise2
 * );
 *
 * $firstResult = Clue\React\Block\awaitAny($promises, $loop);
 *
 * echo 'First result: ' . $firstResult;
 * ```
 *
 * See also the [examples](../examples/).
 *
 * This function will only return after ANY of the given `$promises` has been
 * fulfilled or will throw when ALL of them have been rejected. In the meantime,
 * the event loop will run any events attached to the same loop.
 *
 * Once ANY promise is fulfilled, this function will return whatever this
 * promise resolved to and will try to `cancel()` all remaining promises.
 *
 * Once ALL promises reject, this function will fail and throw an `UnderflowException`.
 * Likewise, this will throw if an empty array of `$promises` is passed.
 *
 * This function takes an optional `LoopInterface|null $loop` parameter that can be used to
 * pass the event loop instance to use. You can use a `null` value here in order to
 * use the [default loop](https://github.com/reactphp/event-loop#loop). This value
 * SHOULD NOT be given unless you're sure you want to explicitly use a given event
 * loop instance.
 *
 * If no `$timeout` argument is given and ALL promises stay pending, then this
 * will potentially wait/block forever until the promise is fulfilled. To avoid
 * this, API authors creating promises are expected to provide means to
 * configure a timeout for the promise instead. For more details, see also the
 * [`timeout()` function](https://github.com/reactphp/promise-timer#timeout).
 *
 * If the deprecated `$timeout` argument is given and ANY promises are still pending once
 * the timeout triggers, this will `cancel()` all pending promises and throw a
 * `TimeoutException`. This implies that if you pass a really small (or negative)
 * value, it will still start a timer and will thus trigger at the earliest
 * possible time in the future.
 *
 * Note that this function will assume control over the event loop. Internally, it
 * will actually `run()` the loop until the promise settles and then calls `stop()` to
 * terminate execution of the loop. This means this function is more suited for
 * short-lived promise executions when using promise-based APIs is not feasible.
 * For long-running applications, using promise-based APIs by leveraging chained
 * `then()` calls is usually preferable.
 *
 * @param PromiseInterface[]  $promises
 * @param ?LoopInterface      $loop
 * @param ?float              $timeout [deprecated] (optional) maximum timeout in seconds or null=wait forever
 * @return mixed returns whatever the first promise resolves to
 * @throws Exception if ALL promises are rejected
 * @throws TimeoutException if the $timeout is given and triggers
 */
function awaitAny(array $promises, LoopInterface $loop = null, $timeout = null)
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
 * Wait for ALL of the given promises to be fulfilled.
 *
 * ```php
 * $promises = array(
 *     $promise1,
 *     $promise2
 * );
 *
 * $allResults = Clue\React\Block\awaitAll($promises, $loop);
 *
 * echo 'First promise resolved with: ' . $allResults[0];
 * ```
 *
 * See also the [examples](../examples/).
 *
 * This function will only return after ALL of the given `$promises` have been
 * fulfilled or will throw when ANY of them have been rejected. In the meantime,
 * the event loop will run any events attached to the same loop.
 *
 * Once ALL promises are fulfilled, this will return an array with whatever
 * each promise resolves to. Array keys will be left intact, i.e. they can
 * be used to correlate the return array to the promises passed.
 *
 * Once ANY promise rejects, this will try to `cancel()` all remaining promises
 * and throw an `Exception`. If the promise did not reject with an `Exception`,
 * then this function will throw an `UnexpectedValueException` instead.
 *
 * This function takes an optional `LoopInterface|null $loop` parameter that can be used to
 * pass the event loop instance to use. You can use a `null` value here in order to
 * use the [default loop](https://github.com/reactphp/event-loop#loop). This value
 * SHOULD NOT be given unless you're sure you want to explicitly use a given event
 * loop instance.
 *
 * If no `$timeout` argument is given and ANY promises stay pending, then this
 * will potentially wait/block forever until the promise is fulfilled. To avoid
 * this, API authors creating promises are expected to provide means to
 * configure a timeout for the promise instead. For more details, see also the
 * [`timeout()` function](https://github.com/reactphp/promise-timer#timeout).
 *
 * If the deprecated `$timeout` argument is given and ANY promises are still pending once
 * the timeout triggers, this will `cancel()` all pending promises and throw a
 * `TimeoutException`. This implies that if you pass a really small (or negative)
 * value, it will still start a timer and will thus trigger at the earliest
 * possible time in the future.
 *
 * Note that this function will assume control over the event loop. Internally, it
 * will actually `run()` the loop until the promise settles and then calls `stop()` to
 * terminate execution of the loop. This means this function is more suited for
 * short-lived promise executions when using promise-based APIs is not feasible.
 * For long-running applications, using promise-based APIs by leveraging chained
 * `then()` calls is usually preferable.
 *
 * @param PromiseInterface[]  $promises
 * @param ?LoopInterface      $loop
 * @param ?float              $timeout [deprecated] (optional) maximum timeout in seconds or null=wait forever
 * @return array returns an array with whatever each promise resolves to
 * @throws Exception when ANY promise is rejected
 * @throws TimeoutException if the $timeout is given and triggers
 */
function awaitAll(array $promises, LoopInterface $loop = null, $timeout = null)
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
 * @return void
 */
function _cancelAllPromises(array $promises)
{
    foreach ($promises as $promise) {
        if ($promise instanceof PromiseInterface && ($promise instanceof CancellablePromiseInterface || !\interface_exists('React\Promise\CancellablePromiseInterface'))) {
            $promise->cancel();
        }
    }
}
