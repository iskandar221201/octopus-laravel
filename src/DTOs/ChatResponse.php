<?php

declare(strict_types=1);

namespace OctopusLLM\Laravel\DTOs;

class ChatResponse
{
    public function __construct(
        public readonly string $content,
        public readonly string $provider,
        public readonly int    $keyIndex,
        public readonly int    $latencyMs,
        public readonly string $model,
        public readonly bool   $fallbackUsed,
        public readonly int    $attempts,
    ) {}
}
