<?php

use Clue\React\Block\Blocker;
use React\Promise\Deferred;

class BlockerTest extends TestCase
{
    private $loop;
    private $block;

    public function setUp()
    {
        $this->loop = React\EventLoop\Factory::create();
        $this->block = new Blocker($this->loop);
    }

    public function testWait()
    {
        $time = microtime(true);
        $this->block->wait(0.2);
        $time = microtime(true) - $time;

        $this->assertEquals(0.2, $time, '', 0.1);
    }

    public function testAwaitOneRejected()
    {
        $promise = $this->createPromiseRejected(new Exception('test'));

        $this->setExpectedException('Exception', 'test');
        $this->block->awaitOne($promise);
    }

    public function testAwaitOneResolved()
    {
        $promise = $this->createPromiseResolved(2);

        $this->assertEquals(2, $this->block->awaitOne($promise));
    }

    public function testAwaitOneInterrupted()
    {
        $promise = $this->createPromiseResolved(2, 0.02);
        $this->createTimerInterrupt(0.01);

        $this->assertEquals(2, $this->block->awaitOne($promise));
    }

    /**
     * @expectedException UnderflowException
     */
    public function testAwaitAnyEmpty()
    {
        $this->block->awaitAny(array());
    }

    public function testAwaitAnyFirstResolved()
    {
        $all = array(
            $this->createPromiseRejected(1),
            $this->createPromiseResolved(2, 0.01),
            $this->createPromiseResolved(3, 0.02)
        );

        $this->assertEquals(2, $this->block->awaitAny($all));
    }

    public function testAwaitAnyFirstResolvedConcurrently()
    {
        $d1 = new Deferred();
        $d2 = new Deferred();
        $d3 = new Deferred();

        $this->loop->addTimer(0.01, function() use ($d1, $d2, $d3) {
            $d1->reject(1);
            $d2->resolve(2);
            $d3->resolve(3);
        });

        $all = array(
            $d1->promise(),
            $d2->promise(),
            $d3->promise()
        );

        $this->assertEquals(2, $this->block->awaitAny($all));
    }

    public function testAwaitAnyAllRejected()
    {
        $all = array(
            $this->createPromiseRejected(1),
            $this->createPromiseRejected(2)
        );

        $this->setExpectedException('UnderflowException');
        $this->block->awaitAny($all);
    }

    public function testAwaitAnyInterrupted()
    {
        $promise = $this->createPromiseResolved(2, 0.02);
        $this->createTimerInterrupt(0.01);

        $this->assertEquals(2, $this->block->awaitAny(array($promise)));
    }

    public function testAwaitAllEmpty()
    {
        $this->assertEquals(array(), $this->block->awaitAll(array()));
    }

    public function testAwaitAllAllResolved()
    {
        $all = array(
            'first' => $this->createPromiseResolved(1),
            'second' => $this->createPromiseResolved(2)
        );

        $this->assertEquals(array('first' => 1, 'second' => 2), $this->block->awaitAll($all));
    }

    public function testAwaitAllRejected()
    {
        $all = array(
            $this->createPromiseResolved(1),
            $this->createPromiseRejected(new Exception('test'))
        );

        $this->setExpectedException('Exception', 'test');
        $this->block->awaitAll($all);
    }

    public function testAwaitAllOnlyRejected()
    {
        $all = array(
            $this->createPromiseRejected(new Exception('first')),
            $this->createPromiseRejected(new Exception('second'))
        );

        $this->setExpectedException('Exception', 'first');
        $this->block->awaitAll($all);
    }

    public function testAwaitAllInterrupted()
    {
        $promise = $this->createPromiseResolved(2, 0.02);
        $this->createTimerInterrupt(0.01);

        $this->assertEquals(array(2), $this->block->awaitAll(array($promise)));
    }

    private function createPromiseResolved($value = null, $delay = 0.01)
    {
        $deferred = new Deferred();

        $this->loop->addTimer($delay, function () use ($deferred, $value) {
            $deferred->resolve($value);
        });

        return $deferred->promise();
    }

    private function createPromiseRejected($value = null, $delay = 0.01)
    {
        $deferred = new Deferred();

        $this->loop->addTimer($delay, function () use ($deferred, $value) {
            $deferred->reject($value);
        });

        return $deferred->promise();
    }

    private function createTimerInterrupt($delay = 0.01)
    {
        $loop = $this->loop;
        $loop->addTimer($delay, function () use ($loop) {
            $loop->stop();
        });
    }
}
