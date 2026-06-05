<?php

declare(strict_types=1);

namespace OctopusLLM\Laravel\Tests\Feature;

use Illuminate\Support\Facades\Http;
use OctopusLLM\Laravel\DTOs\ChatResponse;
use OctopusLLM\Laravel\DTOs\StatusResponse;
use OctopusLLM\Laravel\Exceptions\GatewayExhaustedException;
use OctopusLLM\Laravel\Facades\OctopusLLM;
use OctopusLLM\Laravel\Tests\TestCase;

class GatewayTest extends TestCase
{
    public function test_chat_returns_response_on_success(): void
    {
        Http::fake([
            'api.groq.com/*' => Http::response([
                'choices' => [['message' => ['content' => 'Hello World!']]],
                'model' => 'llama-3.1-8b-instant'
            ], 200)
        ]);

        $response = OctopusLLM::chat([['role' => 'user', 'content' => 'Hi']]);

        $this->assertInstanceOf(ChatResponse::class, $response);
        $this->assertSame('Hello World!', $response->content);
        $this->assertSame('groq', $response->provider);
        $this->assertFalse($response->fallbackUsed);
        $this->assertSame(1, $response->attempts);
    }

    public function test_chat_falls_back_to_next_provider_on_rate_limit(): void
    {
        Http::fake([
            'api.groq.com/*' => Http::response([], 429),
            'openrouter.ai/*' => Http::response([
                'choices' => [['message' => ['content' => 'Fallback response']]],
                'model' => 'mistralai/mistral-7b-instruct:free'
            ], 200),
        ]);

        $response = OctopusLLM::chat([['role' => 'user', 'content' => 'Hi']]);

        $this->assertSame('openrouter', $response->provider);
        $this->assertTrue($response->fallbackUsed);
    }

    public function test_chat_throws_gateway_exhausted_when_all_providers_fail(): void
    {
        Http::fake([
            'api.groq.com/*' => Http::response([], 500),
            'openrouter.ai/*' => Http::response([], 500),
        ]);

        $this->expectException(GatewayExhaustedException::class);

        OctopusLLM::chat([['role' => 'user', 'content' => 'Hi']]);
    }

    public function test_chat_marks_key_inactive_after_rate_limit(): void
    {
        Http::fake([
            'api.groq.com/*' => Http::response([], 429),
            'openrouter.ai/*' => Http::response([], 429),
        ]);

        for ($i = 0; $i < 2; $i++) {
            try {
                OctopusLLM::chat([['role' => 'user', 'content' => 'Hi']]);
                $this->fail('Expected GatewayExhaustedException to be thrown.');
            } catch (GatewayExhaustedException $e) {
                // expected
            }
        }

        $status = OctopusLLM::getStatus();
        
        // Find groq provider status
        $groqProvider = null;
        foreach ($status->providers as $p) {
            if ($p->id === 'groq') {
                $groqProvider = $p;
                break;
            }
        }

        $this->assertNotNull($groqProvider);
        foreach ($groqProvider->keys as $keyStatus) {
            $this->assertSame('inactive', $keyStatus->status);
        }
    }

    public function test_get_status_returns_status_response(): void
    {
        $status = OctopusLLM::getStatus();

        $this->assertInstanceOf(StatusResponse::class, $status);
        $this->assertCount(2, $status->providers);
        $this->assertSame(3, $status->totalActive);
        $this->assertSame(0, $status->totalInactive);
    }

    public function test_key_rotates_on_consecutive_requests(): void
    {
        Http::fake([
            'api.groq.com/*' => Http::response([
                'choices' => [['message' => ['content' => 'OK']]],
                'model' => 'llama-3.1-8b-instant'
            ], 200)
        ]);

        $r1 = OctopusLLM::chat([['role' => 'user', 'content' => 'req1']]);
        $r2 = OctopusLLM::chat([['role' => 'user', 'content' => 'req2']]);

        $this->assertSame('groq', $r1->provider);
        $this->assertSame('groq', $r2->provider);
        $this->assertNotEquals($r1->keyIndex, $r2->keyIndex);
    }
}
