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

        $uniqueKey = $uniqueKey ? substr(md5($uniqueKey),0,4) : null;
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
//        echo $classname . ' - '.self::$apcuPrefix . $md5classname;
        $value = apcu_fetch(self::$apcuPrefix . $md5classname);
        if ($value === false) {
//            echo 'false';
            if (self::restore($md5classname)) {
                return true;
            }
//            self::useComposer();
            return false;
        }
//        var_dump($value);
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
        $files = explode("\n", $files);
        foreach ($files as $file) {
            includeFile(self::$baseDir . $file);
        }
    }

//    static protected function generateCache()
//    {
////        // десь надо будет пробегаться по всем файлам и сaмим все собирать,
////        // но пока бeрем данные от composer;
//// for example https://github.com/dmkuznetsov/php-autoloader/blob/master/src/Autoload.php
//        $classMap = require __DIR__ . '/../../../composer/autoload_classmap.php';
//        if (!is_array($classMap)) {
//            return false;
//        }
//        if (self::$cacheDir && (!is_dir(self::$cacheDir) || !is_writable(self::$cacheDir))) {
//            self::$cacheDir = null;
//        }
//
//        foreach ($classMap as $classname => $filename) {
//            $md5classname = md5($classname);
//            apcu_store(self::$apcuPrefix . $md5classname, $filename);
//            if (self::$cacheDir) {
//                file_put_contents(self::$cacheDir . '/' . $md5classname, $filename);
//            }
//        }
////        $classMap;
//
////        $directory = new \RecursiveDirectoryIterator(self::$baseDir);
////        $iterator = new \RecursiveIteratorIterator($directory);
////        $files = array();
////        foreach ($iterator as $info) {
////            if ($info->getFilename() == 'composer.json') {
////                echo  $info->getPathname();
////            }
////        }
//    }



}

function includeFile($file)
{
    require_once $file;
}
