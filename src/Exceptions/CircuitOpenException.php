<?php

declare(strict_types=1);

namespace OctopusLLM\Laravel\Exceptions;

class CircuitOpenException extends ProviderException
{
    public function __construct(
        string $provider,
        public readonly int $keyIndex,
        public readonly ?int $retryAt = null,
    ) {
        parent::__construct(
            provider: $provider,
            originalMessage: "Circuit is OPEN for provider [{$provider}], key [{$keyIndex}].",
            message: "Circuit is OPEN for provider [{$provider}], key [{$keyIndex}].",
        );
    }
}
