<?php

use \Linfo\Meta\Errors;
use PHPUnit\Framework\TestCase;

class ErrorTest extends TestCase
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

  public function tearDown(): void
  {
    Errors::clear();
  }
}
