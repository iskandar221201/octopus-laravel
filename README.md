# Octopus LLM Gateway for Laravel

[![Latest Stable Version](https://img.shields.io/github/v/release/iskandar221201/octopus-laravel?color=blue)](https://github.com/iskandar221201/octopus-laravel/releases)
[![PHP Version](https://img.shields.io/badge/php-%3E%3D%208.3-8892bf.svg)](https://php.net)
[![Laravel Version](https://img.shields.io/badge/laravel-%5E11.0%20%7C%20%5E12.0%20%7C%20%5E13.0-ff2d20.svg)](https://laravel.com)
[![License](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)

**AI gateway with multi-key rotation, circuit breaker, and zero-cost free tier management.**

Octopus LLM Gateway allows you to seamlessly integrate multiple LLM providers (like Groq, OpenRouter, Cerebras) into your Laravel application with robust fallbacks, load-balancing key rotation, circuit breaking, and automated recovery, maximizing uptime and cost efficiency.

---

## 🚀 Key Features

* **🔄 Multi-Key Rotation**: Automatically rotates API keys per provider using a Least Recently Used (LRU) algorithm to maximize rate limit usage.
* **⚡ Circuit Breaker**: Disables keys automatically when successive HTTP failures occur (e.g., 500, timeout) and triggers events.
* **🛡️ Token Validation Guard**: Estimates input token counts and blocks oversized requests before hitting the remote APIs.
* **🔄 Fallback & Retry Mechanism**: Automatically falls back to lower-priority providers if a provider's keys are completely exhausted or rate-limited.
* **🤖 Automated Ping Recovery**: Background tasks test inactive keys against `/models` endpoints periodically and reactivate them upon recovery.
* **💻 Interactive Artisan CLI**: Commands to monitor gateway status, validate credentials, benchmark latencies, test chats, and recover keys.

---

## 📦 Installation

> [!IMPORTANT]
> This package requires **PHP 8.3 or higher** and **Laravel 11.0 or higher**.

To install the package, run the following command in your Laravel project:

```bash
composer require octopus-llm/laravel
```

Publish the configuration file:

```bash
php artisan vendor:publish --tag=octopus-config
```

---

## ⚙️ Configuration

The published configuration file is located at `config/octopus.php`. Below is a breakdown of the configuration keys and their environment variables:

```php
return [

    /*
    |--------------------------------------------------------------------------
    | LLM Providers
    |--------------------------------------------------------------------------
    | Define the list of providers in order of priority.
    | Lowest priority number (e.g., 1) is tried first.
    |
    */
    'providers' => [
        [
            'id'       => 'groq',
            'baseURL'  => 'https://api.groq.com/openai/v1',
            'model'    => env('GROQ_MODEL', 'llama-3.1-8b-instant'),
            'keys'     => explode(',', env('GROQ_KEYS', '')),
            'priority' => 1,
            'cooldown' => 60, // Key cooldown time in seconds before recovery
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
    ],

    /*
    |--------------------------------------------------------------------------
    | Request & Input Guards
    |--------------------------------------------------------------------------
    */
    'guard' => [
        'temperature'       => env('OCTOPUS_TEMPERATURE', 0.7),
        'timeout_ms'        => env('OCTOPUS_TIMEOUT_MS', 10000),
        'max_input_tokens'  => env('OCTOPUS_MAX_INPUT_TOKENS', 4000),
        'max_retries'       => env('OCTOPUS_MAX_RETRIES', 2),
        'max_output_tokens' => env('OCTOPUS_MAX_OUTPUT_TOKENS', 1000),
    ],

    /*
    |--------------------------------------------------------------------------
    | Circuit Breaker
    |--------------------------------------------------------------------------
    */
    'circuit_breaker' => [
        'failure_threshold' => env('OCTOPUS_CB_THRESHOLD', 3), // Consecutive fails to deactivate a key
    ],

    /*
    |--------------------------------------------------------------------------
    | State Storage
    |--------------------------------------------------------------------------
    */
    'storage'          => env('OCTOPUS_STORAGE', 'cache'),
    'storage_class'    => null, // Custom class implementing StorageInterface
    'cache_key_prefix' => env('OCTOPUS_CACHE_PREFIX', 'octopus_llm_state'),
    'cache_ttl'        => env('OCTOPUS_CACHE_TTL', 86400),

    'streaming' => env('OCTOPUS_STREAMING', true),

];
```

Define your API keys in your `.env` file as comma-separated values:

```env
GROQ_KEYS=test-key-1,test-key-2
OPENROUTER_KEYS=test-key-or-1
```

---

## 🛠️ Usage

### 💬 Sending Chat Requests

Use the `OctopusLLM` facade to send chat completion requests.

```php
use OctopusLLM\Laravel\Facades\OctopusLLM;

$response = OctopusLLM::chat([
    ['role' => 'user', 'content' => 'What is the speed of light?']
]);

echo $response->content; // Response text
echo $response->provider; // 'groq'
echo $response->model; // 'llama-3.1-8b-instant'
echo $response->latencyMs; // Latency in milliseconds
echo $response->attempts; // Attempts taken (e.g., 1)
```

### 🔀 Forcing a Specific Provider

You can bypass rotation sorting and force a specific provider for a single call:

```php
$response = OctopusLLM::chat(
    [['role' => 'user', 'content' => 'Hello']],
    ['forceProvider' => 'openrouter']
);
```

### 🌊 Streaming Responses

To stream completions token-by-token, specify the `streaming` option and pass an `onChunk` callback:

```php
OctopusLLM::chat(
    [['role' => 'user', 'content' => 'Write a short story.']],
    [
        'streaming' => true,
        'onChunk' => function (string $chunk) {
            echo $chunk;
            flush();
        }
    ]
);
```

### 📊 Monitoring Status

To inspect the current active/inactive status of all providers and API keys:

```php
$status = OctopusLLM::getStatus();

echo "Total Active Keys: " . $status->totalActive;
echo "Total Inactive Keys: " . $status->totalInactive;

foreach ($status->providers as $provider) {
    echo "Provider: " . $provider->id;
    foreach ($provider->keys as $key) {
        echo "Key Index: " . $key->index . " Status: " . $key->status;
    }
}
```

### 🤖 Manual Recovery & Ping

You can manually trigger pings or run the recovery engine inside code:

```php
// Ping a specific key
$isAlive = OctopusLLM::ping('groq', 0); // returns boolean

// Run the full recovery process (checks cooldowns and pings inactive keys)
$report = OctopusLLM::runRecovery();
```

---

## 💻 Artisan Commands

Octopus LLM comes with a complete suite of Artisan command-line tools:

| Command | Description |
|---|---|
| `php artisan octopus:validate` | Validates API keys configuration in `.env` |
| `php artisan octopus:status` | Shows table of status, failure counts, and last used times for all keys (use `--json` for raw data) |
| `php artisan octopus:benchmark` | Benchmarks request latency for each provider (use `--samples=5` to customize) |
| `php artisan octopus:test` | Sends a prompt request to the gateway to test end-to-end connectivity |
| `php artisan octopus:recover` | Manually triggers the background key recovery checks |

---

## 🧪 Testing

Run the PHPUnit test suite to verify code correctness and coverage:

```bash
vendor/bin/phpunit
```

---

## 📄 License

The MIT License (MIT). Please see [License File](LICENSE) for more information.
