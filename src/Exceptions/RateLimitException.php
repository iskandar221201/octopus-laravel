<?php

declare(strict_types=1);

namespace OctopusLLM\Laravel\Exceptions;

class RateLimitException extends ProviderException
{
    public function __construct(
        string $provider,
        public readonly ?int $retryAfter = null,
    ) {
        parent::__construct(
            provider: $provider,
            originalMessage: "Rate limit hit on provider [{$provider}].",
            message: "Rate limit hit on provider [{$provider}].",
        );
    }
}
