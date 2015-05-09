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

        $onComplete = $this->getOnCompleteFn($resolution, $wait, array($promise), $timeout);
        $promise->then($onComplete, $onComplete);

        return $this->awaitResolution($wait, $resolution);
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

        $onComplete = $this->getOnCompleteFn($resolution, $wait, $promises, $timeout);

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

        return $this->awaitResolution($wait, $resolution);
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
        if (!count($promises)) {
            return array();
        }
        
        $wait = count($promises);
        $resolution = null;
        $values = array();

        $onComplete = $this->getOnCompleteFn($resolution, $wait, $promises, $timeout);

        foreach ($promises as $key => $promise) {
            /* @var $promise PromiseInterface */
            $promise->then(
                function ($value) use (&$wait, &$values, $key, $onComplete) {
                    $values[$key] = $value;

                    if ($wait == 1) {
                        $onComplete($values);
                    } elseif ($wait) {
                        --$wait;
                    }
                },
                $onComplete
            );
        }

        return $this->awaitResolution($wait, $resolution);
    }

    private function awaitResolution(&$wait, &$resolution)
    {
        while ($wait) {
            $this->loop->run();
        }

        if ($resolution instanceof Exception) {
            throw $resolution;
        }

        return $resolution;
    }

    private function getOnCompleteFn(&$resolution, &$wait, array $promises, $timeout)
    {
        $loop = $this->loop;

        $onComplete = function ($valueOrError) use (&$resolution, &$wait, $promises, $loop) {
            if (!$wait) {
                // only store first promise value
                return;
            }

            $resolution = $valueOrError;
            $wait = false;

            // cancel all remaining promises
            foreach ($promises as $promise) {
                if ($promise instanceof CancellablePromiseInterface) {
                    $promise->cancel();
                }
            }

            $loop->stop();
        };

        if ($timeout) {
            $onComplete = $this->applyTimeout($timeout, $onComplete);
        }

        return $onComplete;
    }

    private function applyTimeout($timeout, $onComplete)
    {
        $timer = $this->loop->addTimer($timeout, function () use ($onComplete) {
            $onComplete(new TimeoutException('Could not resolve in the allowed time'));
        });

        return function ($valueOrError) use ($timer, $onComplete) {
            $timer->cancel();
            $onComplete($valueOrError);
        };
    }
}
