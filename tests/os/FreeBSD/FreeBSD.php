<?php

use \Linfo\Common;
use \Linfo\Linfo;

if (PHP_OS == 'FreeBSD') {
    class OS_FreeBSDTest extends PHPUnit_Framework_TestCase
    {
        protected static $parser;

        public static function setUpBeforeClass()
        {
            $linfo = new Linfo();
            self::$parser = $linfo->getParser();

            self::assertInstanceOf('\\Linfo\\OS\\FreeBSD', self::$parser);
        }

        public static function tearDownAfterClass()
        {
            self::$parser = null;
            Common::unconfig();
        }

  /**
   * @test
   */
  public static function getOS()
  {
      self::assertEquals('FreeBSD', self::$parser->getOS());
  }

  /**
   * @test
   */
  public static function getKernel()
  {
      self::assertInternalType('string', self::$parser->getKernel());
  }

  /**
   * @test
   */
  public static function getHostname()
  {
      self::assertInternalType('string', self::$parser->getHostname());
  }

  /**
   * @test
   */
  public static function getCPUArchitecture()
  {
      self::assertInternalType('string', self::$parser->getCPUArchitecture());
  }

  /**
   * @test
   */
  public static function getNet()
  {
      $nics = self::$parser->getNet();
      self::assertInternalType('array', $nics);
      foreach ($nics as $nic) {
          foreach (array('sent', 'recieved', 'state', 'type') as $key) {
              self::assertArrayHasKey($key, $nic);
          }
          self::assertInternalType('string', $nic['state']);
          self::assertInternalType('string', $nic['type']);
          self::assertInternalType('array', $nic['sent']);
          self::assertInternalType('array', $nic['recieved']);
          foreach (array('bytes', 'errors', 'packets') as $key) {
              self::assertArrayHasKey($key, $nic['sent']);
              self::assertArrayHasKey($key, $nic['recieved']);
              self::assertTrue(is_numeric($nic['sent'][$key]));
              self::assertTrue(is_numeric($nic['recieved'][$key]));
          }
      }
  }

  /**
   * @test
   */
  public static function getCPU()
  {
      $cpus = self::$parser->getCPU();
      self::assertInternalType('array', $cpus);
      foreach ($cpus as $cpu) {
          self::assertArrayHasKey('Model', $cpu);
          self::assertArrayHasKey('MHz', $cpu);
          self::assertInternalType('string', $cpu['Model']);
          self::assertInternalType('int', $cpu['MHz']);
      }
  }

  /**
   * @test
   */
  public static function getUpTime()
  {
      self::assertInternalType('array', self::$parser->getUpTime());
  }

  /**
   * @test
   */
  public static function getLoad()
  {
      $load = self::$parser->getLoad();
      self::assertInternalType('array', $load);
      foreach (array('now', '5min', '15min') as $key) {
          self::assertArrayHasKey($key, $load);
      }
  }

  /**
   * @test
   */
  public static function getProcessStats()
  {
      $stats = self::$parser->getProcessStats();
      self::assertInternalType('array', $stats);
      foreach (array('totals', 'proc_total') as $key) {
          self::assertArrayHasKey($key, $stats);
      }
      self::assertInternalType('int', $stats['proc_total']);
      foreach (array('running', 'zombie', 'stopped', 'sleeping', 'idle') as $key) {
          self::assertArrayHasKey($key, $stats['totals']);
          self::assertInternalType('int', $stats['totals'][$key]);
      }
  }

  /**
   * @test
   */
  public static function getRam()
  {
      $stats = self::$parser->getRam();
      self::assertInternalType('array', $stats);
      foreach (array('total', 'type', 'free', 'swapTotal', 'swapFree', 'swapInfo') as $key) {
          self::assertArrayHasKey($key, $stats);
      }
  }

  /**
   * @test
   */
  public static function getMounts()
  {
      $mounts = self::$parser->getMounts();
      self::assertInternalType('array', $mounts);
      foreach ($mounts as $mount) {
          foreach (array('device', 'mount', 'type', 'size', 'used', 'free', 'free_percent', 'used_percent') as $key) {
              self::assertArrayHasKey($key, $mount);
          }
          self::assertInternalType('string', $mount['device']);
          self::assertInternalType('string', $mount['mount']);
          self::assertInternalType('string', $mount['type']);
          self::assertInternalType('array', $mount['options']);
      }
  }

  /**
   * @test
   */
  public static function getDevs()
  {
      $devs = self::$parser->getDevs();
      self::assertInternalType('array', $devs);
      foreach ($devs as $dev) {
          foreach (array('vendor', 'device', 'type') as $key) {
              self::assertArrayHasKey($key, $dev);
              self::assertInternalType('string', $dev[$key]);
          }
      }
  }

  /**
   * @test
   */
  public static function getHD()
  {
      $drives = self::$parser->getHD();
      self::assertInternalType('array', $drives);
      foreach ($drives as $drive) {
          foreach (array('name', 'vendor', 'device') as $key) {
              self::assertArrayHasKey($key, $drive);
          }
      }
  }
    }
}
