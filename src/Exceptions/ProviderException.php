<?php

declare(strict_types=1);

namespace OctopusLLM\Laravel\Exceptions;

class ProviderException extends \RuntimeException
{
    public function __construct(
        public readonly string $provider,
        public readonly string $originalMessage,
        string $message = '',
        int $code = 0,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message ?: $originalMessage, $code, $previous);
    }
}
