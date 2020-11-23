<?php

use \Linfo\Parsers\Hwpci;

use PHPUnit\Framework\TestCase;

class HwpciTest extends TestCase
{
  /**
   * @test
   */
  public function parse_pci()
  {
    $files = dirname(dirname(dirname(__file__))).'/files';
    $h = new Hwpci('', '', '', false);
    $resolved = $h->resolve_ids($files.'/pci.ids', ['10de' => true, '1d6a' => true], ['10de1b82' => 1, '1d6a07b1' => 1]);

    $this->assertEquals($resolved, [
        '10de1b82' => ['NVIDIA Corporation', 'GP104 [GeForce GTX 1070 Ti]'],
        '1d6a07b1' => ['Aquantia Corp.', 'AQC107 NBase-T/IEEE 802.3bz Ethernet Controller [AQtion]']
    ]);
  }

  /**
   * @test
   */
  public function parse_usb()
  {
    $files = dirname(dirname(dirname(__file__))).'/files';
    $h = new Hwpci('', '', '', false);
    $resolved = $h->resolve_ids($files.'/usb.ids', ['05ac' => true, '046d' => true], ['05ac1006' => 1, '046dc069' => 1]);

    $this->assertEquals($resolved, [
        '05ac1006' => ['Apple, Inc.', 'Hub in Aluminum Keyboard'],
        '046dc069' => ['Logitech, Inc.', 'M-U0007 [Corded Mouse M500]']
    ]);
  }

  /**
   * @test
   */
  public function assumption()
  {
    // python does not like this but php does not care?
    $arr = [];
    $arr['foo']++;
    $arr['foo']++;
    $this->assertEquals($arr, ['foo' => 2]);
  }

}
