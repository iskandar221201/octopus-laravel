<?php

declare(strict_types=1);

namespace OctopusLLM\Laravel\DTOs;

class KeyStatus
{
    public function __construct(
        public readonly int     $index,
        public readonly string  $status,       // 'active' | 'inactive'
        public readonly int     $failureCount,
        public readonly ?string $lastUsed,     // ISO 8601 string atau null
        public readonly ?string $markedAt,     // ISO 8601 string atau null
    ) {}
}
