# YRV Autloader

This is an alternative super fast class autoloader
based on file copozer maps.

For its operation, a preliminary scan of all resources specified 
in the `composer.json` file is performed.

An important feature is the exclusion of stupid forced inclusions. 
If the specified includes contain EXPLICIT functions and constants, 
they will be dynamically loaded along with the autoloading 
of the classes in which they are EXPLICITLY used.

Therefore, if some libraries perform some logical actions in includes, 
the project may not work. To fix the problem - you need to register 
a list of includes in your project.

For best performance, turn on APCU

## Preparing
Scipt `dump.php` scans and generates dependencies

## Usage
Include `vendor/yrv/autoloader/run.php` into your project instead of `vendor/autoload.php`


