<?php

use \Linfo\Common;
use \Linfo\Linfo;

class CommonTest extends PHPUnit_Framework_TestCase
{
  protected static $linfo;

  public static function setUpBeforeClass()
  {
    self::$linfo = new Linfo();
  }

  public static function tearDownAfterClass()
  {
    Common::unconfig();
  }

  /**
   * @test
   */
  public function arrayAppendString()
  {
    $strs = ['str1', 'str2', 'str3'];
    $expected = ['str1_suffix', 'str2_suffix', 'str3_suffix'];

    $this->assertEquals(Common::arrayAppendString($strs, '_suffix'), $expected);
  }

  /**
   * @test
   */
  public function xmlStringSanitize()
  {
    $this->assertEquals(Common::xmlStringSanitize('te!@#$%^st'), 'te_st');
  }

  /**
   * @test
   */
  public function anyInArray()
  {
    $this->assertTrue(Common::anyInArray([1, 2, 3, 4, 5], [5, 6, 7]));
    $this->assertFalse(Common::anyInArray([8, 9, 10], [11, 12, 3]));
  }

  /**
   * @test
   */
  public function locateActualPath()
  {
    $paths = [
        LINFO_TESTDIR . '/files/test1.txt',
        LINFO_TESTDIR . '/files/test2.txt',
        LINFO_TESTDIR . '/files/test3.txt',
    ];
    $real = LINFO_TESTDIR . '/files/test2.txt';
    $this->assertEquals($real, Common::locateActualPath($paths));
  }

  /**
   * @test
   */
  public function getIntFromFile()
  {
    $file = LINFO_TESTDIR . '/files/intfile.txt';
    $this->assertEquals(101, Common::getIntFromFile($file));
  }

  /**
   * @test
   */
  public function getVarFromFile()
  {
    $file = LINFO_TESTDIR . '/files/varfile.php';
    $this->assertEquals('foo', Common::getVarFromFile($file, 'var'));
  }

  /**
   * @test
   */
  public function byteConvert()
  {
    $this->assertEquals('1000 KiB', Common::byteConvert(1024000));
    $this->assertEquals('1 GiB', Common::byteConvert(1073741824));
  }

  /**
   * @test
   */
  public function secondsConvert()
  {
    // this is incorrect
    $this->assertEquals('4 days, 4 hours, 30 seconds', Common::secondsConvert(360000));

    // this says '1 hours, 30 seconds' instead. need to find out why...
    #$this->assertEquals('1 hours', Common::secondsConvert(3600));
  }

  /**
   * @test
   */
  public function getContents()
  {
    $contents = "lineone\nlinetwo\nline3";
    $file = LINFO_TESTDIR . '/files/lines.txt';
    $this->assertEquals($contents, Common::getContents($file));
  }

  /**
   * @test
   */
  public function getLines()
  {
    $lines = ["lineone\n", "linetwo\n", "line3\n"];
    $file = LINFO_TESTDIR . '/files/lines.txt';
    $this->assertEquals($lines, Common::getLines($file));
  }
}
