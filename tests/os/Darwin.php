<?php

class OS_DarwinTest extends PHPUnit_Framework_TestCase {

  protected static $parser;

  public static function setUpBeforeClass() {
    $linfo = new Linfo;
    self::$parser = $linfo->getParser();
  }

  public static function tearDownAfterClass() {
    self::$parser = null;
    LinfoCommon::unconfig();
  }

  /**
   * @test
   */
  public static function verifyOS() {
    self::assertInstanceOf('OS_Darwin', self::$parser);
  }

  /**
   * @test
   */
  public static function getOS() {
    list($os) = explode(' ',self::$parser->getOS());
    self::assertEquals('Darwin', $os);
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
  public static function getModel() {
    self::assertTrue(is_string(self::$parser->getModel()));
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
  public static function getMounts() {
    // TODO make sure each mount given has proper fields
    self::assertTrue(is_array(self::$parser->getMounts()));
  }

  /**
   * @test
   */
  public static function getNet() {
    // TODO make sure each nic given has proper fields
    self::assertTrue(is_array(self::$parser->getNet()));
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
  public static function getBattery() {
    self::assertTrue(is_array(self::$parser->getBattery()));
  }

  /**
   * @test
   */
  public static function getHD() {
    self::assertTrue(is_array(self::$parser->getHD()));
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
    foreach (array('totals', 'proc_total') as $key)
      self::assertArrayHasKey($key, $stats);
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
