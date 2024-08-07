<?php

namespace YRV\Autoloader\Parser\Components;

class ClassComponent extends InterfaceComponent
{
    public array $interfaces = [];
    public ?string $extends = null;
    public array $traits = [];
    public array $methods = [];
    public bool $isAbstract = false;
    public bool $isFinal = false;
    public array $constants = [];
    public array $properties = [];

    public function getCalledFunctions(): array
    {
        $functions = [];
        foreach ($this->methods as $component) {
            $functions = array_merge($functions, $component->getCalledFunctions());
        }

        return $functions;
    }
}