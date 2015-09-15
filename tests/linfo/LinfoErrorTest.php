<?php

use \Linfo\Meta\Errors;

class ErrorTest extends PHPUnit_Framework_TestCase {

  /**
   * @test
   */
  public function Singleton() {
    $this->assertInstanceOf('\\Linfo\\Meta\\Errors', Errors::Singleton());
  }

  /**
   * @test
   */
  public function add() {
    Errors::Singleton()->add('testing', 'testing 123');
    Errors::Singleton()->add('testing', 'testing 456');
    $this->assertCount(2, Errors::Singleton()->show());
  }

  public function tearDown() {
    Errors::clear();
  }
}
