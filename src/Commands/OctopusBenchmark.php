<?php

declare(strict_types=1);

namespace OctopusLLM\Laravel\Commands;

use Exception;
use Illuminate\Console\Command;
use OctopusLLM\Laravel\OctopusGateway;

class OctopusBenchmark extends Command
{
    protected $signature = 'octopus:benchmark {--samples=3 : Number of requests per provider}';

    protected $description = 'Measure latency benchmark per provider';

    public function handle(OctopusGateway $gateway): int
    {
        $samples = (int) $this->option('samples');
        $providers = config('octopus.providers', []);

        foreach ($providers as $provider) {
            $id = $provider['id'] ?? 'unknown';
            $latencies = [];

            for ($i = 0; $i < $samples; $i++) {
                try {
                    $response = $gateway->chat(
                        [['role' => 'user', 'content' => 'Reply with one word: OK']],
                        ['forceProvider' => $id, 'maxTokens' => 5]
                    );

                    $latencies[] = $response->latencyMs;
                } catch (Exception $e) {
                    $this->warn("Sample failed: " . $e->getMessage());
                }
            }

            if (empty($latencies)) {
                $this->warn("Provider [{$id}]: all samples failed");
                continue;
            }

            $avg = array_sum($latencies) / count($latencies);
            $count = count($latencies);

            $this->info("Provider [{$id}]: avg {$avg}ms over {$count} samples");
        }

        return Command::SUCCESS;
    }
}
