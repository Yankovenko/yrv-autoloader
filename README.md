# YRV Autoloader

> This is an alternative superfast class autoloader
> based on file composer maps.

For its operation, a preliminary scan of all resources specified 
in the `composer.json` file is performed.

An important feature is the exclusion of stupid forced inclusions. 
If the specified includes contain EXPLICIT functions and constants, 
they will be dynamically loaded along with the autoloading 
of the classes in which they are EXPLICITLY used.

> **For example:**
> 
>If the file contains dependencies on classes, interfaces, traits, functions and constants, 
> then they will all be loaded in the correct sequence and in one go, 
> since precompilation recursively collects all these dependencies into one file.

The autoloader is very fast, but requires recompilation when changing dependencies,
so it is not convenient for development. But, you can specify a composer file during initialization.
If this fails, the standard composer will be sed and a message about an unfound class will be sent to STDERR.


> It is very highly effective when working in production. 
> Efficiency is 5-10 times higher than the autoloader of a composer. 
> At the same time, memory usage is minimal and does not depend on the number of classes in project.


For best performance, turn on APCU


## Preparing
For usage, you need generate cache with all dependencies.

* For default parameters, just run `dump.php`
```shell
> php dump.php
```

* For custom options use example
```php

$scanner = new Scanner(
    $projectDir, 
    $cacheDir = $projectDir. '/_cache' // optional
);

// For debug output
$scanner->setErrorStream(fopen('php://stderr', 'w+'));

$scanner->scanComposerFile(
    $projectDir . '/composer.json', //Main composer config  
    true, // include the preliminary scripts specified in the config
    true // add develop classes for scan
);
$scanner->scanAllComposerFiles(
    $projectDir . '/vendor', // Directory for search composer config files
    false // include the preliminary scripts specified in the config
);

// Start scanning
$scanner->run(); 
```

## Usage
Include `vendor/yrv/autoloader/run.php` into your project instead of `vendor/autoload.php`

```php
<?php

$root = dirname(__DIR__);
require '../vendor/yrv/autoloader/src/Resolver.php';

\YRV\Autoloader\Resolver::init(
    $root, // Root folder of project
    $root.'/_cache', // Cache folder, optional
    '../vendor/autoload.php' // Alternative autoloader, optional
);

$app = new \App\App($root);
...
```

## Warning!!!

### It not supported included logic
If some libraries perform some logical actions in includes,
the project may not work. To fix the problem - you need to register
a list of includes in your main composer.json.

### Preparation depends on the environment
When scanning and creating a cache, the features native to the installed version
are taken into account. Accordingly, if a function is implemented in PHP for which there
is a polymorphic file, it will not be included during autoload.



