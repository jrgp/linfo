<?php

class LinfoOutTest extends PHPUnit_Framework_TestCase {

  protected static $output;

  public static function setUpBeforeClass() {
    $linfo = new Linfo;
    $linfo->scan();
    self::$output = new LinfoOutput($linfo);
  }

  public static function tearDownAfterClass() {
    self::$output = null;
    LinfoCommon::unconfig();
  }

  /**
   * @test
   */
  public function jsonOut() {

    if (isset($_GET['callback']))
      unset($_GET['callback']);

    ob_start();
    self::$output->jsonOut();
    $json = ob_get_clean();

    self::assertTrue(json_decode($json) !== false, 'Failed parsing generated json');
  }

  /**
   * @test
   */
  public function xmlOut() {

    ob_start();
    self::$output->xmlOut();
    $xml = ob_get_clean();

    self::assertTrue(simplexml_load_string($xml) !== false, 'Failed parsing generated XML');
  }
}
