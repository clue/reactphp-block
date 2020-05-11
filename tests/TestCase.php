<?php

namespace Clue\Tests\React\Block;

use PHPUnit\Framework\TestCase as BaseTestCase;
use React\Promise\Deferred;

class TestCase extends BaseTestCase
{
    protected $loop;

    public function setUp()
    {
        $this->loop = \React\EventLoop\Factory::create();
    }

    protected function createPromiseResolved($value = null, $delay = 0.01)
    {
        $deferred = new Deferred();

        $this->loop->addTimer($delay, function () use ($deferred, $value) {
            $deferred->resolve($value);
        });

        return $deferred->promise();
    }

    protected function createPromiseRejected($value = null, $delay = 0.01)
    {
        $deferred = new Deferred();

        $this->loop->addTimer($delay, function () use ($deferred, $value) {
            $deferred->reject($value);
        });

        return $deferred->promise();
    }

    protected function createTimerInterrupt($delay = 0.01)
    {
        $loop = $this->loop;
        $loop->addTimer($delay, function () use ($loop) {
            $loop->stop();
        });
    }
}
