<?php

declare(strict_types=1);

namespace OctopusLLM\Laravel\DTOs;

class ProviderStatus
{
    public function __construct(
        public readonly string $id,
        public readonly int    $priority,
        public readonly array  $keys,          // KeyStatus[]
    ) {}
}
