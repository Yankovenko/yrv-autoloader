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
    public $uses = [];

    /** @var ClassComponent[]  */
    public $classes = [];

    /** @var TraitComponent[] */
    public $traits = [];

    /** @var FunctionComponent[] */
    public $functions = [];

    /** @var InterfaceComponent[]  */
    public $interfaces = [];

    /** @var array  */
    public $constants = [];

    /** @var array  */
    public $usedConstants = [];

    /** @var array  */
    public $callFunctions = [];

    public function getDeclaredFunctions(): array
    {
        $functions = [];
        $prefix = $this->name ? $this->name . '\\' : '';

        foreach ($this->functions as $function) {
            if ($function->name) {
                $functions[] = $prefix . $function->name;
            }
        }
        return $functions;
    }

    public function getDeclaredConstants(): array
    {
        $prefix = $this->name ? $this->name . '\\' : '';
        if (!$prefix) {
            return $this->constants;
        }

        return array_map(function($name) use ($prefix) {
            return $prefix.$name;
        }, $this->constants);
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
        $functions = array_unique($functions);

        return $this->nameNormalize($functions, true);
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

        $relations = array_unique($relations);
        return $this->nameNormalize($relations);

    }

    protected function nameNormalize(array $names, bool $forFunction = false): array
    {
        $results = [];
        $prefix = $this->name ? $this->name . '\\' : '';
        foreach ($names as $name) {
            if (isset($this->uses[$name])) {
                $results[] = $this->uses[$name];
            } elseif ($forFunction || strpos($name, '\\') === 0) {
                $results[] = $name;
            } else {
                $results[] = $prefix . $name;
            }
        }
        return $results;
    }
}

class InterfaceComponent extends PhpComponent
{
    public $methods = [];
    public $extends = [];
    public $usedConstatns = [];

}

class TraitComponent extends PhpComponent
{
    public $methods = [];
    public $traits = [];
    public $properties = [];
    public $callFunctions = [];

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
    public $interfaces = [];
    public $extends;
    public $traits = [];
    public $methods = [];
    public $isAnonym = false;
    public $isAbstract = false;
    public $isFinal = false;
    public $constants = [];
    public $properties = [];

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

    public function getCalledFunctions(): array
    {
        return $this->callFunctions ?? [];
    }
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
