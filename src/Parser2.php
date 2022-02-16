<?php

namespace YRV\Autoloader;

use YRV\Autoloader\Parser\Scaner;

require __DIR__ . '/Parser/components.php';
require __DIR__ . '/Parser/analyzers.php';
require __DIR__ . '/Parser/Scaner.php';



$scaner = new Scaner();
$scaner->run();
//print_r($scaner->included);
//$scaner->scanFile('/var/www/vendor/illuminate/support/helpers.php');
//$scaner->scanFile('/var/www/vendor/nesbot/carbon/src/Carbon/CarbonPeriod.php');
//$scaner->scanFile(__DIR__.'/../tests/files/functions.php');
//$scaner->scanFile('/var/www/vendor/leocavalcante/siler/src/facades.php');

return;

$janus = new Parser\FileAnalyzer();


$begin = microtime(true);
$nbFiles = 0;
$nbComponents = 0;
$dir = __DIR__ . '/../tests/files';
//$dir = __DIR__;
foreach (analyzeDir($dir) as $subpath) {
    $fileContent = [
        'filepath' => $subpath,
        'components' => []
    ];

    foreach ($janus->analyze($subpath) as $phpComponent) {
        $fileContent['components'][] = $phpComponent->toArray();
        $nbComponents++;
    }

    print_r ($fileContent);
//    echo json_encode($fileContent) . "\n";
    $nbFiles++;
}

echo "\n\n\n";
echo "Time : " . round(microtime(true) - $begin, 3) . "s\n";
echo "Nb files : " . $nbFiles . "\n";
echo "Nb components : " . $nbComponents . "\n";
echo "Peak memory : " . round(memory_get_peak_usage(true) / 1024 / 1024, 3) . "MB\n";

function analyzeDir(string $path)
{
    foreach (scandir($path) as $file) {
        if (in_array($file, ['.', '..', 'cache'])) {
            continue;
        }

        $subpath = $path . '/' . $file;

        if (is_dir($subpath)) {
            yield from analyzeDir($subpath);
        } elseif (strtolower(substr($subpath, strlen($subpath) - 3)) === 'php') {
            yield $subpath;
        }
    }
}


