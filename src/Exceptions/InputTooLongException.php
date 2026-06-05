<?php

declare(strict_types=1);

namespace OctopusLLM\Laravel\Exceptions;

class InputTooLongException extends ProviderException
{
    public function __construct(
        public readonly int $estimated,
        public readonly int $limit,
    ) {
        parent::__construct(
            provider: 'none',
            originalMessage: "Input too long: estimated {$estimated} tokens, limit is {$limit}.",
            message: "Input too long: estimated {$estimated} tokens, limit is {$limit}.",
        );
    }
}
