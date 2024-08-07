<?php

namespace YRV\Autoloader\Parser\Components;

class TraitComponent extends PhpComponent
{
    public array $methods = [];
    public array $traits = [];
    public array $callFunctions = [];

    public function getCalledFunctions($includeObjects = false): array
    {
        $functions = [];
        foreach ($this->methods as $component) {
            $functions = array_merge($functions, $component->getCalledFunctions());
        }

        return $functions;
    }
}