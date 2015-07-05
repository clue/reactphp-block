<?php

use Clue\React\Block;

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
}
