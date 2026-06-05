<?php

return [

    'providers' => [
        [
            'id'       => 'groq',
            'baseURL'  => 'https://api.groq.com/openai/v1',
            'model'    => env('GROQ_MODEL', 'llama-3.1-8b-instant'),
            'keys'     => explode(',', env('GROQ_KEYS', '')),
            'priority' => 1,
            'cooldown' => 60,
        ],
        [
            'id'           => 'openrouter',
            'baseURL'      => 'https://openrouter.ai/api/v1',
            'model'        => env('OPENROUTER_MODEL', 'mistralai/mistral-7b-instruct:free'),
            'keys'         => explode(',', env('OPENROUTER_KEYS', '')),
            'priority'     => 2,
            'cooldown'     => 120,
            'extraHeaders' => ['HTTP-Referer' => env('APP_URL', 'http://localhost')],
        ],
        [
            'id'       => 'cerebras',
            'baseURL'  => 'https://api.cerebras.ai/v1',
            'model'    => env('CEREBRAS_MODEL', 'llama-3.1-8b'),
            'keys'     => explode(',', env('CEREBRAS_KEYS', '')),
            'priority' => 3,
            'cooldown' => 60,
        ],
    ],

    'guard' => [
        'temperature'       => env('OCTOPUS_TEMPERATURE', 0.7),
        'timeout_ms'        => env('OCTOPUS_TIMEOUT_MS', 10000),
        'max_input_tokens'  => env('OCTOPUS_MAX_INPUT_TOKENS', 4000),
        'max_retries'       => env('OCTOPUS_MAX_RETRIES', 2),
        'max_output_tokens' => env('OCTOPUS_MAX_OUTPUT_TOKENS', 1000),
    ],

    'circuit_breaker' => [
        'failure_threshold' => env('OCTOPUS_CB_THRESHOLD', 3),
    ],

    'recovery' => [
        'ping_timeout' => env('OCTOPUS_PING_TIMEOUT', 5),
    ],

    'storage'          => env('OCTOPUS_STORAGE', 'cache'),
    'storage_class'    => null,
    'cache_key_prefix' => env('OCTOPUS_CACHE_PREFIX', 'octopus_llm_state'),
    'cache_ttl'        => env('OCTOPUS_CACHE_TTL', 86400),

    'streaming' => env('OCTOPUS_STREAMING', true),

];
