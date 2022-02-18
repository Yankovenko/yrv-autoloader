<?php

namespace Namespace1\Namespace2;

class ExampleClass extends ExampleClassParent implements ExampleClassInterface
{
//    public function publicMethod() {
//        return 1;
//    }
//
//    public function publicMethod2() {
//        $b = 'asdf{';
//        $c = "asdf}";
//        $a = "asdf{$b}";
//        return 1;
//    }
//
//    function Method() {
//    }
//
//    function MethodWithMoreCode() {
//        $a = $this->{"method{$b}name"};
//        do {
//            if (true) {
//                echo 'true';
//            } else {
//                echo 'false';
//            }
//        } while (0);
//    }

    abstract function AbstractMethod();

    private final function PrivateFinalMthod():null
    {}

    static protected function StaticProtectedMethod($a) {/* comment */}

    final
    public
    function
    PublicFinalFunction/*asdf*/($a,
//         $b,
         $c
    ) {
        return true && true;
    }


}

function functionOutsideClass(){
    return;
}
