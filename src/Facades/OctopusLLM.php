<?php

declare(strict_types=1);

namespace OctopusLLM\Laravel\Facades;

use Illuminate\Support\Facades\Facade;
use OctopusLLM\Laravel\OctopusGateway;

/**
 * @see \OctopusLLM\Laravel\OctopusGateway
 */
class OctopusLLM extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return OctopusGateway::class;
    }
}
