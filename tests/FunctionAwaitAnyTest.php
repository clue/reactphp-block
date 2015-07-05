<?php

use React\Promise\Deferred;

class FunctionAwaitAnyTest extends TestCase
{
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
}
