<?php

namespace YRV\Autoloader;

use APCUIterator;

abstract class Resolver
{
    static protected string $baseDir;
    static protected string $apcuPrefix = 'j#MN';
    static protected string $cacheDir = __DIR__ . '/../cache';

    static public string $composerAutoloaderFile = __DIR__ . '/../../vendor/autoload.php';
    static public bool $useComposerAlternative = true;

    static private bool $composerInited = false;

    static function init($baseDir, $cacheDir = null, string|bool|null $composerAutoloaderFile = null, $prefixKey=null): void
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

    static protected function hashUniqueKey($uniqueKey): string
    {
        return md5((string)$uniqueKey);
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

    static protected function preloading(): void
    {
        if (is_file(self::$cacheDir .'/!required')) {
            self::includeFiles(file_get_contents(self::$cacheDir .'/!required'));
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
        if (!file_exists(self::$cacheDir . $md5classname)) {
            return self::noResults($classname);
        }

        $filenames = file_get_contents(self::$cacheDir . $md5classname);
        self::includeFiles($filenames);
        return true;
    }

    static private function restore($key): bool
    {
        if (!file_exists(self::$cacheDir . '/' . $key)) {
            return false;
        }

        $filenames = file_get_contents(self::$cacheDir . '/' .$key);
        apcu_store(self::$apcuPrefix . $key, $filenames, 86400);
        self::includeFiles($filenames);
        return true;
    }

    static protected function includeFiles($files): void
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
        file_put_contents('php://stderr', 'Autoloader: class not found: ' . $classname."\n");

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
