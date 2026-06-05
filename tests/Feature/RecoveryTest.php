<?php

declare(strict_types=1);

namespace OctopusLLM\Laravel\Tests\Feature;

use Illuminate\Support\Facades\Http;
use OctopusLLM\Laravel\DTOs\RecoveryReport;
use OctopusLLM\Laravel\Exceptions\GatewayExhaustedException;
use OctopusLLM\Laravel\Facades\OctopusLLM;
use OctopusLLM\Laravel\Tests\TestCase;

class RecoveryTest extends TestCase
{
    public function test_ping_returns_true_and_recovers_key(): void
    {
        // 1. Fake specific models endpoint to return 200, but general completions to 429
        Http::fake([
            'api.groq.com/openai/v1/models' => Http::response(['data' => []], 200),
            'api.groq.com/*' => Http::response([], 429),
            'openrouter.ai/*' => Http::response([], 429),
        ]);

        try {
            OctopusLLM::chat([['role' => 'user', 'content' => 'Hi']]);
        } catch (GatewayExhaustedException $e) {
            // expected
        }

        // Verify it is inactive first
        $statusBefore = OctopusLLM::getStatus();
        $groqKey0Before = $statusBefore->providers[0]->keys[0];
        $this->assertSame('inactive', $groqKey0Before->status);

        // Call ping - it will match the models endpoint which returns 200
        $result = OctopusLLM::ping('groq', 0);
        $this->assertTrue($result);

        // Verify it is active now
        $statusAfter = OctopusLLM::getStatus();
        $groqKey0After = $statusAfter->providers[0]->keys[0];
        $this->assertSame('active', $groqKey0After->status);
    }

    public function test_ping_returns_false_when_models_endpoint_fails(): void
    {
        Http::fake([
            'api.groq.com/openai/v1/models' => Http::response([], 500),
        ]);

        $result = OctopusLLM::ping('groq', 0);
        $this->assertFalse($result);
    }

    public function test_run_recovery_returns_report(): void
    {
        Http::fake([
            '*' => Http::response(['data' => []], 200),
        ]);

        $report = OctopusLLM::runRecovery();

        $this->assertInstanceOf(RecoveryReport::class, $report);
        $this->assertSame(0, $report->total);
    }

    public function test_run_recovery_skips_keys_within_cooldown(): void
    {
        // 1. Mark key 0 of groq as inactive
        Http::fake([
            'api.groq.com/openai/v1/models' => Http::response(['data' => []], 200),
            'api.groq.com/*' => Http::response([], 429),
            'openrouter.ai/*' => Http::response([], 429),
        ]);

        try {
            OctopusLLM::chat([['role' => 'user', 'content' => 'Hi']]);
        } catch (GatewayExhaustedException $e) {
            // expected
        }

        // Verify key is inactive
        $status = OctopusLLM::getStatus();
        $this->assertSame('inactive', $status->providers[0]->keys[0]->status);

        // 2. Run recovery immediately
        $report = OctopusLLM::runRecovery();

        // Should be skipped because markedAt is fresh and cooldown (60s) has not passed
        $this->assertSame(0, $report->total);
    }
}
