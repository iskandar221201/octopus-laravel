<?php

declare(strict_types=1);

namespace OctopusLLM\Laravel\Tests;

use Orchestra\Testbench\TestCase as OrchestraTestCase;
use OctopusLLM\Laravel\OctopusLLMServiceProvider;

abstract class TestCase extends OrchestraTestCase
{
    protected function getPackageProviders($app): array
    {
        return [OctopusLLMServiceProvider::class];
    }

    protected function getPackageAliases($app): array
    {
        return [
            'OctopusLLM' => \OctopusLLM\Laravel\Facades\OctopusLLM::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        // Pakai array cache agar tidak ada persistence antar test
        $app['config']->set('cache.default', 'array');

        // Default provider config untuk testing
        $app['config']->set('octopus.providers', [
            [
                'id'       => 'groq',
                'baseURL'  => 'https://api.groq.com/openai/v1',
                'model'    => 'llama-3.1-8b-instant',
                'keys'     => ['test-key-groq-1', 'test-key-groq-2'],
                'priority' => 1,
                'cooldown' => 60,
            ],
            [
                'id'       => 'openrouter',
                'baseURL'  => 'https://openrouter.ai/api/v1',
                'model'    => 'mistralai/mistral-7b-instruct:free',
                'keys'     => ['test-key-or-1'],
                'priority' => 2,
                'cooldown' => 120,
            ],
        ]);

        $app['config']->set('octopus.guard', [
            'temperature'       => 0.7,
            'timeout_ms'        => 10000,
            'max_input_tokens'  => 4000,
            'max_retries'       => 2,
            'max_output_tokens' => 1000,
        ]);

        $app['config']->set('octopus.circuit_breaker', [
            'failure_threshold' => 3,
        ]);

        $app['config']->set('octopus.recovery', [
            'ping_timeout' => 5,
        ]);

        $app['config']->set('octopus.storage', 'cache');
        $app['config']->set('octopus.cache_key_prefix', 'octopus_test');
        $app['config']->set('octopus.cache_ttl', 86400);
        $app['config']->set('octopus.streaming', false);
    }
}
