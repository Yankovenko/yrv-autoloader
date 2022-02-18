<?php

namespace Tests;

use YRV\Autoloader\Parser\Scaner;

class ExamplesTest extends TestCase
{
    public function testArtisanServiceProviderFile()
    {
        $scaner = new Scaner();
        $result = $scaner->scanFile(__DIR__ . '/examples/ArtisanServiceProvider.php', false);
        $this->assertEmpty($result['c']);
        $this->assertEmpty($result['f']);
        $this->assertEmpty($result['uc']);
        $this->assertEmpty($result['cf']);
        $this->assertEquals(['Illuminate\Foundation\Providers\ArtisanServiceProvider'], $result['o']);
        $this->assertEqualsCanonicalizing(
            ['Illuminate\Contracts\Support\DeferrableProvider', 'Illuminate\Support\ServiceProvider'],
            $result['r']);
    }

    public function testIlluminateConsoleSheduleEventFile()
    {
        $scaner = new Scaner();
        $result = $scaner->scanFile(__DIR__ . '/examples/Event.php', false);
        $this->assertEmpty($result['c']);
        $this->assertEmpty($result['f']);
        $this->assertEmpty($result['uc']);
        $this->assertEqualsCanonicalizing(
            ['base_path', 'storage_path'],
            $result['cf']);
        $this->assertEquals(['Illuminate\Console\Scheduling\Event'], $result['o']);
        $this->assertEqualsCanonicalizing(
            [
                'Illuminate\Support\Traits\Macroable',
                'Illuminate\Console\Scheduling\ManagesFrequencies',
                'Illuminate\Support\Traits\ReflectsClosures'
            ],
            $result['r']);

    }

    public function testExampleClassFile()
    {
        $scaner = new Scaner();
        $result = $scaner->scanFile(__DIR__ . '/examples/ExampleClass.php', false);
        $this->assertEmpty($result['c']);
        $this->assertEmpty($result['cf']);
        $this->assertEquals(['Namespace1\Namespace2\functionOutsideClass'], $result['f']);
        $this->assertEmpty($result['uc']);
        $this->assertEquals(['Namespace1\Namespace2\ExampleClass'], $result['o']);
        $this->assertEqualsCanonicalizing(
            [
                'Namespace1\Namespace2\ExampleClassInterface',
                'Namespace1\Namespace2\ExampleClassParent'
            ],
            $result['r']);
    }

    public function testTest()
    {
        $scaner = new Scaner();
        $res = $scaner->scanFile(__DIR__.'/../src/Dumper.php', true);
        print_r ($res);
    }
}

