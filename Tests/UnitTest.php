<?php

namespace YRV\Autoloader\Tests;

use YRV\Autoloader\Parser\Scanner;

class UnitTest extends TestCase
{
    public function testTrimFunction()
    {
        $scaner = new Scanner('/var/www');

        $path = '/var/www/path1/path2/path3';
        $this->assertEquals('/path1/path2/path3', $scaner->trimPath($path));

        $path = '/var/www2/path1/path2/path3';
        $this->assertEquals('/../www2/path1/path2/path3', $scaner->trimPath($path));

//        $path = '/var/www/path1/../path2/path3';
//        $this->assertEquals('/path2/path3', $scaner->trimPath($path));

    }
}

