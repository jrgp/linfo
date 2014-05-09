<?php

class LinfoErrorTest extends PHPUnit_Framework_TestCase {

  /**
   * @test
   */
  public function Singleton() {
    $this->assertInstanceOf('LinfoError', LinfoError::Singleton());
  }

  /**
   * @test
   */
  public function add() {
    LinfoError::Singleton()->add('testing', 'testing 123');
    LinfoError::Singleton()->add('testing', 'testing 456');
    $this->assertCount(2, LinfoError::Singleton()->show());
  }

  public function tearDown() {
    LinfoError::clear();
  }
}
