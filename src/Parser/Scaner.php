<?php

namespace YRV\Autoloader\Parser;

use GuzzleHttp\Psr7\Stream;

require __DIR__ . '/components.php';
require __DIR__ . '/analyzers.php';

class Scaner
{
    protected array $errors = [];
    protected $debugStream = null;
    protected $errorStream = null;

    protected array $composersData = [];
    protected array $includeFiles = [];
    protected array $resourceFiles = [];
    protected array $libraryFiles = [];

    public string $baseDir;
    public string $cacheDir;
    protected string $cacheDirFiles;
    protected FileAnalyzer $fileAnalyzer;

    protected array $systemConstants;
    protected array $systemFunctions;
    protected array $systemObjects;

    protected array $stat = [];

    /**
     * @param string|null $baseDir
     * @param string|null $cacheDir
     * @param $errorStream - resource streem | null = stderr | false - no out
     * @param $debugStream - resource stream | true = stdout | null|false - no out
     * @throws \Exception
     */
    public function __construct(?string $baseDir = null, ?string $cacheDir = null, $errorStream = null, $debugStream = null)
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

        if ($errorStream) {
            $this->setErrorStream($errorStream);
        } elseif ($errorStream === null) {
            $this->setErrorStream(fopen('php://stderr', 'w'));
        }
        if (is_resource($debugStream)) {
            $this->setDebugStream($debugStream);
        } elseif ($debugStream === true) {
            $this->setDebugStream(fopen('php://stdout', 'w'));
        }
    }

    public function setDebugStream($stream)
    {
        if (!is_resource($stream)) {
            throw new \InvalidArgumentException('Stream must be a resource');
        }

        $this->debugStream = $stream;
    }

    public function setErrorStream($stream)
    {
        if (!is_resource($stream)) {
            throw new \InvalidArgumentException('Stream must be a resource');
        }

        $this->errorStream = $stream;
    }

    protected function addError($error, ...$args)
    {
        if (!empty($args)) {
            $error = sprintf($error, ...$args);
        }
        $this->errors[] = $error;
        if ($this->errorStream) {
            fputs($this->errorStream, $error);
        } elseif ($this->debugStream) {
            fputs($this->debugStream, 'Error: ' . $error);
        }
    }

    protected function debug(...$args)
    {
        if (!$this->debugStream) {
            return;
        }
        foreach ($args as $arg) {
            if (is_scalar($arg)) {
                fputs($this->debugStream, (string) $arg);
            } else {
                fputs($this->debugStream, print_r ($arg, true));
            }
            fputs($this->debugStream, "\n");
        }
    }


    public function run($recreateCache=null)
    {

        $dev = false;

        try {
            // сканирует и парсит все композер файлы
//            $this->scanAllComposerFiles($this->baseDir);
//            print_r($this->composersData);
//            die();
//
//            //
            $this->debug('Included files', $this->includeFiles);
            $this->debug('Resources files', $this->resourceFiles);
            $this->debug('Library files', $this->libraryFiles);

//            $files = $this->getFilesForIncludes($dev);
//print_r ($files);
//die();
            //
            // наполняет this->included['constants'] & ['functions']
            $refResources = $this->getResourceReferencesFromFiles($this->resourceFiles);

            $this->debug('Ref resoureces', $refResources);

            $data = $this->scanFiles($this->libraryFiles, $recreateCache);

            $this->debug('Result scaning', $data);

            //print_r ($data);
//die();
            $dependencies = $this->makeDependencies($data, $refResources);

            $this->debug('Dependencies', $dependencies);

            $this->createCacheAutload($dependencies);

            $this->makeIncludeFile($this->includeFiles);

            echo "Process finished:";
            echo "Create/updated cache files: {$this->stat['c']}\n";
            echo "Create/updated dependencies files: {$this->stat['d']}\n";



        } catch (\Throwable $exception) {
            $this->addError($exception->getMessage());
            die();
        }


//        print_r($data);

    }

    protected function makeIncludeFile (array $files)
    {
        $files = array_map(fn($value) => $this->trimPath($value), $files);
        file_put_contents($this->cacheDir . '/!required', implode("\n", $files));
    }

    protected function createCacheAutload($dependencies)
    {
        foreach ($dependencies as $hash => $dependency) {
            file_put_contents($this->cacheDir . '/'. $hash, implode("\n", $dependency));
            $this->stat['d']++;
        }
    }


    public function makeDependencies($data, $refResources)
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
                    $refResources['functions'][$name] = $datum['fp'];
                });
            }
            if (!empty($datum['c'])) {
                array_walk($datum['c'], function($name) use (&$included, $datum) {
                    $refResources['constants'][$name] = $datum['fp'];
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
                    if (isset($refResources['functions'][$functionName])) {
                        $object['rf'][$functionName] = $refResources['functions'][$functionName];
                    }
                }
            }
            // если испоьзуются константы которые где-то объявлены,
            // то добавляем зависимость
            if (isset($object['uc'])) {
                foreach ($object['uc'] as $constantName) {
                    if (isset($refResources['constants'][$constantName])) {
                        $object['rc'][$constantName] = $refResources['constants'][$constantName];
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
        foreach ($objects as $name => &$object) {
            $hash = md5($name);
            $object['h'] = $hash;
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

        $this->debug('Dependencies process', $objects);

        return $dependencies;
    }


    /** filled
     * @param $files
     * @return array
     */
    public function getResourceReferencesFromFiles($files): array
    {
        $refResources = [];

        foreach ($files as $file) {
            if (!is_file($file)) {
                $this->addError(
                    'File [%s] for included not found',
                    $file
                );
                continue;
            }

            try {
                $components = $this->fileAnalyzer->analyze($file);
            } catch (\Throwable $exception) {
                $this->addError(
                    'Error analyze file [%s]: %s',
                    $file,
                    $exception->getMessage()
                );
                continue;
            }

            foreach ($components as $component) {
                $functions = $this->filterFunctions($component->getDeclaredFunctions());
                foreach ($functions as $name) {
                    if (isset($refResources['functions'][$name])) {
                        $this->addError(
                            'Duplicate function [%s] declared in files: %s, %s',
                            $name, $file, $refResources['functions'][$name]
                        );
                    }
                    $refResources['functions'][$name] = $this->trimPath($file);
                }
                $constants = $this->filterFunctions($component->getDeclaredConstants());
                foreach ($constants as $name) {
                    if (isset($refResources['constants'][$name])) {
                        $this->addError(
                            'Duplicate constant [%s] declared in files: %s, %s',
                            $name, $file, $refResources['functions'][$name]
                        );
                    }
                    $refResources['constants'][$name] = $this->trimPath($file);
                }
                unset($component);
            }
        }

        return $refResources;
    }

    public function scanFiles($files, $recreateCache=null): array
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
     * @param $shortfile - path from baseDir or fullpath, if second - cache not used
     * @param bool|null $cache - true - update, false - not use, null - update if change
     * @return array[]|null
     * @throws \Exception
     */
    public function scanFile($shortfile, ?bool $cache=null): ?array
    {
        $file = $this->baseDir . $shortfile;

        if (!is_file($file)) {
            if (!is_file($shortfile)) {
                $this->addError(
                    'File [%s] not found',
                    $file
                );
                return null;
            } else {
                $file = $shortfile;
                $cache = false;
            }
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
            $this->addError(
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

        if ($cache===false) {
            return $result;
        }
        if (!file_put_contents($fileCache, serialize($result))) {
            throw new \Exception(
                sprintf('Error save cache file [%s]: %s', $fileCache, $exception->getMessage()),
                0,
                $exception
            );
        }

        $this->stat['c']++;
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


    public function scanAllComposerFiles(string $dir, $addInclude = false)
    {
        $directory = new \RecursiveDirectoryIterator($dir);
        $iterator = new \RecursiveIteratorIterator($directory);
        $files = array();
        $data = [];
        foreach ($iterator as $info) {

            if ($iterator->getDepth()>2) {
                continue;
            }

            if ($info->getFilename() == 'composer.json') {
//                var_dump($info);
                $composerFilepath = realpath($info->getPath());
//                die();
                $this->scanComposerFile($info, $addInclude);
            }
        }


//        if (isset($data['f'])) {
//            $this->parseIncludedFiles($data['f']);
//        }
//        print_r ($data);
//        die();
    }

    public function scanComposerFile($file, $addInclude = false, $useDevelop = false): void
    {
        $filename = null;
        if ($file instanceof \SplFileInfo) {
            $filename = $file->getPathname();
        } elseif (is_file($file)) {
            $filename = $file;
        } elseif (is_file($this->baseDir . $file)) {
            $filename = $this->baseDir . $file;
        } else {
            $filename = (string) $file;
            $body = false;
        }
        if ($filename) {
            $body = \file_get_contents($filename);
        }
        if ($body === false) {
            $this->addError("Error loading file [{$filename}]");
            return;
        }

        $json = \json_decode($body, true);

        $data = [];
        $base = dirname(realpath($filename));


        $map1 = $useDevelop ? ['autoload', 'autoload-dev'] : ['autoload'];
        $map2 = ['classmap', 'psr-4', 'psr-0'];
//        'exclude-from-classmap'

        $allFiles = [];
        foreach ($map1 as $v1) {
            $libraryFiles = [];
            foreach ($map2 as $v2) {
                if (isset($json[$v1][$v2]) && !empty($json[$v1][$v2])) {
                    $libraryFiles = array_merge($libraryFiles, $this->getFilesForDirs($base, $json[$v1][$v2]));
                }
            }
            if (isset($json[$v1]['exclude-from-classmap'])) {
                foreach ($json[$v1]['exclude-from-classmap'] as $exclude) {
                    $exclude = $base . $exclude;
                    $libraryFiles = array_filter($libraryFiles, function ($name) use ($exclude) {
                        return !(strpos($name, $exclude) === 0);
                    });
                }
            }
            $allFiles = array_merge($allFiles, $libraryFiles);

            if (isset($json[$v1]['files'])) {
                $files = array_map(fn($value) => $base . '/' .$value ,$json[$v1]['files']);
                if ($addInclude) {
                    $this->includeFiles = array_merge($this->includeFiles, $files);
                } else {
                    $this->resourceFiles = array_merge($this->resourceFiles, $files);
                }
            }
        }
        $this->libraryFiles = array_merge($this->libraryFiles, array_unique($allFiles));
        $this->composersData[$filename] = $data;
    }

    protected function getFilesForDirs(string $dir, array $subitems): array
    {
        if (!is_dir($dir)) {
            $this->addError(
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
                $this->addError(
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
            if (isset($data['f'])) {
                array_map(function ($file) use (&$includes, $dir) {
                    $file = $dir . '/' . $file;
                    $includes[$file] = true;
                }, $data['f']);
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
