<?php

declare(strict_types=1);

namespace OctopusLLM\Laravel\Exceptions;

class GatewayExhaustedException extends ProviderException
{
    public function __construct(string $message = 'All providers are exhausted.')
    {
        parent::__construct(
            provider: 'all',
            originalMessage: $message,
            message: $message,
        );
    }
}
