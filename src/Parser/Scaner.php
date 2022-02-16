<?php

namespace YRV\Autoloader\Parser;

class Scaner
{
    protected array $errors = [];

    protected array $composersData;
    public array $included;
//    protected array $

    protected string $baseDir;
    protected string $cacheDir;
    protected FileAnalyzer $fileAnalyzer;

    protected array $systemConstants;
    protected array $systemFunctions;

    public function __construct(?string $baseDir = null, ?string $cacheDir = null)
    {
        $this->baseDir = $baseDir ? $baseDir : __DIR__ . '/../../../../';
        $this->cacheDir = $cacheDir ? $cacheDir : './../cache/';
        $this->fileAnalyzer = new FileAnalyzer();
        $this->systemFunctions = get_defined_functions()['internal'];
        $this->systemConstants = get_defined_constants();
        $this->systemObjects = array_merge(
            get_declared_classes(),
            get_declared_interfaces(),
            get_declared_traits()
        );
    }

    public function run()
    {

        $dev = false;

        // сканирует и парсит все композер файлы
        $this->scanAllComposerFiles($this->baseDir);

        //
        $files = $this->getFilesForIncludes($dev);

        // наполняет this->included['constants'] & ['functions']
        $this->scanIncludedFiles($files);

        $allFiles = $this->getAllFiles($dev);

        $this->scanAllFiles($allFiles);


        print_r($allFiles);

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
            $components = $this->fileAnalyzer->analyze($file);
            foreach ($components as $component) {
                $functions = $this->filterFunctions($component->getDeclaredFunctions());
                foreach ($functions as $name) {
                    if (isset($this->included['functions'][$name])) {
                        $this->errors[] = sprintf(
                            'Duplicate function [%s] declared in files: %s, %s',
                            $name, $file, $this->included['functions'][$name]
                        );
                    }
                    $this->included['functions'][$name] = $file;
                }
                $constants = $this->filterFunctions($component->getDeclaredConstants());
                foreach ($constants as $name) {
                    if (isset($this->included['constants'][$name])) {
                        $this->errors[] = sprintf(
                            'Duplicate constant [%s] declared in files: %s, %s',
                            $name, $file, $this->included['functions'][$name]
                        );
                    }
                    $this->included['constants'][$name] = $file;
                }
                unset($component);
            }
        }
    }

    public function scanAllFiles($files)
    {
        foreach ($files as $file) {
            $result = $this->scanFile($file);
//            print_r($result);
//            die();
        }
    }


    public function scanFile($file)
    {
        $components = $this->fileAnalyzer->analyze($file);
        $result = [
            'functions' => [],
            'constants' => [],
            'objects' => [],
            'calledFunctions' => [],
            'relations' => [],
            'usedConstants' => [],
        ];
        foreach ($components as $component) {
            $result['functions'] = array_merge(
                $result['functions'],
                $this->filterFunctions($component->getDeclaredFunctions())
            );
            $result['constants'] = array_merge(
                $result['constants'],
                $this->filterConstants($component->getDeclaredConstants())
            );
            $result['objects'] = array_merge(
                $result['objects'],
                $this->filterConstants($component->getDeclaredObjects())
            );

            $result['calledFunctions'] = array_merge(
                $result['calledFunctions'],
                $this->filterFunctions($component->getCalledFunctions(true))
            );

            $result['relations'] = array_merge(
                $result['relations'],
                $this->filterObjects($component->getRelationsObjects())
            );

            $result['usedConstants'] = array_merge(
                $result['usedConstants'],
                $this->filterConstants($component->getUsedConstants(true))
            );
            unset($component);
        }
        array_walk($result, function ($item) {
            $item = array_unique($item);
        });

        return $result;

    }

    protected function filterConstants(array $constants)
    {
        return array_filter($constants, function ($name) {
            if (isset($this->systemConstants[$name])) {
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
        foreach ($subitems as $item) {
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
        }
        return array_keys($files);
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
