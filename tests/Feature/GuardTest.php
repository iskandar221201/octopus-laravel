<?php

declare(strict_types=1);

namespace OctopusLLM\Laravel\Tests\Feature;

use Illuminate\Support\Facades\Http;
use OctopusLLM\Laravel\Exceptions\InputTooLongException;
use OctopusLLM\Laravel\Facades\OctopusLLM;
use OctopusLLM\Laravel\Tests\TestCase;

class GuardTest extends TestCase
{
    public function test_chat_throws_input_too_long_when_exceeds_limit(): void
    {
        $longContent = str_repeat('a', 16004);

        try {
            OctopusLLM::chat([['role' => 'user', 'content' => $longContent]]);
            $this->fail('Expected InputTooLongException to be thrown.');
        } catch (InputTooLongException $e) {
            $this->assertGreaterThan(4000, $e->estimated);
            $this->assertSame(4000, $e->limit);
        }
    }

    public function test_chat_does_not_throw_when_input_within_limit(): void
    {
        Http::fake([
            'api.groq.com/*' => Http::response([
                'choices' => [['message' => ['content' => 'OK']]],
                'model' => 'llama'
            ], 200)
        ]);

        $content = str_repeat('a', 100);
        $response = OctopusLLM::chat([['role' => 'user', 'content' => $content]]);

        $this->assertNotNull($response);
    }
}
