<?php

namespace Clue\Tests\React\Block;

use PHPUnit\Framework\TestCase as BaseTestCase;
use React\EventLoop\Loop;
use React\Promise\Deferred;

class TestCase extends BaseTestCase
{
    protected $loop;

    /**
     * @before
     */
    public function setUpLoop()
    {
        $this->loop = Loop::get();
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

    public function setExpectedException($exception, $exceptionMessage = '', $exceptionCode = null)
    {
        if (method_exists($this, 'expectException')) {
            // PHPUnit 5+
            $this->expectException($exception);
            if ($exceptionMessage !== '') {
                $this->expectExceptionMessage($exceptionMessage);
            }
            if ($exceptionCode !== null) {
                $this->expectExceptionCode($exceptionCode);
            }
        } else {
            // legacy PHPUnit 4
            parent::setExpectedException($exception, $exceptionMessage, $exceptionCode);
        }
    }

    public function assertEqualsDelta($expected, $actual, $delta)
    {
        if (method_exists($this, 'assertEqualsWithDelta')) {
            // PHPUnit 7.5+
            $this->assertEqualsWithDelta($expected, $actual, $delta);
        } else {
            // legacy PHPUnit 4 - PHPUnit 7.5
            $this->assertEquals($expected, $actual, '', $delta);
        }
    }
}
