<?php

use \Linfo\Linfo;
use \Linfo\Common;

use PHPUnit\Framework\TestCase;

class LinfoTest extends TestCase
{
  protected static $linfo;

  public static function setUpBeforeClass(): void
  {
    self::$linfo = new Linfo();
  }

  public static function tearDownAfterClass(): void
  {
    self::$linfo = null;
    Common::unconfig();
  }

  /**
   * @test
   */
  public static function getLang()
  {
    self::assertIsArray(self::$linfo->getLang());
  }

  /**
   * @test
   */
  public static function getSettings()
  {
    self::assertIsArray(self::$linfo->getSettings());
  }

  /**
   * @test
   */
  public static function getAppName()
  {
    self::assertIsString(self::$linfo->getAppName());
  }

  /**
   * @test
   */
  public static function getVersion()
  {
    self::assertIsString(self::$linfo->getVersion());
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
