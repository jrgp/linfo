<?php

class OS_LinuxTest extends PHPUnit_Framework_TestCase {

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
    self::assertTrue(is_string(self::$parser->getKernel()));
  }

  /**
   * @test
   */
  public static function getHostname() {
    self::assertTrue(is_string(self::$parser->getHostname()));
  }

  /**
   * @test
   */
  public static function getCPUArchitecture() {
    self::assertTrue(is_string(self::$parser->getCPUArchitecture()));
  }

  /**
   * @test
   */
  public static function getTemps() {
    self::assertTrue(is_array(self::$parser->getTemps()));
  }

  /**
   * @test
   */
  public static function getRAID() {
    self::assertTrue(is_array(self::$parser->getRAID()));
  }

  /**
   * @test
   */
  public static function getDistro() {
    $distro = self::$parser->getDistro();
    self::assertTrue(is_array($distro));
    foreach (array('name', 'version') as $key)
      self::assertArrayHasKey($key, $distro);
  }

  /**
   * @test
   */
  public static function getNumLoggedIn() {
    self::assertTrue(is_int(self::$parser->getNumLoggedIn()));
  }

  /**
   * @test
   */
  public static function getCPUUsage() {
    self::assertTrue(is_float(self::$parser->getCPUUsage()));
  }

  /**
   * @test
   */
  public static function getSoundCards() {
    self::assertTrue(is_array(self::$parser->getSoundCards()));
  }

  /**
   * @test
   */
  public static function getMounts() {
    // TODO make sure each mount given has proper fields
    self::assertTrue(is_array(self::$parser->getMounts()));
  }

  /**
   * @test
   */
  public static function getNet() {
    $nics = self::$parser->getNet();
    self::assertTrue(is_array($nics));
    foreach ($nics as $nic) {
      foreach (array('sent', 'recieved', 'state', 'type') as $key) {
        self::assertArrayHasKey($key, $nic);
      }
      self::assertTrue(is_string($nic['state']));
      self::assertTrue(is_string($nic['type']));
      self::assertTrue(is_array($nic['sent']));
      self::assertTrue(is_array($nic['recieved']));
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
    self::assertTrue(is_array(self::$parser->getCPU()));
  }

  /**
   * @test
   */
  public static function getHD() {
    $drives = self::$parser->getHD();
    self::assertTrue(is_array($drives));
    foreach ($drives as $drive) {
      foreach (array('name', 'vendor', 'device', 'reads', 'writes', 'size', 'partitions') as $key) {
        self::assertArrayHasKey($key, $drive);
      }
      if (is_array($drive['partitions'])) {
        foreach ($drive['partitions'] as $partition) {
          self::assertArrayHasKey('size', $partition);
          self::assertArrayHasKey('number', $partition);
          self::assertTrue(is_int($partition['size']));
          self::assertTrue(is_numeric($partition['number']));
        }
      }
      self::assertTrue(is_int($drive['size']));
    }
  }

  /**
   * @test
   */
  public static function getUpTime() {
    self::assertTrue(is_string(self::$parser->getUpTime()));
  }

  /**
   * @test
   */
  public static function getLoad() {
    $load = self::$parser->getLoad();
    self::assertTrue(is_array($load));
    foreach (array('now', '5min', '15min') as $key)
      self::assertArrayHasKey($key, $load);
  }

  /**
   * @test
   */
  public static function getProcessStats() {
    $stats = self::$parser->getProcessStats();
    self::assertTrue(is_array($stats));
    foreach (array('totals', 'proc_total', 'threads') as $key) {
      self::assertArrayHasKey($key, $stats);
    }
    self::assertTrue(is_int($stats['proc_total']));
    self::assertTrue(is_int($stats['threads']));
    foreach (array('running', 'zombie', 'stopped', 'sleeping') as $key) {
      self::assertArrayHasKey($key, $stats['totals']);
      self::assertTrue(is_int($stats['totals'][$key]));
    }
  }

  /**
   * @test
   */
  public static function getRam() {
    $stats = self::$parser->getRam();
    self::assertTrue(is_array($stats));
    foreach (array('total', 'type', 'free', 'swapTotal', 'swapFree', 'swapInfo') as $key)
      self::assertArrayHasKey($key, $stats);
  }

}
