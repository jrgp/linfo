<?php

use \Linfo\Common;
use \Linfo\Linfo;

use PHPUnit\Framework\TestCase;

class OS_DarwinTest extends TestCase
{
  protected static $parser;

  public static function setUpBeforeClass(): void
  {
    if (PHP_OS !== 'Darwin') {
      self::markTestSkipped('Skip tests for Darwin on other os');
    }

    $linfo = new Linfo();
    self::$parser = $linfo->getParser();

    self::assertInstanceOf('\\Linfo\\OS\\Darwin', self::$parser);
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
    self::assertStringStartsWith('Darwin', self::$parser->getOS());
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
  public static function getModel()
  {
    self::assertIsString(self::$parser->getModel());
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
  public static function getMounts()
  {
    $mounts = self::$parser->getMounts();
    self::assertIsArray($mounts);
    foreach ($mounts as $mount) {
      foreach (['device', 'mount', 'type', 'size', 'used', 'free', 'free_percent', 'used_percent'] as $key) {
        self::assertArrayHasKey($key, $mount);
      }
      self::assertIsString($mount['device']);
      self::assertIsString($mount['mount']);
      self::assertIsString($mount['type']);
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
    $cpus = self::$parser->getCPU();
    self::assertIsArray($cpus);
    foreach ($cpus as $cpu) {
      self::assertArrayHasKey('Model', $cpu);
      self::assertArrayHasKey('MHz', $cpu);
      self::assertArrayHasKey('Vendor', $cpu);
      self::assertIsString($cpu['Model']);
      self::assertIsInt($cpu['MHz']);
      self::assertIsString($cpu['Vendor']);
    }
  }

  /**
   * @test
   */
  public static function getBattery()
  {
    $batteries = self::$parser->getBattery();
    self::assertIsArray($batteries);
    foreach ($batteries as $bat) {
      foreach (['charge_full', 'charge_now', 'percentage', 'state'] as $key) {
        self::assertArrayHasKey($key, $bat);
      }
    }
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
          self::assertIsNumeric($partition['size']);
        }
      }
      self::assertIsNumeric($drive['size']);
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
    foreach (['totals', 'proc_total'] as $key) {
      self::assertArrayHasKey($key, $stats);
    }
    self::assertIsInt($stats['proc_total']);
    foreach (['running', 'zombie', 'stopped', 'sleeping', 'idle'] as $key) {
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
