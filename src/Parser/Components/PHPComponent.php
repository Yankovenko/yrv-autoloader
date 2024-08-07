<?php

namespace YRV\Autoloader\Parser\Components;

abstract class PhpComponent
{
    public $name;
    public $tokenStartPos;
    public $tokenEndPos;
    public array $constants = [];
    public array $properties = [];


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