<?php

declare(strict_types=1);

namespace OctopusLLM\Laravel\Commands;

use Illuminate\Console\Command;
use OctopusLLM\Laravel\OctopusGateway;

class OctopusRecover extends Command
{
    protected $signature = 'octopus:recover';

    protected $description = 'Trigger manual recovery check for inactive keys';

    public function handle(OctopusGateway $gateway): int
    {
        $this->info('Running recovery check...');

        $report = $gateway->runRecovery();

        $this->line("Total inactive keys checked: {$report->total}");

        if ($report->total === 0) {
            $this->info('No inactive keys to check.');

            return Command::SUCCESS;
        }

        foreach ($report->recovered as $item) {
            $this->info("✓ Recovered: provider [{$item['provider']}] key [{$item['keyIndex']}]");
        }

        foreach ($report->failed as $item) {
            $this->warn("✗ Still inactive: provider [{$item['provider']}] key [{$item['keyIndex']}]");
        }

        return Command::SUCCESS;
    }
}
