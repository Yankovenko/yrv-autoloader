<?php
namespace YRV\Autoloader\Parser;


abstract class PhpComponent
{
    public $name;
    public $tokenStartPos;
    public $tokenEndPos;

    public function toArray(): array
    {
        $data = ['_' => get_class($this)];

        foreach (array_keys(get_object_vars($this)) as $name) {
            if ($this->$name instanceof PhpComponent) {
                $data[$name] = $this->$name->toArray();
            } elseif (is_array($this->$name) && !empty(is_array($this->$name))) {
                $data[$name] = array_map(function ($value) {
                    return $value instanceof PhpComponent
                        ? $value->toArray()
                        : $value;
                }, $this->$name);
            } else {
                $data[$name] = $this->$name;
            }
            if(empty($data[$name])) {
                unset($data[$name]);
            }
        }

        return $data;
    }
}

class NamespaceComponent extends PhpComponent
{
    public $namespace;
    public $uses = [];
    public $classes = [];
    public $traits = [];
    public $functions = [];
    public $interfaces = [];
    public $constants = [];
    public $callFunctions = [];
}

class InterfaceComponent extends PhpComponent
{
    public $namespace;
    public $methods = [];
    public $extends;
}

class TraitComponent extends PhpComponent
{
    public $namespace;
    public $methods = [];
    public $traits = [];
    public $properties = [];
    public $callFunctions = [];
}

class ClassComponent extends InterfaceComponent
{
    public $interfaces = [];
    public $extends;
    public $traits = [];
    public $isAnonym = false;
    public $isAbstract = false;
    public $isFinal = false;
    public $constants = [];
    public $properties = [];
}

class FunctionComponent extends PhpComponent
{
    public $params = [];
    public $returnType;
    public $visibility;
    public $isStatic = false;
    public $isAnonym = false;
    public $isAbstract = false;
    public $isFinal = false;
    public $callFunctions = [];
    public $instantiated = [];
    public $usedConstants = [];
}

class VariableComponent extends PhpComponent
{
    public $type;
    public $hasDefaultValue = false;
    public $defaultValue;
}

class ParamComponent extends VariableComponent
{
    public $isVariadic = false;
}

class PropertyComponent extends VariableComponent
{
    public $isStatic = false;
    public $visibility;
}
