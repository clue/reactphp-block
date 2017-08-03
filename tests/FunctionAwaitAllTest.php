<?php

use Clue\React\Block;
use React\Promise;
use React\Promise\Timer\TimeoutException;

class FunctionAwaitAllTest extends TestCase
{
    public function testAwaitAllEmpty()
    {
        $this->assertEquals(array(), Block\awaitAll(array(), $this->loop));
    }

    public function testAwaitAllAllResolved()
    {
        $all = array(
            'first' => $this->createPromiseResolved(1),
            'second' => $this->createPromiseResolved(2)
        );

        $this->assertEquals(array('first' => 1, 'second' => 2), Block\awaitAll($all, $this->loop));
    }

    public function testAwaitAllRejected()
    {
        $all = array(
            $this->createPromiseResolved(1),
            $this->createPromiseRejected(new Exception('test'))
        );

        $this->setExpectedException('Exception', 'test');
        Block\awaitAll($all, $this->loop);
    }

    public function testAwaitAllRejectedWithFalseWillWrapInUnexpectedValueException()
    {
        $all = array(
            $this->createPromiseResolved(1),
            Promise\reject(false)
        );

        $this->setExpectedException('UnexpectedValueException');
        Block\awaitAll($all, $this->loop);
    }

    public function testAwaitAllOnlyRejected()
    {
        $all = array(
            $this->createPromiseRejected(new Exception('first')),
            $this->createPromiseRejected(new Exception('second'))
        );

        $this->setExpectedException('Exception', 'first');
        Block\awaitAll($all, $this->loop);
    }

    public function testAwaitAllInterrupted()
    {
        $promise = $this->createPromiseResolved(2, 0.02);
        $this->createTimerInterrupt(0.01);

        $this->assertEquals(array(2), Block\awaitAll(array($promise), $this->loop));
    }

    public function testAwaitAllWithRejectedWillCancelPending()
    {
        $cancelled = false;
        $promise = new Promise\Promise(function () { }, function () use (&$cancelled) {
            $cancelled = true;
        });

        $all = array(
            Promise\reject(new Exception('test')),
            $promise
        );

        try {
            Block\awaitAll($all, $this->loop);
            $this->fail();
        } catch (Exception $expected) {
            $this->assertEquals('test', $expected->getMessage());
            $this->assertTrue($cancelled);
        }
    }

    public function testAwaitAllPendingWillThrowAndCallCancellerOnTimeout()
    {
        $cancelled = false;
        $promise = new Promise\Promise(function () { }, function () use (&$cancelled) {
            $cancelled = true;
        });

        try {
            Block\awaitAll(array($promise), $this->loop, 0.001);
        } catch (TimeoutException $expected) {
            $this->assertTrue($cancelled);
        }
    }
}
