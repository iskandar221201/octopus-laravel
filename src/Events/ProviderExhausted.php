<?php

declare(strict_types=1);

namespace OctopusLLM\Laravel\Events;

class ProviderExhausted
{
    public function __construct(
        public readonly string $provider,
    ) {}
}
