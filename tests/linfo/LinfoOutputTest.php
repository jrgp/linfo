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

    $p = xml_parser_create();
    $retcode = xml_parse_into_struct($p, $xml, $vals, $index);
    xml_parser_free($p);

    self::assertTrue($retcode === 1, 'Failed parsing generated XML');
  }
}
