<?php

namespace YRV\Autoloader\Parser;

require __DIR__ . '/components.php';
require __DIR__ . '/analyzers.php';

class Scaner
{
    protected array $errors = [];

    protected array $composersData;
    public array $included = [];
//    protected array $

    protected string $baseDir;
    protected string $cacheDir;
    protected string $cacheDirFiles;
    protected FileAnalyzer $fileAnalyzer;

    protected array $systemConstants;
    protected array $systemFunctions;

    public function __construct(?string $baseDir = null, ?string $cacheDir = null)
    {
        $this->baseDir = realpath($baseDir ? $baseDir : __DIR__ . '/../../../..');
        $this->cacheDir = realpath($cacheDir ? $cacheDir : __DIR__ .'/../../cache');
        $this->cacheDirFiles = $this->cacheDir . '/files';
        if (!is_dir($this->cacheDirFiles)) {
            if (!mkdir($this->cacheDirFiles, 0777)) {
                throw new \Exception('Error create cache dir: '.$this->cacheDirFiles);
            }
        }

        $this->fileAnalyzer = new FileAnalyzer();
        $this->systemFunctions = get_defined_functions()['internal'];
        $this->systemConstants = get_defined_constants();
        $this->systemConstantsUnregistred = ['true','false','null'];
        $this->systemObjects = array_merge(
            get_declared_classes(),
            get_declared_interfaces(),
            get_declared_traits()
        );
    }

    public function run($recreateCache=null)
    {

        $dev = false;

        try {
            // сканирует и парсит все композер файлы
            $this->scanAllComposerFiles($this->baseDir);
//            print_r($this->composersData);
//            die();
//
//            //
            $files = $this->getFilesForIncludes($dev);
//print_r ($files);
//die();
            //
//            // наполняет this->included['constants'] & ['functions']
            $this->scanIncludedFiles($files);
//print_r ($this->included);
//die();
            //
            $allFiles = $this->getAllFiles($dev);
//print_r ($allFiles);
//die();
            $data = $this->scanAllFiles($allFiles, $recreateCache);
//print_r ($data);
//die();
            $dependencies = $this->makeDependencies($data);
//            print_r($dependencies);
//            die();
            $this->createCacheAutload($dependencies);



        } catch (\Throwable $exception) {
            $this->errors[] = 'Error: '.$exception->getMessage();
            print_r($this->errors);
            die();
        }


//        print_r($data);

    }

    public function createCacheAutload($dependencies)
    {
        foreach ($dependencies as $hash => $dependency) {
            file_put_contents($this->cacheDir . '/'. $hash, implode("\n", $dependency));
        }
    }


    public function makeDependencies($data)
    {
        $functions = [];
        $constants = [];
        $objects = [];
        $usedConstants = [];
        $calledFunctions = [];

        $dependencies = [];

        foreach ($data as $hash => $datum) {
            if (!empty($datum['f'])) {
                array_walk($datum['f'], function($name) use (&$included, $datum) {
                    $this->included['functions'][$name] = $datum['fp'];
                });
            }
            if (!empty($datum['c'])) {
                array_walk($datum['c'], function($name) use (&$included, $datum) {
                    $this->included['constants'][$name] = $datum['fp'];
                });
            }

            if (!empty($datum['o'])) {
                array_walk($datum['o'], function($name) use (&$objects, $hash, $datum) {
                    $objects[$name] = ['h' => $hash, 'fp' => $datum['fp']];
                    if (!empty($datum['uc'])) {
                        $objects[$name]['uc'] = $datum['uc'];
                    }
                    if (!empty($datum['cf'])) {
                        $objects[$name]['cf'] = $datum['cf'];
                    }
                });
            }
//            if (!empty($datum['uc'])) {
//                array_walk($datum['uc'], function($name) use (&$usedConstants, $hash) {$usedConstants[$name] = $hash;});
//            }
//            if (!empty($datum['cf'])) {
//                array_walk($datum['cf'], function($name) use (&$calledFunctions, $hash) {$calledFunctions[$name] = $hash;});
//            }
        }
//        print_r ($included);
        foreach ($objects as $name => &$object) {
            $hash = $object['h'];
            $relations = $data[$hash]['r'];
            foreach ($relations as $relation) {
                if (isset($objects[$relation])) {
                    $object['r'][$relation] =  $objects[$relation]['fp'];
                }
            }
            if (isset($object['cf'])) {
                foreach ($object['cf'] as $functionName) {
                    if (isset($this->included['functions'][$functionName])) {
                        $object['rf'][$functionName] = $this->included['functions'][$functionName];
                    }
                }
            }
            // если испоьзуются константы которые где-то объявлены,
            // то добавляем зависимость
            if (isset($object['uc'])) {
                foreach ($object['uc'] as $constantName) {
                    if (isset($this->included['constants'][$constantName])) {
                        $object['rc'][$constantName] = $this->included['constants'][$constantName];
                    }
                }
            }
        }


        do {
//            echo 'OnceAgain+';
            $onceAgain = false;
            foreach ($objects as $name => &$object) {
                if (!isset($object['r'])) {
                    continue;
                }

                foreach ($object['r'] as $rName => $file) {
                    if (isset($objects[$rName]['r'])) {
                        foreach ($objects[$rName]['r'] as $rrName => $file) {
                            if (!isset($object['r'][$rrName])) {
                                $object['r'][$rrName] = $file;
                                $onceAgain = true;
                            }
                        }
                    }
                }
            }
        } while ($onceAgain);

        $dependencies = [];
        foreach ($objects as $name => $object) {
            $hash = md5($name);
            $dependencies[$hash] = [$object['fp']];
            if (isset($object['rc'])) {
                $dependencies[$hash] = array_merge($dependencies[$hash], $object['rc']);
            }
            if (isset($object['rf'])) {
                $dependencies[$hash] = array_merge($dependencies[$hash], $object['rf']);
            }
            if (isset($object['r'])) {
                $dependencies[$hash] = array_merge($dependencies[$hash], $object['r']);
            }
            $dependencies[$hash] = array_unique(array_values($dependencies[$hash]));
        }

        return $dependencies;
    }


    /** filled
     * @param $files
     * @return void
     */
    public function scanIncludedFiles($files)
    {
        foreach ($files as $file) {
            if (!is_file($file)) {
                $errors[] = sprintf(
                    'File [%s] for included not found',
                    $file
                );
                continue;
            }

            try {
                $components = $this->fileAnalyzer->analyze($file);
            } catch (\Throwable $exception) {
                $errors[] = sprintf(
                    'Error analyze file [%s]: %s',
                    $file,
                    $exception->getMessage()
                );
                continue;
            }

            foreach ($components as $component) {
                $functions = $this->filterFunctions($component->getDeclaredFunctions());
                foreach ($functions as $name) {
                    if (isset($this->included['functions'][$name])) {
                        $this->errors[] = sprintf(
                            'Duplicate function [%s] declared in files: %s, %s',
                            $name, $file, $this->included['functions'][$name]
                        );
                    }
                    $this->included['functions'][$name] = $this->trimPath($file);
                }
                $constants = $this->filterFunctions($component->getDeclaredConstants());
                foreach ($constants as $name) {
                    if (isset($this->included['constants'][$name])) {
                        $this->errors[] = sprintf(
                            'Duplicate constant [%s] declared in files: %s, %s',
                            $name, $file, $this->included['functions'][$name]
                        );
                    }
                    $this->included['constants'][$name] = $this->trimPath($file);
                }
                unset($component);
            }
        }
    }

    public function scanAllFiles($files, $recreateCache=null): array
    {
        $result = [];
        foreach ($files as $file) {
            try {
                $trimfile = $this->trimPath($file);
                $result[md5($trimfile)] = $this->scanFile($trimfile, $recreateCache);
            } catch (\Throwable $exception) {
                throw $exception;
            }
        }
        return $result;
    }


    /**
     * @param $file
     * @param bool|null $cache - true - update, false - not use, null - update if change
     * @return array[]|null
     * @throws \Exception
     */
    public function scanFile($shortfile, ?bool $cache=null): ?array
    {
        $file = $this->baseDir . $shortfile;

        if (!is_file($file)) {
            $this->errors[] = sprintf(
                'File [%s] not found',
                $file
            );
            return null;
        }
        $fileHash = md5($file);
        $fileCache = $this->cacheDirFiles . '/' . $fileHash;
        if (file_exists($fileCache) && $cache===null) {
            if (filemtime($fileCache) > filemtime($file)) {
                try {
                    $result = unserialize(file_get_contents($fileCache));
                    return $result;
                } catch (\Throwable $exception) {}
            }
        }
        try {
            $components = $this->fileAnalyzer->analyze($file);
//            print_r($components);
        } catch (\Throwable $exception) {
            $errors[] = sprintf(
                'Error analyze file [%s]: %s',
                $file,
                $exception->getMessage()
            );
            return null;
        }

        $result = [
            'f' => [], // functions
            'c' => [], // constants
            'o' => [], // objects
            'cf' => [], // calledFunctions
            'r' => [], // relations
            'uc' => [], // usedConstants
        ];

        foreach ($components as $component) {
            $result['f'] = array_merge(
                $result['f'],
                $this->filterFunctions($component->getDeclaredFunctions())
            );
            $result['c'] = array_merge(
                $result['c'],
                $this->filterConstants($component->getDeclaredConstants())
            );
            $result['o'] = array_merge(
                $result['o'],
                $this->filterConstants($component->getDeclaredObjects())
            );

            $result['cf'] = array_merge(
                $result['cf'],
                $this->filterFunctions($component->getCalledFunctions(true))
            );

            $result['r'] = array_merge(
                $result['r'],
                $this->filterObjects($component->getRelationsObjects())
            );

            $result['uc'] = array_merge(
                $result['uc'],
                $this->filterConstants($component->getUsedConstants(true))
            );
            unset($component);
        }
        array_walk($result, function ($item) {
            $item = array_unique($item);
        });

        $result['fp'] = $shortfile;

        if ($cache!==false && !file_put_contents($fileCache, serialize($result))) {
            throw new \Exception(
                sprintf('Error save cache file [%s]: %s', $fileCache, $exception->getMessage()),
                0,
                $exception
            );
        }

        return $result;

    }

    protected function filterConstants(array $constants)
    {
        return array_filter($constants, function ($name) {
            if (isset($this->systemConstants[$name])) {
                return false;
            } elseif (isset($this->systemConstantsUnregistred[strtolower($name)])) {
                return false;
            }
            return true;
        }, ARRAY_FILTER_USE_BOTH);
    }

    protected function filterFunctions(array $functions)
    {
        return array_filter($functions, function ($name) {
            if (substr($name, 0, 1) == '\\') {
                $name = substr($name, 1);
            }
            if (in_array($name, $this->systemFunctions)) {
                return false;
            }
            return true;
        }, ARRAY_FILTER_USE_BOTH);
    }

    protected function filterObjects(array $objects)
    {
        return array_filter($objects, function ($name) {
            if (substr($name, 0, 1) == '\\') {
                $name = substr($name, 1);
            }
            if (in_array($name, $this->systemObjects)) {
                return false;
            }
            return true;
        }, ARRAY_FILTER_USE_BOTH);
    }


    protected function scanAllComposerFiles(string $dir)
    {
        $directory = new \RecursiveDirectoryIterator($dir);
        $iterator = new \RecursiveIteratorIterator($directory);
        $files = array();
        $data = [];
        foreach ($iterator as $info) {
            if ($info->getFilename() == 'composer.json') {
//                var_dump($info);
                $composerFilepath = realpath($info->getPath());
//                die();
                $data[$composerFilepath] = $this->parseComposerJson($info);
            }
        }
        $this->composersData = $data;


//        if (isset($data['f'])) {
//            $this->parseIncludedFiles($data['f']);
//        }
//        print_r ($data);
//        die();
    }

    protected function parseComposerJson(\SplFileInfo $file): array
    {
        $body = \file_get_contents($file->getPathname());
        if ($body === false) {
            echo "Error loading file [{$file->getFilename()}]\n";
            return [];
        }

        $json = \json_decode($body, true);

        $data = [];
        $base = realpath($file->getPath());

        $map1 = ['a' => 'autoload', 'd' => 'autoload-dev'];
        $map2 = ['f' => 'files', 'm' => 'classmap', 'p4' => 'psr-4', 'p0' => 'psr-0', 'x' => 'exclude-from-classmap'];

        foreach ($map1 as $k1 => $v1) {
            foreach ($map2 as $k2 => $v2) {
                if (isset($json[$v1][$v2]) && !empty($json[$v1][$v2])) {
                    $data[$k1][$k2] = $json[$v1][$v2];
                }
            }
        }
        return $data;
    }

    public function getAllFiles($dev = false)
    {
        $allFiles = [];
        foreach ($this->composersData as $dir => $data) {
            $libraryFiles = [];
            if (isset($data['a']['m'])) {
                $libraryFiles = array_merge($libraryFiles, $this->getFilesForDirs($dir, $data['a']['m']));
            }

            if (isset($data['a']['p0'])) {
                $libraryFiles = array_merge($libraryFiles, $this->getFilesForDirs($dir, $data['a']['p0']));
            }

            if (isset($data['a']['p4'])) {
                $libraryFiles = array_merge($libraryFiles, $this->getFilesForDirs($dir, $data['a']['p4']));
            }

            if (isset($data['a']['x'])) {
                foreach ($data['a']['x'] as $exclude) {
                    $exclude = $dir . $exclude;
                    $libraryFiles = array_filter($libraryFiles, function ($name) use ($exclude) {
                        return !(strpos($name, $exclude) === 0);
                    });
                }
            }
            $allFiles = array_merge($allFiles, $libraryFiles);
        }
        return array_unique($allFiles);

    }

    protected function getFilesForDirs(string $dir, array $subitems): array
    {
        if (!is_dir($dir)) {
            $this->errors[] = sprintf(
                'Directory [%s] not found',
                $dir
            );
            return [];
        }
        $files = [];
        do {
            $item = current($subitems);
        //foreach ($subitems as $item) {
            if (is_array($item)) {
                array_push($subitems, ...$item);
                continue;
            }
            $name = $dir . '/' . $item;
            if (is_file($name)) {
                $files[$name] = true;
                continue;
            } elseif (!is_dir($name)) {
                $this->errors[] = sprintf(
                    'Directory [%s] not found',
                    $name
                );
                continue;
            }
            $directory = new \RecursiveDirectoryIterator($name);
            $iterator = new \RecursiveIteratorIterator($directory);
            foreach ($iterator as $item) {
                if ($item->getExtension() === 'php') {
                    $files[$item->getPathname()] = true;
                }
            }
            unset($iterator, $directory);
        } while (next($subitems));
        return array_keys($files);
    }

    public function trimPath($path): string
    {
        static $baseDirLength;
        if (!$baseDirLength) {
            $baseDirLength = strlen($this->baseDir);
        }

        if (strpos($path, $this->baseDir) === 0) {
            if (isset($path[$baseDirLength]) && $path[$baseDirLength] === '/') {
                return substr($path, $baseDirLength);
            }
        }
        $bdd = explode ('/', $this->baseDir);
        $pd = explode ('/', $path);
        $np = $pd;
//        print_r ($pd);
        $step = 0;
        while (isset($bdd[$step]) && $bdd[$step] === $pd[$step]) {
            array_shift(($np));
            $step++;
        }
        if ($step < sizeof($bdd)) {
            array_unshift($np, ...array_fill(0, sizeof($bdd)-$step, '..'));
        }
        return '/'.implode('/', $np);
    }


    protected function getFilesForIncludes($dev = false)
    {
        $includes = [];
        foreach ($this->composersData as $dir => $data) {
            if (isset($data['a']['f'])) {
                array_map(function ($file) use (&$includes, $dir) {
                    $file = $dir . '/' . $file;
                    $includes[$file] = true;
                }, $data['a']['f']);
            }

            if ($dev && isset($data['d']['f'])) {
                array_map(function ($file) use (&$includes, $dir) {
                    $file = $dir . '/' . $file;
                    $includes[$file] = true;
                }, $data['d']['f']);
            }
        }
        return array_keys($includes);
    }
}
