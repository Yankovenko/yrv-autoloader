<?php

namespace TestNS\TestNS2;
use const GlobalNS\TestConstViaAlias as ASD;

const SIMPLE_CONST = 'SIMPLE';
define('DEFINE_CONST', 'define');
define('TestNS\TestNS3\DEFINE_CONST2', 'define');

$a = function  () {
    $a = T_COMMENT + SIMPLE_CONST;
};
$b=DEFINE_CONST;
$c=\GLOBAL_CONST;
$d = TestNS3\DEFINE_CONST2;
$c = ASD;
