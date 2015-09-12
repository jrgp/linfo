<?php

if (PHP_OS == 'Linux') {

/*
 * Primarily validate return types
 */
class LinuxTest extends PHPUnit_Framework_TestCase {

  protected static $parser;

  public static function setUpBeforeClass() {
    $linfo = new Linfo;
    self::$parser = $linfo->getParser();

    self::assertInstanceOf('OS_Linux', self::$parser);

    self::$parser->determineCPUPercentage();
  }

  public static function tearDownAfterClass() {
    self::$parser = null;
    LinfoCommon::unconfig();
  }

  /**
   * @test
   */
  public static function getOS() {
    self::assertEquals('Linux', self::$parser->getOS());
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
  public static function getTemps() {
    self::assertInternalType('array', self::$parser->getTemps());
  }

  /**
   * @test
   */
  public static function getRAID() {
    self::assertInternalType('array', self::$parser->getRAID());
  }

  /**
   * @test
   */
  public static function getDistro() {
    $distro = self::$parser->getDistro();
    self::assertInternalType('array', $distro);
    foreach (array('name', 'version') as $key)
      self::assertArrayHasKey($key, $distro);
  }

  /**
   * @test
   */
  public static function getNumLoggedIn() {
    self::assertInternalType('int', self::$parser->getNumLoggedIn());
  }

  /**
   * @test
   */
  public static function getCPUUsage() {
    self::assertInternalType('float', self::$parser->getCPUUsage());
  }

  /**
   * @test
   */
  public static function getSoundCards() {
    $cards = self::$parser->getSoundCards();
    self::assertInternalType('array', $cards);
    foreach ($cards as $card) {
      self::assertArrayHasKey('card', $card);
      self::assertArrayHasKey('number', $card);
    }
  }

  /**
   * @test
   */
  public static function getMounts() {
    $mounts = self::$parser->getMounts();
    self::assertInternalType('array', $mounts);
    foreach ($mounts as $mount) {
      foreach (array('device', 'mount', 'type', 'size', 'used', 'free', 'free_percent', 'used_percent', 'options') as $key) {
        self::assertArrayHasKey($key, $mount);
      }
      self::assertInternalType('string', $mount['device']);
      self::assertInternalType('string', $mount['mount']);
      self::assertInternalType('string', $mount['type']);
      self::assertInternalType('array', $mount['options']);
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
    // TODO: flesh this out
    self::assertInternalType('array', self::$parser->getCPU());
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
          self::assertArrayHasKey('number', $partition);
          self::assertInternalType('int', $partition['size']);
          self::assertTrue(is_numeric($partition['number']));
        }
      }
      self::assertInternalType('int', $drive['size']);
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
    foreach (array('totals', 'proc_total', 'threads') as $key) {
      self::assertArrayHasKey($key, $stats);
    }
    self::assertInternalType('int', $stats['proc_total']);
    self::assertInternalType('int', $stats['threads']);
    foreach (array('running', 'zombie', 'stopped', 'sleeping') as $key) {
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
