<?php

use \Linfo\Leveldict;

use PHPUnit\Framework\TestCase;

class LeveldictTest extends TestCase
{
  /**
   * @test
   */
  public function levelDict()
  {

    $d = new Leveldict;
    $d->set(['1', '2', '3'], 'foo');
    $result = $d->todict();
    $this->assertEquals($result['1']['2']['3'], 'foo');
    $this->assertEquals($d->get(['1', '2', '3']), 'foo');
    $this->assertEquals($d->get(['not', 'found']), null);
  }
}
