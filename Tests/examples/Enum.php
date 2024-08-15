<?php declare(strict_types=1);


namespace EnumNS;

use Psr\Log\LogLevel;

enum EnumExample: int implements EnumInterface
{
    use \GlobalEnumTrait;
    use Relative\EnumTrait;

}
