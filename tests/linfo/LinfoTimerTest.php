<?php

class LinfoTimerTest extends PHPUnit_Framework_TestCase {

  /**
   * @test
   */
  public function Singleton() {
    $this->assertInstanceOf('LinfoTimer', LinfoTimer::Singleton());
  }

  /**
   * @test
   */
  public function runTimers() {
    $t1 = new LinfoTimerStart('test1');
    unset($t1);
    $t2 = new LinfoTimerStart('test2');
    unset($t2);
    $this->assertCount(2, LinfoTimer::Singleton()->getResults());
  }

  public function tearDown() {
    LinfoTimer::clear();
  }
}
