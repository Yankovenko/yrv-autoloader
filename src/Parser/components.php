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
    /** @var array  */
    public array $uses = [];

    /** @var ClassComponent[]  */
    public array $classes = [];

    /** @var TraitComponent[] */
    public array $traits = [];

    /** @var FunctionComponent[] */
    public array $functions = [];

    /** @var InterfaceComponent[]  */
    public array $interfaces = [];

    /** @var array  */
    public array $constants = [];

    /** @var array  */
    public array $usedConstants = [];

    /** @var array  */
    public array $callFunctions = [];

    public function getNamespace(): string
    {
        return $this->name;
    }

    public function getDeclaredFunctions(): array
    {
        return array_map(fn($object) => $object->name, $this->functions);
    }

    public function getDeclaredConstants(): array
    {
        return $this->constants;
    }

    public function getUsedConstants($includeObjects = false): array
    {
        $constants = $this->usedConstants ?? [];

        foreach ($this->functions as $component) {
            $constants = array_merge($constants, $component->usedConstants ?? []);
        }

        if ($includeObjects) {
            foreach ($this->classes as $component) {
                $constants = array_merge($constants, $component->usedConstants ?? []);
            }
            foreach ($this->interfaces as $component) {
                $constants = array_merge($constants, $component->usedConstants ?? []);
            }
        }
        return array_unique($constants);
    }

    public function getCalledFunctions($includeObjects = false): array
    {
        $functions = [];
        if (!empty($this->callFunctions)) {
            $functions = $this->callFunctions;
        }
        if ($includeObjects) {
            foreach ($this->classes as $component) {
                $functions = array_merge($functions, $component->getCalledFunctions());
            }
            foreach ($this->traits as $component) {
                $functions = array_merge($functions, $component->getCalledFunctions());
            }
        }
        return array_unique($functions);
    }

    public function getDeclaredObjects(): array
    {
        $prefix = $this->name ? $this->name . '\\' : '';
        $names = [];
        foreach ($this->classes as $component) {
            $names[] = $prefix . $component->name;
        }
        foreach ($this->traits as $component) {
            $names[] = $prefix . $component->name;
        }
        foreach ($this->interfaces as $component) {
            $names[] = $prefix . $component->name;
        }

        return array_unique($names);

    }

    public function getRelationsObjects(): array
    {
        $relations = [];
        foreach ($this->classes as $component) {
            $relations = array_merge($relations, $component->interfaces ?? []);
            $relations = array_merge($relations, $component->extends ? [$component->extends]:[]);
            $relations = array_merge($relations, $component->traits ?? []);
        }
        foreach ($this->interfaces as $component) {
            $relations = array_merge($relations, $component->extends ? [$component->extends]:[]);
        }
        foreach ($this->traits as $component) {
            $relations = array_merge($relations, $component->traits ?? []);
        }

        return array_unique($relations);

    }

}

class InterfaceComponent extends PhpComponent
{
    public array $methods = [];
    public ?string $extends = null;
    public array $usedConstants = [];

}

class TraitComponent extends PhpComponent
{
    public array $methods = [];
    public array $traits = [];
    public array $properties = [];
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

class VariableComponent extends PhpComponent
{
    public $type;
    public bool $hasDefaultValue = false;
    public $defaultValue;
}

class ParamComponent extends VariableComponent
{
    public bool $isVariadic = false;
}

class PropertyComponent extends VariableComponent
{
    public bool $isStatic = false;
    public $visibility;
}
