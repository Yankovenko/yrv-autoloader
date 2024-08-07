<?php

namespace YRV\Autoloader\Parser\Components;

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
