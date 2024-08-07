<?php

namespace YRV2\YSiteORM\Models\DocBase\Methods;

use Psr\Http\Server\MiddlewareInterface;
use YRV2\Http\ServerRequest;
use YRV2\Logger;
use YRV2\YSiteORM\Components\Method\MethodInterface;

class AdminSearch extends \YRV2\YSiteORM\Models\OA\Methods\AdminSearch implements MethodInterface, MiddlewareInterface
{
    use Logger\LoggerTrait;
}