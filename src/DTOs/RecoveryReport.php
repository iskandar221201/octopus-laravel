<?php

declare(strict_types=1);

namespace OctopusLLM\Laravel\DTOs;

class RecoveryReport
{
    public function __construct(
        public readonly array $recovered,  // [['provider' => 'groq', 'keyIndex' => 1], ...]
        public readonly array $failed,     // idem format
        public readonly int   $total,      // jumlah inactive key yang dicoba ping
    ) {}
}
