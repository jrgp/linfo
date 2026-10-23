<?php

use Linfo\Common;
use Linfo\Extension\Smb;
use Linfo\Extension\Utorrent;
use Linfo\Linfo;
use Linfo\Output\Html;
use PHPUnit\Framework\TestCase;

class EscapingTestUtorrent extends Utorrent
{
    public $hideName = false;
}

class ExtensionEscapingTest extends TestCase
{
    protected function setUp(): void
    {
        new Linfo();
    }

    protected function tearDown(): void
    {
        Common::unconfig();
    }

    private function setPrivate($object, $class, $name, $value)
    {
        $property = new ReflectionProperty($class, $name);
        $property->setAccessible(true);
        $property->setValue($object, $value);
    }

    private function torrent($name, $hash, $status)
    {
        $extension = (new ReflectionClass(EscapingTestUtorrent::class))->newInstanceWithoutConstructor();
        $this->setPrivate($extension, Utorrent::class, 'res', true);
        $this->setPrivate($extension, Utorrent::class, 'stats', array('uploaded' => 512, 'downloaded' => 1024));
        $this->setPrivate($extension, Utorrent::class, 'torrents', array(array(
            'TORRENT_NAME' => $name,
            'TORRENT_HASH' => $hash,
            'TORRENT_STATUS_MESSAGE' => $status,
            'TORRENT_SIZE' => 1024,
            'TORRENT_PROGRESS' => 500,
            'TORRENT_SEEDS_CONNECTED' => 2,
            'TORRENT_SEEDS_SWARM' => 3,
            'TORRENT_PEERS_SWARM' => 4,
            'TORRENT_DOWNLOADED' => 512,
            'TORRENT_UPLOADED' => 256,
            'TORRENT_RATIO' => 500,
            'TORRENT_DOWNSPEED' => 10,
            'TORRENT_UPSPEED' => 5,
        )));

        return $extension;
    }

    public function testTorrentTextIsEscapedWithoutRemovingGeneratedMarkup()
    {
        $payload = '<svg onload=alert(1)>"\' &';
        $extension = $this->torrent($payload, $payload, $payload);
        $result = $extension->result();
        $columns = $result['rows'][1]['columns'];
        $escaped = '&lt;svg onload=alert(1)&gt;&quot;&#039; &amp;';
        self::assertSame($escaped.'<br /><span style="font-size: 80%; font-family: monaco, monospace, courier;">'.$escaped.'</span>', $columns[0]);
        self::assertSame($escaped, $columns[3]);
        self::assertSame('2/3', $columns[4]);
        self::assertSame('2/4', $columns[5]);
        self::assertStringContainsString('width: 50%', $columns[2]);
        self::assertStringNotContainsString('<svg', Html::createTable($result));
    }

    public function testTorrentNamesStayHidden()
    {
        $extension = $this->torrent('<svg onload=alert(1)>', 'safe-hash', 'Seeding');
        $extension->hideName = true;
        $html = Html::createTable($extension->result());
        self::assertStringNotContainsString('onload', $html);
        self::assertStringContainsString('safe-hash</span>', $html);
        self::assertStringContainsString('names hidden', $html);
    }

    public function testInvalidUtf8DoesNotDiscardTheWholeTorrentName()
    {
        $extension = $this->torrent("bad\xff<svg>", 'hash', 'Seeding');
        $result = $extension->result();
        self::assertStringContainsString("bad\xef\xbf\xbd&lt;svg&gt;", $result['rows'][1]['columns'][0]);
    }

    public function testSambaExternalTextIsEscapedAndMachineMarkupPreserved()
    {
        $payload = '<svg onload=alert(1)>"\' &';
        $extension = (new ReflectionClass(Smb::class))->newInstanceWithoutConstructor();
        $this->setPrivate($extension, Smb::class, '_res', array(
            'connections' => array(array(
                'username' => $payload,
                'group' => $payload,
                'hostname' => $payload,
                'ip' => '192.0.2.1',
            )),
            'services' => array(array(
                'service' => $payload,
                'machine' => $payload,
                'date' => 0,
            )),
            'files' => array(array(
                'uid' => 2147483647,
                'rw' => $payload,
                'share' => $payload,
                'filename' => $payload,
                'date' => 0,
            )),
        ));
        $result = $extension->result();
        $escaped = '&lt;svg onload=alert(1)&gt;&quot;&#039; &amp;';
        self::assertSame($escaped, $result['rows'][2]['columns'][0]);
        self::assertSame($escaped, $result['rows'][2]['columns'][1]);
        self::assertSame(array(3, $escaped.' <span class="perc">(192.0.2.1)</span>'), $result['rows'][2]['columns'][2]);
        self::assertSame($escaped, $result['rows'][5]['columns'][0]);
        self::assertSame($escaped, $result['rows'][5]['columns'][1]);
        foreach (array(1, 2, 3) as $column) {
            self::assertSame($escaped, $result['rows'][8]['columns'][$column]);
        }
        self::assertStringNotContainsString('<svg', Html::createTable($result));
    }
}
