<?php

use \Linfo\Meta\Timer;

class LinfoTimerTest extends PHPUnit_Framework_TestCase
{
  /**
   * @test
   */
  public function runTimers()
  {
    $t1 = new Timer('test1');
    unset($t1);
    $t2 = new Timer('test2');
    unset($t2);
    $this->assertCount(2, Timer::getResults());
  }

  public function tearDown()
  {
    Timer::clear();
  }
}
