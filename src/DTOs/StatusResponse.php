<?php

declare(strict_types=1);

namespace OctopusLLM\Laravel\DTOs;

class StatusResponse
{
    public function __construct(
        public readonly array $providers,      // ProviderStatus[]
        public readonly int   $totalActive,
        public readonly int   $totalInactive,
    ) {}
}
