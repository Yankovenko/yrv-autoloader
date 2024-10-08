<?php

namespace YRV\Autoloader\Tests;

use \PHPUnit\Framework\TestCase as BaseCase;

abstract class TestCase extends BaseCase
{
    public function __construct(?string $name = null, array $data = [], $dataName = '')
    {
        parent::__construct($name, $data, $dataName);
        require_once __DIR__ . '/../src/Parser/Scanner.php';
    }
}
