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
        $loop = $this->loop;
        $loop->addTimer($time, function () use ($loop) {
            $loop->stop();
        });
        $loop->run();
    }

    /**
     * block waiting for the given $promise to resolve
     *
     * @param PromiseInterface $promise
     * @return mixed returns whatever the promise resolves to
     * @throws Exception when the promise is rejected
     */
    public function awaitOne(PromiseInterface $promise)
    {
        $resolved = null;
        $exception = null;
        $loop = $this->loop;

        $promise->then(
            function ($c) use (&$resolved, $loop) {
                $resolved = $c;
                $loop->stop();
            },
            function ($error) use (&$exception, $loop) {
                $exception = $error;
                $loop->stop();
            }
        );

        $loop->run();

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
     * @param array $promises
     * @return mixed returns whatever the first promise resolves to
     * @throws Exception if ALL promises are rejected
     */
    public function awaitRace(array $promises)
    {
        $wait = count($promises);
        $value = null;
        $success = false;
        $loop = $this->loop;

        foreach ($promises as $key => $promise) {
            /* @var $promise PromiseInterface */
            $promise->then(
                function ($return) use (&$value, &$wait, &$success, $promises, $loop) {
                    if (!$wait) {
                        // only store first promise value
                        return;
                    }
                    $value = $return;
                    $wait = 0;
                    $success = true;

                    // cancel all remaining promises
                    foreach ($promises as $promise) {
                        if ($promise instanceof CancellablePromiseInterface) {
                            $promise->cancel();
                        }
                    }

                    $loop->stop();
                },
                function ($e) use (&$wait, $loop) {
                    if ($wait) {
                        // count number of promises to await
                        // cancelling promises will reject all remaining ones, ignore this
                        --$wait;

                        if (!$wait) {
                            $loop->stop();
                        }
                    }
                }
            );
        }

        $loop->run();

        if (!$success) {
            throw new UnderflowException('No promise could resolve');
        }

        return $value;
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
     * @return array returns an array with whatever each promise resolves to
     * @throws Exception when ANY promise is rejected
     */
    public function awaitAll(array $promises)
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

        $loop->run();

        if ($exception !== null) {
            throw $exception;
        }

        return $values;
    }
}
