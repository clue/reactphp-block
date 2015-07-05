<?php

class FunctionSleepTest extends TestCase
{
    public function testSleep()
    {
        $time = microtime(true);
        $this->block->sleep(0.2);
        $time = microtime(true) - $time;

        $this->assertEquals(0.2, $time, '', 0.1);
    }
}
