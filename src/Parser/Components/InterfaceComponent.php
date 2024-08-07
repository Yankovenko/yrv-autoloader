<?php

namespace YRV\Autoloader\Parser\Components;

class InterfaceComponent extends PhpComponent
{
    public array $methods = [];
    public ?string $extends = null;
    public array $usedConstants = [];

}