<?php

use \Linfo\Common;
use \Linfo\Linfo;
use \Linfo\Parsers\FileIO;

use PHPUnit\Framework\TestCase;

class DistroCheck extends TestCase
{
  protected static $parser;
  protected static $io;

  public static function setUpBeforeClass(): void
  {
    if (PHP_OS !== 'Linux') {
      self::markTestSkipped('Skip tests for Linux on other os');
    }

    $linfo = new Linfo();
    self::$parser = $linfo->getParser();

    self::$io = new MockedIO;

    Common::$io = self::$io;
  }

  /**
   * Test in-depth that many of our linux distro regexes actually work
   * @test
   */
  public static function checkDistros() {
    foreach([

        [
            'path' => '/etc/os-release',
            'contents' => "NAME=\"Ubuntu\"\nVERSION=\"20.04.1 LTS (Focal Fossa)\"\nID=ubuntu",
            'expected' => ['name' => 'Ubuntu', 'version' => '20.04.1 LTS (Focal Fossa)'],
        ],

        [
            'path' => '/etc/lsb-release',
            'contents' => "DISTRIB_ID=Ubuntu\nDISTRIB_RELEASE=20.04\nDISTRIB_CODENAME=focal",
            'expected' => ['name' => 'Ubuntu', 'version' => '20.04 (Focal)'],
        ],

        [
            'path' => '/etc/redhat-release',
            'contents' => "Red Hat Enterprise Linux release 5.6 (Santiago)",
            'expected' => ['name' => 'RedHat', 'version' => '5.6 (Santiago)'],
        ],

        [
            'path' => '/etc/redhat-release',
            'contents' => "CentOS release 6.5 (Final)",
            'expected' => ['name' => 'CentOS', 'version' => '6.5 (Final)'],
        ],

        [
            'path' => '/etc/fedora-release',
            'contents' => "Fedora Core release 10 (Foobar)",
            'expected' => ['name' => 'Fedora', 'version' => '10 (Foobar)'],
        ],

        [
            'path' => '/etc/debian_version',
            'contents' => "bullseye/sid",
            'expected' => ['name' => 'Debian', 'version' => 'bullseye/sid'],
        ],

    ] as $item) {
        self::$io->register($item['path'], $item['contents']);
        self::assertEquals($item['expected'], self::$parser->getDistro());
        self::$io->unregister($item['path']);
    }
  }

  public static function tearDownAfterClass(): void
  {
    self::$parser = null;
    Common::unconfig();
    Common::$io = new FileIO(false);
  }
}

