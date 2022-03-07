<?php

namespace YRV\Autoloader;

class Resolver
{
    static protected $baseDir;
    static protected $apcuKey = 'YRV_Resolver_key';
    static protected $apcuPrefix = '';
    static protected $cacheDir = __DIR__ . '/../cache';

    static function init($baseDir, $uniqueKey=null)
    {
        self::$baseDir = $baseDir;

        if (!self::checkApcuEnabled()) {
            spl_autoload_register([self::class, 'resolveWhitoutApcu'], false, true);
            self::preloading();
            return;
        }

        $uniqueKey = static::hashUniqueKey($uniqueKey);
        self::loadApcuPrefix($uniqueKey);
        if (!self::$apcuPrefix) {
            self::generateApcuPrefix($uniqueKey);
        }

        spl_autoload_register([self::class, 'resolve'], false, true);
        self::preloading();
    }

    static public function checkApcuEnabled(): bool
    {
        return (function_exists('apcu_enabled') && apcu_enabled());
    }

    static protected function hashUniqueKey($uniqueKey): string
    {

    }

    static public function flush(string $uniqueKey = ''): bool
    {
        if (!static::checkApcuEnabled()) {
            return false;
        }
        $uniqueKey = static::hashUniqueKey($uniqueKey);
        self::loadApcuPrefix($uniqueKey);
        if (self::$apcuPrefix) {
            foreach (new APCUIterator('/^' . self::$apcuPrefix . '/') as $item) {
                apcu_delete($item['key']);
            }
        }
        self::generateApcuPrefix($uniqueKey);

        return true;
    }

    static protected function preloading()
    {
        if (is_file(self::$cacheDir .'/!required')) {
            self::includeFiles(file_get_contents(self::$cacheDir .'/!required'));
        }
    }


    static protected function loadApcuPrefix($key=null)
    {
        $apcuPrefix = apcu_fetch(self::$apcuKey);
        if (!$apcuPrefix || ($key && ($key != $apcuPrefix))) {
            return null;
        }
        self::$apcuPrefix = $apcuPrefix;
    }

    static protected function generateApcuPrefix($key=null)
    {

        if ($key === null) {
            $key = substr(md5(mt_srand(0,mt_getrandmax()).microtime()),0,4);
        }
        self::$apcuPrefix = $key;
        apcu_store(self::$apcuKey, $key);
    }

    static public function resolve($classname)
    {

        $md5classname = md5($classname);
        $value = apcu_fetch(self::$apcuPrefix . $md5classname);
        if ($value === false) {
            if (self::restore($md5classname)) {
                return true;
            }
            return false;
        }
        self::includeFiles($value);
    }

    static function resolveWhitoutApcu($classname)
    {
        if (!file_exists($cacheDir . $key)) {
            return false;
        }

        $filenames = file_get_contents($cacheDir . $key);
        self::includeFiles($filenames);
        return true;
    }

    static protected function restore($key)
    {
        if (!file_exists(self::$cacheDir . '/' . $key)) {
            return false;
        }

        $filenames = file_get_contents(self::$cacheDir . '/' .$key);
        apcu_store(self::$apcuPrefix . $key, $filenames);
        self::includeFiles($filenames);
        return true;
    }

    static protected function includeFiles($files)
    {
        if (!trim($files)) {
            return;
        }
        $files = explode("\n", $files);
        foreach ($files as $file) {
            includeFile(self::$baseDir . $file);
        }
    }
}

function includeFile($file)
{
    require_once $file;
}
