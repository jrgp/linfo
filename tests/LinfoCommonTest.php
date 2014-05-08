<?php

class LinfoCommonTest extends PHPUnit_Framework_TestCase {

  public function testArrayAppendString() {
    
    $strs = array('str1', 'str2', 'str3');
    $expected = array('str1_suffix', 'str2_suffix', 'str3_suffix');

    $this->assertEquals(LinfoCommon::arrayAppendString($strs, '_suffix'), $expected);
  }

  public function testxmlStringSanitize() {
    $this->assertEquals(LinfoCommon::xmlStringSanitize('te!@#$%^st'), 'te_st');
  }

  public function testAnyInArray() {
    $this->assertTrue(LinfoCommon::anyInArray(array(1, 2, 3, 4, 5), array(5, 6, 7)));
    $this->assertFalse(LinfoCommon::anyInArray(array(8, 9, 10), array(11, 12, 3)));
  }
}

