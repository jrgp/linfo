<?php

use \Linfo\Meta\Timer;
use PHPUnit\Framework\TestCase;

class LinfoTimerTest extends TestCase
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

  public function tearDown(): void
  {
    Timer::clear();
  }
}
