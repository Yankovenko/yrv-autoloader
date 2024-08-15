<?php

declare(strict_types=1);

use YRV\Autoloader\Parser\Scanner;

require __DIR__ . '/src/Parser/Scanner.php';

$baseDir = __DIR__ . '/../../..';

$scanner = new Scanner($baseDir);

$scanner->setDebugStream(fopen($scanner->cacheDir.'/!debug.txt', 'w'));
$scanner->scanComposerFile($baseDir . '/composer.json', true, true);
$scanner->scanAllComposerFiles($baseDir . '/vendor', false);
$scanner->run();