<?php

declare(strict_types=1);

namespace OctopusLLM\Laravel\Commands;

use Exception;
use Illuminate\Console\Command;
use OctopusLLM\Laravel\OctopusGateway;

class OctopusTest extends Command
{
    protected $signature = 'octopus:test {--prompt=Hello, who are you? : Test prompt to send}';

    protected $description = 'Send a test prompt to the AI gateway';

    public function handle(OctopusGateway $gateway): int
    {
        $prompt = $this->option('prompt');

        $this->info("Sending test prompt: \"{$prompt}\"");

        try {
            $response = $gateway->chat([['role' => 'user', 'content' => $prompt]]);

            $this->line('');
            $this->info('=== Response ===');
            $this->line($response->content);
            $this->line('');

            $this->table(
                ['Field', 'Value'],
                [
                    ['Provider',      $response->provider],
                    ['Key Index',     $response->keyIndex],
                    ['Model',         $response->model],
                    ['Latency',       $response->latencyMs . 'ms'],
                    ['Fallback Used', $response->fallbackUsed ? 'Yes' : 'No'],
                    ['Attempts',      $response->attempts],
                ]
            );

            return Command::SUCCESS;
        } catch (Exception $e) {
            $this->error('Test failed: ' . $e->getMessage());

            return Command::FAILURE;
        }
    }
}
