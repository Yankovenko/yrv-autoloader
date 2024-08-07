<?php

namespace YRV\Autoloader\Parser\Components;

class VariableComponent extends PhpComponent
{
    public $type;
    public bool $hasDefaultValue = false;
    public $defaultValue;
}