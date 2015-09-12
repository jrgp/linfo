<?php

if (PHP_OS == 'Darwin') {

class OS_DarwinTest extends PHPUnit_Framework_TestCase {

  protected static $parser;

  public static function setUpBeforeClass() {
    $linfo = new Linfo;
    self::$parser = $linfo->getParser();

    self::assertInstanceOf('OS_Darwin', self::$parser);
  }

  public static function tearDownAfterClass() {
    self::$parser = null;
    LinfoCommon::unconfig();
  }

  /**
   * @test
   */
  public static function getOS() {
    self::assertStringStartsWith('Darwin', self::$parser->getOS());
  }

  /**
   * @test
   */
  public static function getKernel() {
    self::assertInternalType('string', self::$parser->getKernel());
  }

  /**
   * @test
   */
  public static function getModel() {
    self::assertInternalType('string', self::$parser->getModel());
  }

  /**
   * @test
   */
  public static function getHostname() {
    self::assertInternalType('string', self::$parser->getHostname());
  }

  /**
   * @test
   */
  public static function getCPUArchitecture() {
    self::assertInternalType('string', self::$parser->getCPUArchitecture());
  }

  /**
   * @test
   */
  public static function getMounts() {
    $mounts = self::$parser->getMounts();
    self::assertInternalType('array', $mounts);
    foreach ($mounts as $mount) {
      foreach (array('device', 'mount', 'type', 'size', 'used', 'free', 'free_percent', 'used_percent') as $key) {
        self::assertArrayHasKey($key, $mount);
      }
      self::assertInternalType('string', $mount['device']);
      self::assertInternalType('string', $mount['mount']);
      self::assertInternalType('string', $mount['type']);
    }
  }

  /**
   * @test
   */
  public static function getNet() {
    $nics = self::$parser->getNet();
    self::assertInternalType('array', $nics);
    foreach ($nics as $nic) {
      foreach (array('sent', 'recieved', 'state', 'type') as $key) {
        self::assertArrayHasKey($key, $nic);
      }
      self::assertInternalType('string', $nic['state']);
      self::assertInternalType('string', $nic['type']);
      self::assertInternalType('array', $nic['sent']);
      self::assertInternalType('array', $nic['recieved']);
      foreach (array('bytes', 'errors', 'packets') as $key) {
        self::assertArrayHasKey($key, $nic['sent']);
        self::assertArrayHasKey($key, $nic['recieved']);
        self::assertTrue(is_numeric($nic['sent'][$key]));
        self::assertTrue(is_numeric($nic['recieved'][$key]));
      }
    }
  }

  /**
   * @test
   */
  public static function getCPU() {
    $cpus = self::$parser->getCPU();
    self::assertInternalType('array', $cpus);
    foreach ($cpus as $cpu) {
      self::assertArrayHasKey('Model', $cpu);
      self::assertArrayHasKey('MHz', $cpu);
      self::assertArrayHasKey('Vendor', $cpu);
      self::assertInternalType('string', $cpu['Model']);
      self::assertInternalType('int', $cpu['MHz']);
      self::assertInternalType('string', $cpu['Vendor']);
    }
  }

  /**
   * @test
   */
  public static function getBattery() {
    $batteries = self::$parser->getBattery();
    self::assertInternalType('array', $batteries);
    foreach ($batteries as $bat) {
      foreach (array('charge_full', 'charge_now', 'percentage', 'state') as $key) {
        self::assertArrayHasKey($key, $bat);
      }
    }
  }

  /**
   * @test
   */
  public static function getHD() {
    $drives = self::$parser->getHD();
    self::assertInternalType('array', $drives);
    foreach ($drives as $drive) {
      foreach (array('name', 'vendor', 'device', 'reads', 'writes', 'size', 'partitions') as $key) {
        self::assertArrayHasKey($key, $drive);
      }
      if (is_array($drive['partitions'])) {
        foreach ($drive['partitions'] as $partition) {
          self::assertArrayHasKey('size', $partition);
          self::assertTrue(is_numeric($partition['size']));
        }
      }
      self::assertTrue(is_numeric($drive['size']));
    }
  }

  /**
   * @test
   */
  public static function getUpTime() {
    self::assertInternalType('array', self::$parser->getUpTime());
  }

  /**
   * @test
   */
  public static function getLoad() {
    $load = self::$parser->getLoad();
    self::assertInternalType('array', $load);
    foreach (array('now', '5min', '15min') as $key)
      self::assertArrayHasKey($key, $load);
  }

  /**
   * @test
   */
  public static function getProcessStats() {
    $stats = self::$parser->getProcessStats();
    self::assertInternalType('array', $stats);
    foreach (array('totals', 'proc_total') as $key)
      self::assertArrayHasKey($key, $stats);
    self::assertInternalType('int', $stats['proc_total']);
    foreach (array('running', 'zombie', 'stopped', 'sleeping', 'idle') as $key) {
      self::assertArrayHasKey($key, $stats['totals']);
      self::assertInternalType('int', $stats['totals'][$key]);
    }
  }

  /**
   * @test
   */
  public static function getRam() {
    $stats = self::$parser->getRam();
    self::assertInternalType('array', $stats);
    foreach (array('total', 'type', 'free', 'swapTotal', 'swapFree', 'swapInfo') as $key)
      self::assertArrayHasKey($key, $stats);
  }
}


}
