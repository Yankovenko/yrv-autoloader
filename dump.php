<?php

declare(strict_types=1);

use YRV\Autoloader\Parser\Scanner;

require __DIR__ . '/src/Parser/Scanner.php';


$baseDir = __DIR__ . '/../../..';

$scaner = new Scanner($baseDir);

$scaner->setDebugStream(fopen($scaner->cacheDir.'/!debug.txt', 'w'));

$scaner->scanComposerFile($baseDir . '/composer.json', true, true);
$scaner->scanAllComposerFiles($baseDir . '/vendor', false);

$scaner->run();
