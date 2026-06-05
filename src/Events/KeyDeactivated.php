<?php

declare(strict_types=1);

namespace OctopusLLM\Laravel\Events;

class KeyDeactivated
{
    public function __construct(
        public readonly string $provider,
        public readonly int    $keyIndex,
        public readonly string $reason,   // 'rate_limit' | 'auth_failure' | 'failure_threshold'
    ) {}
}
