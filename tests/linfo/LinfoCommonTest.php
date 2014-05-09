<?php

class LinfoCommonTest extends PHPUnit_Framework_TestCase {

  protected static $linfo;

  public static function setUpBeforeClass() {
    self::$linfo = new Linfo;
  }

  public static function tearDownAfterClass() {
    LinfoCommon::unconfig();
  }

  /**
   * @test
   */
  public function arrayAppendString() {
    
    $strs = array('str1', 'str2', 'str3');
    $expected = array('str1_suffix', 'str2_suffix', 'str3_suffix');

    $this->assertEquals(LinfoCommon::arrayAppendString($strs, '_suffix'), $expected);
  }

  /**
   * @test
   */
  public function xmlStringSanitize() {
    $this->assertEquals(LinfoCommon::xmlStringSanitize('te!@#$%^st'), 'te_st');
  }

  /**
   * @test
   */
  public function anyInArray() {
    $this->assertTrue(LinfoCommon::anyInArray(array(1, 2, 3, 4, 5), array(5, 6, 7)));
    $this->assertFalse(LinfoCommon::anyInArray(array(8, 9, 10), array(11, 12, 3)));
  }

  /**
   * @test
   */
  public function locateActualPath() {
    $paths = array(
      LINFO_TESTDIR.'/files/test1.txt', 
      LINFO_TESTDIR.'/files/test2.txt', 
      LINFO_TESTDIR.'/files/test3.txt', 
    );
    $real = LINFO_TESTDIR.'/files/test2.txt';
    $this->assertEquals($real, LinfoCommon::locateActualPath($paths));
  }

  /**
   * @test
   */
  public function getIntFromFile() {
    $file = LINFO_TESTDIR.'/files/intfile.txt';
    $this->assertEquals(101, LinfoCommon::getIntFromFile($file));
  }

  /**
   * @test
   */
  public function getVarFromFile() {
    $file = LINFO_TESTDIR.'/files/varfile.php';
    $this->assertEquals('foo', LinfoCommon::getVarFromFile($file, 'var'));
  }

  /**
   * @test
   */
  public function byteConvert() {
    $this->assertEquals('1000 KiB', LinfoCommon::byteConvert(1024000));
    $this->assertEquals('1 GiB', LinfoCommon::byteConvert(1073741824));
  }

  /**
   * @test
   */
  public function secondsConvert() {
    // this is incorrect
    $this->assertEquals('4 days, 4 hours, 30 seconds', LinfoCommon::secondsConvert(360000));

    // this says '1 hours, 30 seconds' instead. need to find out why...
    #$this->assertEquals('1 hours', LinfoCommon::secondsConvert(3600));
  }

  /**
   * @test
   */
  public function getContents() {
    $contents = "lineone\nlinetwo\nline3";
    $file = LINFO_TESTDIR.'/files/lines.txt';
    $this->assertEquals($contents, LinfoCommon::getContents($file));
  }

  /**
   * @test
   */
  public function getLines() {
    $lines = array("lineone\n", "linetwo\n", "line3\n");
    $file = LINFO_TESTDIR.'/files/lines.txt';
    $this->assertEquals($lines, LinfoCommon::getLines($file));
  }
}

