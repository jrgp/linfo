<?php

use \Linfo\Common;
use \Linfo\Linfo;
use \Linfo\Parsers\FileIO;

use PHPUnit\Framework\TestCase;

/*
 * Validate most of the regexes/file parsing, targeting custom known real
 * Linux files taken from the wild
 *
 * Also, thank FUCK for var_export()
 */

class LinuxGenericDistroTest extends TestCase
{
  protected static $parser;

  public static function setUpBeforeClass(): void
  {
    if (PHP_OS !== 'Linux') {
      self::markTestSkipped('Skip tests for Linux on other os');
    }

    $linfo = new Linfo();
    self::$parser = $linfo->getParser();

    $path_prefix = realpath(dirname(dirname(__FILE__)) . '/../files/linux/generic_distro');
    Common::$io = new FileIO($path_prefix);
  }

  /**
   * @test
   */
  public static function checkDistro()
  {
    self::assertEquals([
        'name' => 'Ubuntu',
        'version' => '14.04 (Trusty)',
    ], self::$parser->getDistro());
  }

  /**
   * @test
   */
  public static function checkLoad()
  {
    self::assertEquals([
        'now' => '0.23',
        '5min' => '0.29',
        '15min' => '0.31',
    ], self::$parser->getLoad());
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
    self::assertEquals([
        0 => [
            'number' => '0',
            'card' => 'HDA-Intel - HDA Intel HDMI',
        ],
        1 => [
            'number' => '1',
            'card' => 'HDA-Intel - HDA Intel PCH',
        ],
    ], self::$parser->getSoundCards());
  }

  /**
   * @test
   */
  public static function checkCPU()
  {
    self::assertEquals([
        [
            'Vendor' => 'GenuineIntel',
            'Model' => 'Intel(R) Pentium(R) CPU G3420 @ 3.20GHz',
            'MHz' => '1000.000',
        ],
        [
            'Model' => 'Intel(R) Pentium(R) CPU G3420 @ 3.20GHz',
            'Vendor' => 'GenuineIntel',
            'MHz' => '3200.000',
        ],
    ], self::$parser->getCPU());
  }

  /**
   * @test
   */
  public static function checkMdadm()
  {
    self::assertEquals([
        0 => [
            'device' => '/dev/md1',
            'status' => 'active',
            'level' => '1',
            'drives' => [
                0 => [
                    'drive' => '/dev/sdb2',
                    'state' => 'normal',
                ],
                1 => [
                    'drive' => '/dev/sdc2',
                    'state' => 'normal',
                ],
            ],
            'size' => '931.02 GiB',
            'count' => '2/2',
            'chart' => 'UU',
        ],
        1 => [
            'device' => '/dev/md0',
            'status' => 'active',
            'level' => '1',
            'drives' => [
                0 => [
                    'drive' => '/dev/sdb1',
                    'state' => 'normal',
                ],
                1 => [
                    'drive' => '/dev/sdc1',
                    'state' => 'normal',
                ],
            ],
            'size' => '499.94 MiB',
            'count' => '2/2',
            'chart' => 'UU',
        ],
        2 => [
            'device' => '/dev/md2',
            'status' => 'active',
            'level' => '10',
            'drives' => [
                0 => [
                    'drive' => '/dev/sda2',
                    'state' => 'normal',
                ],
                1 => [
                    'drive' => '/dev/sdc2',
                    'state' => 'normal',
                ],
                2 => [
                    'drive' => '/dev/sdd2',
                    'state' => 'normal',
                ],
                3 => [
                    'drive' => '/dev/sdb2',
                    'state' => 'normal',
                ],
            ],
            'size' => '129.28 GiB',
            'count' => '4/4',
            'chart' => 'UUUU',
        ],
        3 => [
            'device' => '/dev/md3',
            'status' => 'active',
            'level' => '6',
            'drives' => [
                0 => [
                    'drive' => '/dev/sdh',
                    'state' => 'normal',
                ],
                1 => [
                    'drive' => '/dev/sde',
                    'state' => 'normal',
                ],
                2 => [
                    'drive' => '/dev/sdg',
                    'state' => 'normal',
                ],
                3 => [
                    'drive' => '/dev/sdf',
                    'state' => 'normal',
                ],
                4 => [
                    'drive' => '/dev/sdd',
                    'state' => 'normal',
                ],
                5 => [
                    'drive' => '/dev/sdb',
                    'state' => 'normal',
                ],
                6 => [
                    'drive' => '/dev/sdc',
                    'state' => 'normal',
                ],
            ],
            'size' => '341.83 GiB',
            'count' => '7/7',
            'chart' => 'UUUUUUU',
        ],
    ], self::$parser->getRAID());
  }

  public static function tearDownAfterClass(): void
  {
    self::$parser = null;
    Common::unconfig();
    Common::$io = new FileIO(false);
  }
}
