<?php

namespace Clue\React\Block;

use React\EventLoop\LoopInterface;
use React\Promise\PromiseInterface;
use React\Promise\CancellablePromiseInterface;
use UnderflowException;
use Exception;

class Blocker
{
    private $loop;

    public function __construct(LoopInterface $loop)
    {
        $this->loop = $loop;
    }

    /**
     * wait/sleep for $time seconds
     *
     * @param float $time
     */
    public function wait($time)
    {
        $wait = true;
        $loop = $this->loop;
        $loop->addTimer($time, function () use ($loop, &$wait) {
            $loop->stop();
            $wait = false;
        });

        do {
            $loop->run();
        } while($wait);
    }

    /**
     * block waiting for the given $promise to resolve
     *
     * @param PromiseInterface $promise
     * @param double $timeout maximum time to wait in seconds
     * @return mixed returns whatever the promise resolves to
     * @throws Exception when the promise is rejected
     * @throws TimeoutException when the timeout is reached and the promise is not resolved
     */
    public function awaitOne(PromiseInterface $promise, $timeout = null)
    {
        $wait = true;
        $resolution = null;
        $loop = $this->loop;

        $onComplete = function ($valueOrError) use (&$resolution, &$wait, $loop) {
            $resolution = $valueOrError;
            $wait = false;
            $loop->stop();
        };

        if ($timeout) {
            $onComplete = $this->applyTimeout($loop, $timeout, $onComplete);
        }

        $promise->then($onComplete, $onComplete);

        while ($wait) {
            $loop->run();
        }

        if ($resolution instanceof Exception) {
            throw $resolution;
        }

        return $resolution;
    }

    /**
     * wait for ANY of the given promises to resolve
     *
     * Once the first promise is resolved, this will try to cancel() all
     * remaining promises and return whatever the first promise resolves to.
     *
     * If ALL promises fail to resolve, this will fail and throw an Exception.
     *
     * @param array $promises
     * @param double $timeout maximum time to wait in seconds
     * @return mixed returns whatever the first promise resolves to
     * @throws Exception if ALL promises are rejected
     * @throws TimeoutException if the timeout is reached and NO promise is resolved
     */
    public function awaitRace(array $promises, $timeout = null)
    {
        if (!count($promises)) {
            throw new UnderflowException('No promise could resolve');
        }

        $wait = count($promises);
        $resolution = null;
        $loop = $this->loop;

        $onComplete = function ($valueOrError) use (&$resolution, &$wait, $promises, $loop) {
            if (!$wait) {
                // only store first promise value
                return;
            }

            $resolution = $valueOrError;
            $wait = 0;

            // cancel all remaining promises
            foreach ($promises as $promise) {
                if ($promise instanceof CancellablePromiseInterface) {
                    $promise->cancel();
                }
            }

            $loop->stop();
        };

        if ($timeout) {
            $onComplete = $this->applyTimeout($loop, $timeout, $onComplete);
        }

        foreach ($promises as $promise) {
            /* @var $promise PromiseInterface */
            $promise->then(
                $onComplete,
                function ($e) use (&$wait, $onComplete) {
                    if ($wait == 1) {
                        $onComplete(new UnderflowException('No promise could resolve'));
                    } elseif ($wait) {
                        --$wait;
                    }
                }
            );
        }

        while ($wait) {
            $loop->run();
        }

        if ($resolution instanceof Exception) {
            throw $resolution;
        }

        return $resolution;
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
     * @param array $promises
     * @param double $timeout maximum time to wait in seconds
     * @return array returns an array with whatever each promise resolves to
     * @throws Exception when ANY promise is rejected
     * @throws TimeoutException if the timeout is reached and ANY promise is not resolved
     */
    public function awaitAll(array $promises, $timeout = null)
    {
        $wait = count($promises);
        $exception = null;
        $values = array();
        $loop = $this->loop;

        foreach ($promises as $key => $promise) {
            /* @var $promise PromiseInterface */
            $promise->then(
                function ($value) use (&$values, $key, &$wait, $loop) {
                    $values[$key] = $value;
                    --$wait;

                    if (!$wait) {
                        $loop->stop();
                    }
                },
                function ($e) use ($promises, &$exception, &$wait, $loop) {
                    if (!$wait) {
                        // cancelling promises will reject all remaining ones, only store first error
                        return;
                    }

                    $exception = $e;
                    $wait = 0;

                    // cancel all remaining promises
                    foreach ($promises as $promise) {
                        if ($promise instanceof CancellablePromiseInterface) {
                            $promise->cancel();
                        }
                    }

                    $loop->stop();
                }
            );
        }

        if ($timeout) {
            $loop->addTimer($timeout, function () use (&$exception, &$wait, $loop) {
                if (!$wait) {
                    return;
                }
                $exception = new TimeoutException('Not all promises could resolve in the allowed time');
                $wait = 0;
                $loop->stop();
            });
        }

        while ($wait) {
            $loop->run();
        }

        if ($exception !== null) {
            throw $exception;
        }

        return $values;
    }

    private function applyTimeout(LoopInterface $loop, $timeout, $onComplete)
    {
        $timer = $loop->addTimer($timeout, function () use ($onComplete) {
            $onComplete(new TimeoutException('Could not resolve in the allowed time'));
        });

        return function ($valueOrError) use ($timer, $onComplete) {
            $timer->cancel();
            $onComplete($valueOrError);
        };
    }
}
