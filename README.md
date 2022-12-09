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
so it is not convenient for development.

> But it is very highly effective when working in production. 
> Efficiency is 5-10 times higher than the autoloader of a composer. 
> At the same time, memory usage is minimal and does not depend on the number of classes.


For best performance, turn on APCU


## Preparing
For usage, you need generate cache with all dependence.

```shell
> php dump.php
```

## Usage
Include `vendor/yrv/autoloader/run.php` into your project instead of `vendor/autoload.php`

```php
<?php

require 'vendor/yrv/autoloader/run.php';
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


## Flush cache
For flush APCU cache you need restart php process manager or run in your code
```php
\YRV\Autoloader\Resolver::flush();
```


