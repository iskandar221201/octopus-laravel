<?php

declare(strict_types=1);

namespace OctopusLLM\Laravel\Exceptions;

class TimeoutException extends ProviderException
{
    public function __construct(
        string $provider,
        public readonly int $timeoutMs,
    ) {
        parent::__construct(
            provider: $provider,
            originalMessage: "Request to [{$provider}] timed out after {$timeoutMs}ms.",
            message: "Request to [{$provider}] timed out after {$timeoutMs}ms.",
        );
    }
}
