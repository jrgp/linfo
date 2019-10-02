<?php

use \Linfo\Meta\Errors;

class ErrorTest extends PHPUnit_Framework_TestCase
{

  /**
   * @test
   */
  public function add()
  {
    Errors::add('testing', 'testing 123');
    Errors::add('testing', 'testing 456');
    $this->assertCount(2, Errors::show());
  }

  public function tearDown()
  {
    Errors::clear();
  }
}
