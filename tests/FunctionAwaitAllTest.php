<?php

use Clue\React\Block;

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
}
