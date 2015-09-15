<?php

use \Linfo\Common;
use \Linfo\Linfo;

if (PHP_OS == 'Linux') {

/*
 * Validate most of the regexes/file parsing, targeting custom known real
 * Linux files taken from the wild
 *
 * Also, thank FUCK for var_export()
 */
class LinuxGenericDistroTest extends PHPUnit_Framework_TestCase
{
    protected static $parser;

    public static function setUpBeforeClass()
    {
        Common::$path_prefix = dirname(dirname(__FILE__)).'/../files/linux/generic_distro';
        $linfo = new Linfo();
        self::$parser = $linfo->getParser();
    }

  /**
   * @test
   */
  public static function checkDistro()
  {
      self::assertEquals(array(
      'name' => 'Ubuntu',
      'version' => '14.04 (Trusty)',
    ), self::$parser->getDistro());
  }

  /**
   * @test
   */
  public static function checkLoad()
  {
      self::assertEquals(array(
      'now' => '0.23',
      '5min' => '0.29',
      '15min' => '0.31',
    ), self::$parser->getLoad());
  }

  /**
   * @test
   */
  public static function checkHostname()
  {
      self::assertEquals('machina.jrgp.us', self::$parser->getHostname());
  }

  /**
   * @test
   */
  public static function checkKernel()
  {
      self::assertEquals('3.17.3', self::$parser->getKernel());
  }

  /**
   * @test
   */
  public static function checkModel()
  {
      self::assertEquals('PowerEdge R610 (Dell Inc. 0K399H)', self::$parser->getModel());
  }

  /**
   * @test
   */
  public static function checkSoundCards()
  {
      self::assertEquals(array(
      0 => array(
        'number' => '0',
        'card' => 'HDA-Intel - HDA Intel HDMI',
      ),
      1 => array(
        'number' => '1',
        'card' => 'HDA-Intel - HDA Intel PCH',
      ),
    ), self::$parser->getSoundCards());
  }

  /**
   * @test
   */
  public static function checkCPU()
  {
      self::assertEquals(array(
      array(
        'Vendor' => 'GenuineIntel',
        'Model' => 'Intel(R) Pentium(R) CPU G3420 @ 3.20GHz',
        'MHz' => '1000.000',
      ),
      array(
        'Model' => 'Intel(R) Pentium(R) CPU G3420 @ 3.20GHz',
        'Vendor' => 'GenuineIntel',
        'MHz' => '3200.000',
      ),
    ), self::$parser->getCPU());
  }

  /**
   * @test
   */
  public static function checkMdadm()
  {
      self::assertEquals(array(0 => array(
        'device' => '/dev/md1',
        'status' => 'active',
        'level' => '1',
        'drives' => array(
          0 => array(
            'drive' => '/dev/sdb2',
            'state' => 'normal',
          ),
          1 => array(
            'drive' => '/dev/sdc2',
            'state' => 'normal',
          ),
        ),
        'size' => '931.02 GiB',
        'count' => '2/2',
        'chart' => 'UU',
      ),
      1 => array(
        'device' => '/dev/md0',
        'status' => 'active',
        'level' => '1',
        'drives' => array(
          0 => array(
            'drive' => '/dev/sdb1',
            'state' => 'normal',
          ),
          1 => array(
            'drive' => '/dev/sdc1',
            'state' => 'normal',
          ),
        ),
        'size' => '499.94 MiB',
        'count' => '2/2',
        'chart' => 'UU',
      ),
      2 => array(
        'device' => '/dev/md2',
        'status' => 'active',
        'level' => '10',
        'drives' => array(
          0 => array(
            'drive' => '/dev/sda2',
            'state' => 'normal',
          ),
          1 => array(
            'drive' => '/dev/sdc2',
            'state' => 'normal',
          ),
          2 => array(
            'drive' => '/dev/sdd2',
            'state' => 'normal',
          ),
          3 => array(
            'drive' => '/dev/sdb2',
            'state' => 'normal',
          ),
        ),
        'size' => '129.28 GiB',
        'count' => '4/4',
        'chart' => 'UUUU',
      ),
      3 => array(
        'device' => '/dev/md3',
        'status' => 'active',
        'level' => '6',
        'drives' => array(
          0 => array(
            'drive' => '/dev/sdh',
            'state' => 'normal',
          ),
          1 => array(
            'drive' => '/dev/sde',
            'state' => 'normal',
          ),
          2 => array(
            'drive' => '/dev/sdg',
            'state' => 'normal',
          ),
          3 => array(
            'drive' => '/dev/sdf',
            'state' => 'normal',
          ),
          4 => array(
            'drive' => '/dev/sdd',
            'state' => 'normal',
          ),
          5 => array(
            'drive' => '/dev/sdb',
            'state' => 'normal',
          ),
          6 => array(
            'drive' => '/dev/sdc',
            'state' => 'normal',
          ),
        ),
        'size' => '341.83 GiB',
        'count' => '7/7',
        'chart' => 'UUUUUUU',
      ),
    ), self::$parser->getRAID());
  }

    public static function tearDownAfterClass()
    {
        self::$parser = null;
        Common::unconfig();
        Common::$path_prefix = false;
    }
}
}
