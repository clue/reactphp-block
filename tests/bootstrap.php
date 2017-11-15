<?php

use PHPUnit\Framework\TestCase as BaseTestCase;
use React\Promise\Deferred;

require_once __DIR__ . '/../vendor/autoload.php';

error_reporting(-1);

class TestCase extends BaseTestCase
{
    protected $loop;

    public function setUp()
    {
        $this->loop = React\EventLoop\Factory::create();
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
