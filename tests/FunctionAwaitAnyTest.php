<?php

use Clue\React\Block;
use React\Promise\Deferred;

class FunctionAwaitAnyTest extends TestCase
{
    /**
     * @expectedException UnderflowException
     */
    public function testAwaitAnyEmpty()
    {
        Block\awaitAny(array(), $this->loop);
    }

    public function testAwaitAnyFirstResolved()
    {
        $all = array(
            $this->createPromiseRejected(1),
            $this->createPromiseResolved(2, 0.01),
            $this->createPromiseResolved(3, 0.02)
        );

        $this->assertEquals(2, Block\awaitAny($all, $this->loop));
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

        $this->assertEquals(2, Block\awaitAny($all, $this->loop));
    }

    public function testAwaitAnyAllRejected()
    {
        $all = array(
            $this->createPromiseRejected(1),
            $this->createPromiseRejected(2)
        );

        $this->setExpectedException('UnderflowException');
        Block\awaitAny($all, $this->loop);
    }

    public function testAwaitAnyInterrupted()
    {
        $promise = $this->createPromiseResolved(2, 0.02);
        $this->createTimerInterrupt(0.01);

        $this->assertEquals(2, Block\awaitAny(array($promise), $this->loop));
    }
}
