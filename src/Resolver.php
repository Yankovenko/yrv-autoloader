<?php

namespace YRV\Autoloader;

use APCUIterator;

abstract class Resolver
{
    static public string $apcuPrefix = 'j#MN';
    static public int $apcuTtl = 86400;
    static protected string $baseDir;
    static protected string $cacheDir = __DIR__ . '/../cache';

    static public string $composerAutoloaderFile = __DIR__ . '/../../vendor/autoload.php';
    static public bool $useComposerAlternative = true;
    static private bool $composerInited = false;

    /**
     * @param $baseDir
     * The root folder of project, must match of the Scanner used
     *
     * @param $cacheDir
     * [optional] default use /../cache ,
     * must match of the Scanner used
     *
     * @param string|bool $composerAutoloaderFile [optional]
     * * true - use default composer as alternative,
     * * false - not use anything
     * * string - path to initialize your alternative autoloader - it will be the second after the current
     *
     * @param $prefixKey [optional] APCU prefix
     *
     * @return void
     */
    static function init($baseDir, $cacheDir = null, string|bool $composerAutoloaderFile = false, $prefixKey = null): void
    {
        self::$baseDir = $baseDir;
        if ($cacheDir) {
            self::$cacheDir = $cacheDir;
        }
        if ($prefixKey) {
            self::$apcuPrefix = $prefixKey;
        }

        if ($composerAutoloaderFile === false) {
            self::$useComposerAlternative = false;
        } elseif (is_string($composerAutoloaderFile)) {
            self::$composerAutoloaderFile = $composerAutoloaderFile;
        }

        self::autoloadRegister();
        self::preloading();
    }

    static private function autoloadRegister(): void
    {
        if (!self::checkApcuEnabled()) {
            spl_autoload_register([self::class, 'resolveWithoutApcu'], true, true);
            return;
        }

        spl_autoload_register([self::class, 'resolve'], true, true);
    }

    static private function autoloadUnregister(): void
    {
        if (!self::checkApcuEnabled()) {
            spl_autoload_unregister([self::class, 'resolveWithoutApcu']);
            return;
        }

        spl_autoload_unregister([self::class, 'resolve']);
    }

    static public function checkApcuEnabled(): bool
    {
        return (function_exists('apcu_enabled') && apcu_enabled());
    }

    static public function flush(): bool
    {
        if (self::$apcuPrefix) {
            foreach (new APCUIterator('/^' . self::$apcuPrefix . '/') as $item) {
                apcu_delete($item['key']);
            }
        }

        return true;
    }

    static private function preloading(): void
    {

        if (self::checkApcuEnabled()) {
            $files = apcu_fetch(self::$apcuPrefix . '_required');
            if ($files !== false) {
                self::includeFiles($files);
                return;
            }
        }

        $fileWithRequiredFiles = self::$cacheDir . DIRECTORY_SEPARATOR . '!required';
        if (is_file($fileWithRequiredFiles)) {
            $files = file_get_contents($fileWithRequiredFiles);
            if (self::checkApcuEnabled()) {
                apcu_store(self::$apcuPrefix . '_required', $files, self::$apcuTtl);
            }
            self::includeFiles($files);
        }
    }


    static public function resolve($classname): bool
    {
        $md5classname = md5($classname);
        $value = apcu_fetch(self::$apcuPrefix . $md5classname);
        if ($value === false) {
            if (self::restore($md5classname)) {
                return true;
            }
            return self::noResults($classname);
        }
        self::includeFiles($value);
        return true;
    }

    static public function resolveWithoutApcu($classname): bool
    {
        $md5classname = md5($classname);

        $filenames = @file_get_contents(self::$cacheDir . DIRECTORY_SEPARATOR . $md5classname);
        if ($filenames === false) {
            file_put_contents(
                'php://stderr',
                sprintf('The required file [%s] for the class [%s] was not found',
                    $md5classname,
                    $classname
                )
            );
            return self::noResults($classname);
        }
        self::includeFiles($filenames);
        return true;
    }

    static private function restore($key): bool
    {
        if (!file_exists(self::$cacheDir . DIRECTORY_SEPARATOR . $key)) {
            return false;
        }

        $filenames = file_get_contents(self::$cacheDir . DIRECTORY_SEPARATOR . $key);
        apcu_store(self::$apcuPrefix . $key, $filenames, self::$apcuTtl);
        self::includeFiles($filenames);
        return true;
    }

    static private function includeFiles($files): void
    {
        if (!trim($files)) {
            return;
        }
        $files = explode("\n", $files);
        foreach ($files as $file) {
            includeFile(self::$baseDir . $file);
        }
    }

    static private function noResults($classname): bool
    {
        file_put_contents('php://stderr',
            sprintf('Class not found: %s', $classname)
        );

        if (!self::$useComposerAlternative) {
            throw new \RuntimeException('Class "' . $classname . '" not found');
        }
        if (!self::$composerInited) {
            self::autoloadUnregister();
            includeFile(self::$composerAutoloaderFile);
            self::autoloadRegister();
            self::$composerInited = true;
            class_exists($classname);
        }
        return false;
    }
}

function includeFile($file): void
{
    require_once $file;
}
