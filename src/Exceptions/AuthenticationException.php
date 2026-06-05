<?php

declare(strict_types=1);

namespace OctopusLLM\Laravel\Exceptions;

class AuthenticationException extends ProviderException
{
    public function __construct(
        string $provider,
        public readonly int $keyIndex,
    ) {
        parent::__construct(
            provider: $provider,
            originalMessage: "Authentication failed on provider [{$provider}], key index [{$keyIndex}].",
            message: "Authentication failed on provider [{$provider}], key index [{$keyIndex}].",
        );
    }
}
