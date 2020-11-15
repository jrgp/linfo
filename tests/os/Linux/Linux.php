<?php

use \Linfo\Common;
use \Linfo\Linfo;
use PHPUnit\Framework\TestCase;


/*
 * Primarily validate return types
 */
class LinuxTest extends TestCase
{
  protected static $parser;

  public static function setUpBeforeClass(): void
  {
    if (PHP_OS !== 'Linux') {
      self::markTestSkipped('Skip tests for Linux on other os');
    }

    $linfo = new Linfo();
    self::$parser = $linfo->getParser();

    self::assertInstanceOf('\\Linfo\\OS\\Linux', self::$parser);

    self::$parser->determineCPUPercentage();
  }

  public static function tearDownAfterClass(): void
  {
    self::$parser = null;
    Common::unconfig();
  }

  /**
   * @test
   */
  public static function getOS()
  {
    self::assertEquals('Linux', self::$parser->getOS());
  }

  /**
   * @test
   */
  public static function getKernel()
  {
    self::assertIsString(self::$parser->getKernel());
  }

  /**
   * @test
   */
  public static function getHostname()
  {
    self::assertIsString(self::$parser->getHostname());
  }

  /**
   * @test
   */
  public static function getCPUArchitecture()
  {
    self::assertIsString(self::$parser->getCPUArchitecture());
  }

  /**
   * @test
   */
  public static function getTemps()
  {
    self::assertIsArray(self::$parser->getTemps());
  }

  /**
   * @test
   */
  public static function getRAID()
  {
    self::assertIsArray(self::$parser->getRAID());
  }

  /**
   * @test
   */
  public static function getDistro()
  {
    $distro = self::$parser->getDistro();
    self::assertIsArray($distro);
    foreach (['name', 'version'] as $key) {
      self::assertArrayHasKey($key, $distro);
    }
  }

  /**
   * @test
   */
  public static function getNumLoggedIn()
  {
    self::assertIsInt(self::$parser->getNumLoggedIn());
  }

  /**
   * @test
   */
  public static function getCPUUsage()
  {
    self::assertIsFloat(self::$parser->getCPUUsage());
  }

  /**
   * @test
   */
  public static function getSoundCards()
  {
    $cards = self::$parser->getSoundCards();
    self::assertIsArray($cards);
    foreach ($cards as $card) {
      self::assertArrayHasKey('card', $card);
      self::assertArrayHasKey('number', $card);
    }
  }

  /**
   * @test
   */
  public static function getMounts()
  {
    $mounts = self::$parser->getMounts();
    self::assertIsArray($mounts);
    foreach ($mounts as $mount) {
      foreach ([
                   'device',
                   'mount',
                   'type',
                   'size',
                   'used',
                   'free',
                   'free_percent',
                   'used_percent',
                   'options'
               ] as $key) {
        self::assertArrayHasKey($key, $mount);
      }
      self::assertIsString($mount['device']);
      self::assertIsString($mount['mount']);
      self::assertIsString($mount['type']);
      self::assertIsArray($mount['options']);
    }
  }

  /**
   * @test
   */
  public static function getNet()
  {
    $nics = self::$parser->getNet();
    self::assertIsArray($nics);
    foreach ($nics as $nic) {
      foreach (['sent', 'recieved', 'state', 'type'] as $key) {
        self::assertArrayHasKey($key, $nic);
      }
      self::assertIsString($nic['state']);
      self::assertIsString($nic['type']);
      self::assertIsArray($nic['sent']);
      self::assertIsArray($nic['recieved']);
      foreach (['bytes', 'errors', 'packets'] as $key) {
        self::assertArrayHasKey($key, $nic['sent']);
        self::assertArrayHasKey($key, $nic['recieved']);
        self::assertIsNumeric($nic['sent'][$key]);
        self::assertIsNumeric($nic['recieved'][$key]);
      }
    }
  }

  /**
   * @test
   */
  public static function getCPU()
  {
    // TODO: flesh this out
    self::assertIsArray(self::$parser->getCPU());
  }

  /**
   * @test
   */
  public static function getHD()
  {
    $drives = self::$parser->getHD();
    self::assertIsArray($drives);
    foreach ($drives as $drive) {
      foreach (['name', 'vendor', 'device', 'reads', 'writes', 'size', 'partitions'] as $key) {
        self::assertArrayHasKey($key, $drive);
      }
      if (is_array($drive['partitions'])) {
        foreach ($drive['partitions'] as $partition) {
          self::assertArrayHasKey('size', $partition);
          self::assertArrayHasKey('number', $partition);
          self::assertIsInt($partition['size']);
       }
      }
      self::assertIsInt($drive['size']);
    }
  }

  /**
   * @test
   */
  public static function getUpTime()
  {
    self::assertIsArray(self::$parser->getUpTime());
  }

  /**
   * @test
   */
  public static function getLoad()
  {
    $load = self::$parser->getLoad();
    self::assertIsArray($load);
    foreach (['now', '5min', '15min'] as $key) {
      self::assertArrayHasKey($key, $load);
    }
  }

  /**
   * @test
   */
  public static function getProcessStats()
  {
    $stats = self::$parser->getProcessStats();
    self::assertIsArray($stats);
    foreach (['totals', 'proc_total', 'threads'] as $key) {
      self::assertArrayHasKey($key, $stats);
    }
    self::assertIsInt($stats['proc_total']);
    self::assertIsInt($stats['threads']);
    foreach (['running', 'zombie', 'stopped', 'sleeping'] as $key) {
      self::assertArrayHasKey($key, $stats['totals']);
      self::assertIsInt($stats['totals'][$key]);
    }
  }

  /**
   * @test
   */
  public static function getRam()
  {
    $stats = self::$parser->getRam();
    self::assertIsArray($stats);
    foreach (['total', 'type', 'free', 'swapTotal', 'swapFree', 'swapInfo'] as $key) {
      self::assertArrayHasKey($key, $stats);
    }
  }
}
