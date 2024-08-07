<?php

namespace YRV\Autoloader\Parser\Components;

class FunctionComponent extends PhpComponent
{
    public array $params = [];
    public $returnType;
    public $visibility;
    public $isStatic = false;
    public $isAbstract = false;
    public $isFinal = false;
    public array $callFunctions = [];
    public array $instantiated = [];
    public array $usedConstants = [];

    public function getCalledFunctions(): array
    {
        return $this->callFunctions ?? [];
    }
}