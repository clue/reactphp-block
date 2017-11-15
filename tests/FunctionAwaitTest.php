<?php

use Clue\React\Block;
use React\Promise;
use React\Promise\Timer\TimeoutException;

class FunctionAwaitTest extends TestCase
{
    /**
     * @expectedException Exception
     * @expectedExceptionMessage test
     */
    public function testAwaitOneRejected()
    {
        $promise = $this->createPromiseRejected(new Exception('test'));

        Block\await($promise, $this->loop);
    }

    /**
     * @expectedException UnexpectedValueException
     */
    public function testAwaitOneRejectedWithFalseWillWrapInUnexpectedValueException()
    {
        $promise = Promise\reject(false);

        Block\await($promise, $this->loop);
    }

    /**
     * @expectedException UnexpectedValueException
     */
    public function testAwaitOneRejectedWithNullWillWrapInUnexpectedValueException()
    {
        $promise = Promise\reject(null);

        Block\await($promise, $this->loop);
    }

    /**
     * @requires PHP 7
     */
    public function testAwaitOneRejectedWithPhp7ErrorWillWrapInUnexpectedValueExceptionWithPrevious()
    {
        $promise = Promise\reject(new Error('Test'));

        try {
            Block\await($promise, $this->loop);
            $this->fail();
        } catch (UnexpectedValueException $e) {
            $this->assertInstanceOf('Throwable', $e->getPrevious());
            $this->assertEquals('Test', $e->getPrevious()->getMessage());
        }
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

    /**
     * @expectedException React\Promise\Timer\TimeoutException
     */
    public function testAwaitOncePendingWillThrowOnTimeout()
    {
        $promise = new Promise\Promise(function () { });

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
