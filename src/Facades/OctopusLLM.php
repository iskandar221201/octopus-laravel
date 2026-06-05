<?php

namespace OctopusLLM\Laravel\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \OctopusLLM\Laravel\OctopusGateway
 */
class OctopusLLM extends Facade
{
    /**
     * Get the registered name of the component.
     */
    protected static function getFacadeAccessor(): string
    {
        return \OctopusLLM\Laravel\OctopusGateway::class;
    }
}
