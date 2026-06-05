<?php

declare(strict_types=1);

namespace OctopusLLM\Laravel\Events;

class ProviderFallback
{
    public function __construct(
        public readonly string $from,   // provider ID asal yang exhausted
        public readonly string $to,     // provider ID tujuan fallback
    ) {}
}
