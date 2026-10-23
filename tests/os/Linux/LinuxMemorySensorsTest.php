<?php

use Linfo\Common;
use Linfo\Meta\Errors;
use Linfo\OS\Linux;
use Linfo\Parsers\FileIO;
use PHPUnit\Framework\TestCase;

class LinuxFixtureIO extends FileIO
{
    private $root;

    public function __construct($root)
    {
        parent::__construct(false);
        $this->root = $root;
    }

    public function getContents($file, $default = '')
    {
        if (strpos($file, '/proc/') === 0) {
            $file = $this->root.$file;
        }

        return parent::getContents($file, $default);
    }
}

class LinuxFixtureParser extends Linux
{
    public function __construct($root, $showEmptyFans = false)
    {
        parent::__construct(array(
            'temps' => array('hwmon' => true, 'thermal_zone' => true),
            'temps_show0rpmfans' => $showEmptyFans,
        ));
        $this->sysPath = $root.'/sys';
    }
}

class LinuxMemorySensorsTest extends TestCase
{
    private $root;
    private $parser;
    private $previousIO;

    protected function setUp(): void
    {
        if (PHP_OS !== 'Linux') {
            self::markTestSkipped('Linux collectors require Linux');
        }
        $this->root = sys_get_temp_dir().'/linfo-'.bin2hex(random_bytes(8));
        mkdir($this->root);
        $this->previousIO = Common::$io;
        Common::$io = new LinuxFixtureIO($this->root);
        Errors::clear();
        $this->parser = new LinuxFixtureParser($this->root);
    }

    protected function tearDown(): void
    {
        Common::$io = $this->previousIO;
        Errors::clear();
        if ($this->root === null) {
            return;
        }
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->root, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($files as $file) {
            if ($file->isDir() && !$file->isLink()) {
                rmdir($file->getPathname());
            } else {
                unlink($file->getPathname());
            }
        }
        rmdir($this->root);
    }

    private function put($path, $value)
    {
        $file = $this->root.$path;
        if (!is_dir(dirname($file))) {
            mkdir(dirname($file), 0777, true);
        }
        file_put_contents($file, $value);
    }

    private function memory($overrides = array())
    {
        $values = array_merge(array(
            'MemTotal' => 1000000,
            'MemFree' => 100000,
            'MemAvailable' => 600000,
            'Cached' => 200000,
            'Buffers' => 10000,
            'SReclaimable' => 50000,
            'Shmem' => 20000,
            'SwapTotal' => 100000,
            'SwapFree' => 60000,
            'SwapCached' => 10000,
        ), $overrides);
        $contents = '';
        foreach ($values as $key => $value) {
            if ($value !== null) {
                $contents .= $key.': '.$value." kB\n";
            }
        }
        $this->put('/proc/meminfo', $contents);
        $this->put('/proc/swaps', "Filename Type Size Used Priority\n/swapfile file 100000   40000 -2\n");
    }

    public function testMemoryUsesKernelAvailabilityAndActualSwapFree()
    {
        $this->memory();
        $ram = $this->parser->getRam();
        self::assertSame(600000 * 1024, $ram['free']);
        self::assertSame(60000 * 1024, $ram['swapFree']);
        self::assertSame(10000 * 1024, $ram['swapCached']);
        self::assertCount(1, $ram['swapInfo']);
        self::assertSame($ram['swapTotal'] - $ram['swapFree'], $ram['swapInfo'][0]['used']);
        self::assertSame(0, Errors::num());
    }

    public function testMemoryFallbackAccountsForReclaimableSlabAndSharedMemory()
    {
        $this->memory(array('MemAvailable' => null));
        self::assertSame(340000 * 1024, $this->parser->getRam()['free']);
    }

    public function testMemoryBoundsAndOptionalFields()
    {
        $this->memory(array('MemAvailable' => 2000000, 'SwapFree' => 200000, 'SwapCached' => null));
        $ram = $this->parser->getRam();
        self::assertSame($ram['total'], $ram['free']);
        self::assertSame($ram['swapTotal'], $ram['swapFree']);
        self::assertSame(0, $ram['swapCached']);
        $this->memory(array('MemAvailable' => null, 'Shmem' => 900000));
        self::assertSame(0, $this->parser->getRam()['free']);
    }

    public function testMissingSwapDetailsPreservesMemoryAndSwapTotals()
    {
        $this->memory();
        unlink($this->root.'/proc/swaps');
        $ram = $this->parser->getRam();
        self::assertSame(1000000 * 1024, $ram['total']);
        self::assertSame(100000 * 1024, $ram['swapTotal']);
        self::assertSame(array(), $ram['swapInfo']);
        self::assertSame(1, Errors::num());
    }

    public function testMissingAndInvalidMemoryAreReported()
    {
        self::assertSame(array(), $this->parser->getRam());
        foreach (array('', "MemTotal: nope kB\n", "MemTotal: 1000 kB\n") as $contents) {
            $this->put('/proc/meminfo', $contents);
            self::assertSame(array(), $this->parser->getRam());
        }
        $this->memory(array('MemTotal' => 0));
        self::assertSame(array(), $this->parser->getRam());
        self::assertSame(5, Errors::num());
    }

    public function testTemperaturesIncludeUnlabeledColdNegativeAndZeroReadings()
    {
        $base = '/sys/class/hwmon/hwmon0/';
        $this->put($base.'name', 'coretemp');
        foreach (array(1 => 10000, 2 => -5000, 3 => 0, 4 => 45500) as $channel => $value) {
            $this->put($base.'temp'.$channel.'_input', (string) $value);
        }
        $this->put($base.'temp4_label', 'Package');
        $temps = $this->parser->getTemps();
        $values = array();
        foreach ($temps as $temp) {
            $values[basename($temp['path'])] = $temp['temp'];
            self::assertSame('C', $temp['unit']);
            self::assertStringContainsString('coretemp / hwmon0', $temp['name']);
        }
        self::assertEquals(array(
            'temp1_input' => 10,
            'temp2_input' => -5,
            'temp3_input' => 0,
            'temp4_input' => 45.5,
        ), $values);
        self::assertSame(0, Errors::num());
    }

    public function testAliasesAreDeduplicatedButEqualIndependentSensorsAreNot()
    {
        $base = '/sys/class/hwmon/hwmon0/';
        $this->put($base.'temp1_input', '45000');
        $this->put('/sys/class/hwmon/hwmon1/temp1_input', '45000');
        mkdir($this->root.$base.'device');
        symlink('../temp1_input', $this->root.$base.'device/temp1_input');
        self::assertCount(2, $this->parser->getTemps());
    }

    public function testLegacyDeviceLayoutUsesChipNameAndModel()
    {
        $base = '/sys/class/hwmon/hwmon0/';
        $this->put($base.'device/temp1_input', '32000');
        $this->put($base.'device/name', 'drivetemp');
        $this->put($base.'device/model', 'Test disk');
        $temps = $this->parser->getTemps();
        self::assertCount(1, $temps);
        self::assertSame('Test disk (drivetemp / hwmon0)', $temps[0]['name']);
        self::assertEquals(32, $temps[0]['temp']);
    }

    public function testFansVoltagesAndInvalidSensorReads()
    {
        $base = '/sys/class/hwmon/hwmon0/';
        $this->put($base.'fan1_input', '0');
        $this->put($base.'fan2_input', '1200');
        $this->put($base.'fan2_label', 'CPU fan');
        $this->put($base.'in0_input', '1250');
        $this->put($base.'temp1_input', 'not a number');
        $this->put($base.'temp2_input', '');
        symlink('missing', $this->root.$base.'temp3_input');
        $temps = $this->parser->getTemps();
        self::assertCount(2, $temps);
        self::assertSame(3, Errors::num());
        $values = array();
        foreach ($temps as $temp) {
            $values[$temp['unit']] = $temp['temp'];
        }
        self::assertEquals(array('RPM' => 1200, 'V' => 1.25), $values);
        $parser = new LinuxFixtureParser($this->root, true);
        self::assertCount(3, $parser->getTemps());
    }

    public function testThermalZonesUseMillidegreesAndRejectInvalidReadings()
    {
        foreach (array(0 => '10000', 1 => '-5000', 2 => '0', 3 => 'invalid') as $id => $value) {
            $base = '/sys/class/thermal/thermal_zone'.$id.'/';
            $this->put($base.'type', 'zone');
            $this->put($base.'temp', $value);
        }
        $temps = $this->parser->getTemps();
        self::assertCount(3, $temps);
        $values = array_column($temps, 'temp');
        sort($values);
        self::assertEquals(array(-5, 0, 10), $values);
        self::assertSame(1, Errors::num());
    }

    public function testBacklightAliasesAreDeduplicatedAndPercentageKeepsPrecision()
    {
        $base = '/sys/devices/virtual/backlight/panel/';
        $this->put($base.'max_brightness', '3');
        $this->put($base.'actual_brightness', '1');
        mkdir($this->root.'/sys/class/backlight', 0777, true);
        symlink('../../devices/virtual/backlight/panel', $this->root.'/sys/class/backlight/panel');
        $temps = $this->parser->getTemps();
        self::assertCount(1, $temps);
        self::assertSame(33.33, $temps[0]['temp']);
    }

    public function testBacklightRejectsZeroMaximumAndInvalidReadings()
    {
        foreach (array(
            array('0', '0'),
            array('bad', '0'),
            array('100', '-1'),
            array('100', '101'),
            array('100', ''),
        ) as $values) {
            $this->put('/sys/class/backlight/panel/max_brightness', $values[0]);
            $this->put('/sys/class/backlight/panel/actual_brightness', $values[1]);
            self::assertSame(array(), $this->parser->getTemps());
        }
        self::assertSame(5, Errors::num());
        $this->put('/sys/class/backlight/panel/max_brightness', '100');
        $this->put('/sys/class/backlight/panel/actual_brightness', '0');
        self::assertEquals(0, $this->parser->getTemps()[0]['temp']);
    }
}
