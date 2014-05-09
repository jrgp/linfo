<?php

class LinfoCommonTest extends PHPUnit_Framework_TestCase {

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
}

