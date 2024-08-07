<?php

namespace YRV\Autoloader\Parser\Components;

class PropertyComponent extends VariableComponent
{
    public bool $isStatic = false;
    public $visibility;
}