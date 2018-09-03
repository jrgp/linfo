<?php

use \Linfo\Linfo;
use \Linfo\Common;

class LinfoTest extends PHPUnit_Framework_TestCase
{
  protected static $linfo;

  public static function setUpBeforeClass()
  {
    self::$linfo = new Linfo();
  }

  public static function tearDownAfterClass()
  {
    self::$linfo = null;
    Common::unconfig();
  }

  /**
   * @test
   */
  public static function getLang()
  {
    self::assertInternalType('array', self::$linfo->getLang());
  }

  /**
   * @test
   */
  public static function getSettings()
  {
    self::assertInternalType('array', self::$linfo->getSettings());
  }

  /**
   * @test
   */
  public static function getAppName()
  {
    self::assertInternalType('string', self::$linfo->getAppName());
  }

  /**
   * @test
   */
  public static function getVersion()
  {
    self::assertInternalType('string', self::$linfo->getVersion());
  }

  /**
   * @test
   */
  public static function getTimeStart()
  {
    self::assertTrue(is_float(self::$linfo->getTimeStart()));
  }

  /**
   * @test
   */
  public static function getParser()
  {
    self::assertInstanceOf('\\Linfo\\OS\\OS', self::$linfo->getParser());
  }
}
