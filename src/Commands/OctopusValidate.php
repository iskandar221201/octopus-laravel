<?php

declare(strict_types=1);

namespace OctopusLLM\Laravel\Commands;

use Illuminate\Console\Command;

class OctopusValidate extends Command
{
    protected $signature = 'octopus:validate';

    protected $description = 'Validate .env API keys configuration for all providers';

    public function handle(): int
    {
        $providers = config('octopus.providers', []);
        $hasFailure = false;

        foreach ($providers as $provider) {
            $id = $provider['id'] ?? 'unknown';
            $keys = array_filter($provider['keys'] ?? [], fn ($k) => $k !== '');

            if (empty($keys)) {
                $this->warn("Provider [{$id}]: NO KEYS configured ✗");
                $hasFailure = true;
            } else {
                $count = count($keys);
                $this->info("Provider [{$id}]: {$count} key(s) configured ✓");
            }
        }

        return $hasFailure ? Command::FAILURE : Command::SUCCESS;
    }
}
