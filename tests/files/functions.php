<?php

namespace Namespaces\asdf;

use function GuzzleHttp\Psr7\str;
use function Illuminate\Filesystem;
use function Illuminate\Support\Facades as facade;

use PHPUnit\Util\ErrorHandler;
use PHPUnit\{
    TextUI\XmlConfiguration\CodeCoverage\Report\Php,
    TextUI\XmlConfiguration\Logging\TestDox as TestDox,
    function helpers
};
define('CONSTANT', 'constanta');


const CONSTANT_ONE  = 'qwer';
const CONSTANT_TWO  = 'qwer';
const CONST_TREE = 3, CONST_FOUR = 4;

function functionOne($a) {
    $result = strlen($a);
    $result = \strlen($result) + str($a) + CONST_TREE;

    return require($b);
};


//$a = fn() => 1+2;

class NewClass implements \Iterator {
    use Traitt;
    const
        CONS_CLASS  = 'Const of class' . 'asdf',
        CONST2 = 'asdfadf';

    public function test() {
        if (interfaceOne::CONS_INTERFACE)
            return false;
        return new TestDoc(zxc(123));
        $a = $this->privateFunction();
        function functionInsideClass($a) {return $a . CONS_CLASS ;}
    }
    private function privateFunction(): int {
        return 0;
    }
}

trait Traitt {
    protected string $asd = 'asdf';
    public function test()
    {
        $z = GuzzleHttp\Psr7\uri_for('fsdf' + 'afasdf');
        return '123';
    }
}

trait ComplexTrait{
    use Traitt;
    protected string $zxcv;
    final function FunctionTrait() {

    }
}

interface interfaceOne {
    const CONS_INTERFACE  = 'Const in interface';
    public function asdf ();
}

interface extendedeInterface extends interfaceOne {

}

$b = function($a) use ($b) {
    $c = \sizeof
    ([]);
    $b = strlen ('123');

    return $a + $b;
};

class NewComplexClass extends NewClass implements \ArrayAccess {

}


if (max(10,12) > 200) {
    $a =
        \GuzzleHttp\Handler\asdf ();
    $b = max (2,3);
}

function withoutNamespace() {
    return 0;
}
