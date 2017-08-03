<?php

use Clue\React\Block;

class FunctionSleepTest extends TestCase
{
    public function testSleep()
    {
        $time = microtime(true);
        Block\sleep(0.2, $this->loop);
        $time = microtime(true) - $time;

        $this->assertEquals(0.2, $time, '', 0.1);
    }

    public function testSleepSmallTimerWillBeCappedReasonably()
    {
        $time = microtime(true);
        Block\sleep(0.0000001, $this->loop);
        $time = microtime(true) - $time;

        $this->assertEquals(0.1, $time, '', 0.1);
    }
}
