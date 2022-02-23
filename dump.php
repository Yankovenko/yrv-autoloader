<?php

declare(strict_types=1);

use YRV\Autoloader\Parser\Scaner;

require __DIR__ . '/src/Parser/Scaner.php';


$baseDir = __DIR__ . '/../../..';

$scaner = new Scaner($baseDir);

$scaner->setDebugStream(fopen($scaner->cacheDir.'/!debug.txt', 'w'));

$scaner->scanComposerFile($baseDir . '/composer.json', true, true);
$scaner->scanAllComposerFiles($baseDir . '/vendor', true);

$scaner->run(false);
