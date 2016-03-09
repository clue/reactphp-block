<?php

use Clue\React\Block;
use React\Promise;
use React\Promise\Timer\TimeoutException;

class FunctionAwaitTest extends TestCase
{
    public function testAwaitOneRejected()
    {
        $promise = $this->createPromiseRejected(new Exception('test'));

        $this->setExpectedException('Exception', 'test');
        Block\await($promise, $this->loop);
    }

    public function testAwaitOneResolved()
    {
        $promise = $this->createPromiseResolved(2);

        $this->assertEquals(2, Block\await($promise, $this->loop));
    }

    public function testAwaitOneInterrupted()
    {
        $promise = $this->createPromiseResolved(2, 0.02);
        $this->createTimerInterrupt(0.01);

        $this->assertEquals(2, Block\await($promise, $this->loop));
    }

    public function testAwaitOncePendingWillThrowOnTimeout()
    {
        $promise = new Promise\Promise(function () { });

        $this->setExpectedException('React\Promise\Timer\TimeoutException');
        Block\await($promise, $this->loop, 0.001);
    }

    public function testAwaitOncePendingWillThrowAndCallCancellerOnTimeout()
    {
        $cancelled = false;
        $promise = new Promise\Promise(function () { }, function () use (&$cancelled) {
            $cancelled = true;
        });

        try {
            Block\await($promise, $this->loop, 0.001);
        } catch (TimeoutException $expected) {
            $this->assertTrue($cancelled);
        }
    }

    public function testAwaitOnceWithTimeoutWillResolvemmediatelyAndCleanUpTimeout()
    {
        $promise = Promise\resolve(true);

        $time = microtime(true);
        Block\await($promise, $this->loop, 5.0);
        $this->loop->run();
        $time = microtime(true) - $time;

        $this->assertLessThan(0.1, $time);
    }
}
