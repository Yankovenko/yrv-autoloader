<?php

namespace YRV\Autoloader\Tests;

use YRV\Autoloader\Parser\Scanner;

class ExamplesTest extends TestCase
{
    public function testArtisanServiceProviderFile()
    {
        $scaner = new Scanner();
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
        $scaner = new Scanner();
        $result = $scaner->scanFile(__DIR__ . '/examples/Event.php', false);
        $this->assertEmpty($result['c']);
        $this->assertEmpty($result['f']);
        $this->assertEmpty($result['uc']);
        $this->assertEqualsCanonicalizing(
            [
                'base_path',
                'storage_path',
                'Illuminate\Console\Scheduling\base_path',
                'Illuminate\Console\Scheduling\storage_path'
            ],
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
        $scaner = new Scanner();
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

    public function testMacroableFile()
    {
        $scaner = new Scanner();
        $result = $scaner->scanFile(__DIR__ . '/examples/Macroable.php', false);
        $this->assertEmpty($result['f']);
        $this->assertEmpty($result['c']);
        $this->assertEmpty($result['cf']);
        $this->assertEmpty($result['uc']);
        $this->assertEmpty($result['r']);
        $this->assertEquals(['Illuminate\Support\Traits\Macroable'], $result['o']);
    }

    public function testGlFile()
    {
        $scaner = new Scanner();
        $result = $scaner->scanFile(__DIR__ . '/examples/gl.php', false);

        $this->assertEmpty($result['f']);
        $this->assertEmpty($result['c']);
        $this->assertEmpty($result['o']);
        $this->assertEmpty($result['uc']);
        $this->assertEmpty($result['r']);
        $this->assertEquals(['str_starts_with_2'], $result['cf']);
    }


    public function testArrFile()
    {
        $scaner = new Scanner();
        $result = $scaner->scanFile(__DIR__ . '/examples/Arr.php', false);
        $this->assertEmpty($result['f']);
        $this->assertEmpty($result['c']);
        $this->assertEquals(['Illuminate\Support\Arr'], $result['o']);
        $this->assertEmpty($result['uc']);
        $this->assertEqualsCanonicalizing([
            'Illuminate\Support\value',
            'value',
            'Illuminate\Support\data_get',
            'data_get'
        ], $result['cf']);
        $this->assertEqualsCanonicalizing([
            'Illuminate\Support\Traits\Macroable'
        ], $result['r']);

    }

    public function testConstantsFile()
    {
        $scaner = new Scanner();
        $result = $scaner->scanFile(__DIR__ . '/examples/constants.php', false);
        $this->assertEmpty($result['f']);
        $this->assertEmpty($result['o']);
        $this->assertEmpty($result['cf']);
        $this->assertEmpty($result['r']);
        $this->assertEqualsCanonicalizing([
            'TestNS\TestNS2\SIMPLE_CONST',
            'DEFINE_CONST',
            'TestNS\TestNS3\DEFINE_CONST2'
        ], $result['c']);
        $this->assertEqualsCanonicalizing([
            'SIMPLE_CONST',
            'DEFINE_CONST',
            'GLOBAL_CONST',
            'TestNS\TestNS2\TestNS3\DEFINE_CONST2',
            'TestNS\TestNS2\SIMPLE_CONST',
            'TestNS\TestNS2\DEFINE_CONST',
            'GlobalNS\TestConstViaAlias'
        ], $result['uc']);

    }

    public function testAdminSearchFile()
    {
        $scaner = new Scanner();
        $result = $scaner->scanFile(__DIR__ . '/examples/AdminSearch.php', false);

        $this->assertEmpty($result['f']);
        $this->assertEqualsCanonicalizing([
            'YRV2\YSiteORM\Models\DocBase\Methods\AdminSearch'
        ], $result['o']);
        $this->assertEmpty($result['cf']);
        $this->assertEqualsCanonicalizing([
            'YRV2\YSiteORM\Components\Method\MethodInterface',
            'Psr\Http\Server\MiddlewareInterface',
            'YRV2\YSiteORM\Models\OA\Methods\AdminSearch',
            'YRV2\Logger\LoggerTrait'
        ], $result['r']);
        $this->assertEmpty($result['c']);
        $this->assertEmpty($result['uc']);

    }


    public function testEnumFile()
    {
        $scaner = new Scanner();
        $result = $scaner->scanFile(__DIR__ . '/examples/Enum.php', false);

        print_r ($result);

        $this->assertEmpty($result['f']);
        $this->assertEqualsCanonicalizing($result['o'], [
            'EnumNS\EnumExample'
        ]);
        $this->assertEmpty($result['cf']);
        $this->assertEqualsCanonicalizing([
            'EnumNS\EnumInterface',
            'GlobalEnumTrait',
            'EnumNS\Relative\EnumTrait',
        ], $result['r']);
        $this->assertEmpty($result['c']);
        $this->assertEmpty($result['uc']);

    }

}

