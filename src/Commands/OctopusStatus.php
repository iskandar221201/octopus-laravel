<?php

declare(strict_types=1);

namespace OctopusLLM\Laravel\Commands;

use Illuminate\Console\Command;
use OctopusLLM\Laravel\OctopusGateway;

class OctopusStatus extends Command
{
    protected $signature = 'octopus:status {--json : Output as JSON}';

    protected $description = 'Show active/inactive status per provider and key';

    public function handle(OctopusGateway $gateway): int
    {
        $status = $gateway->getStatus();

        if ($this->option('json')) {
            $data = array_map(function ($provider) {
                return [
                    'id'       => $provider->id,
                    'priority' => $provider->priority,
                    'keys'     => array_map(function ($key) {
                        return [
                            'index'     => $key->index,
                            'status'    => $key->status,
                            'failures'  => $key->failures,
                            'lastUsed'  => $key->lastUsed,
                            'markedAt'  => $key->markedAt,
                        ];
                    }, $key->keys ?? $provider->keys),
                ];
            }, $status->providers);

            $this->line(json_encode($data, JSON_PRETTY_PRINT));

            return Command::SUCCESS;
        }

        $rows = [];
        $totalActive = 0;
        $totalInactive = 0;

        foreach ($status->providers as $provider) {
            foreach ($provider->keys as $key) {
                $isActive = $key->status === 'active';

                if ($isActive) {
                    $totalActive++;
                } else {
                    $totalInactive++;
                }

                $statusLabel = $isActive
                    ? '<fg=green>active</>'
                    : '<fg=red>inactive</>';

                $rows[] = [
                    $provider->id,
                    $provider->priority,
                    $key->index,
                    $statusLabel,
                    $key->failures,
                    $key->lastUsed ?? '-',
                    $key->markedAt ?? '-',
                ];
            }
        }

        $this->table(
            ['Provider', 'Priority', 'Key Index', 'Status', 'Failures', 'Last Used', 'Marked At'],
            $rows
        );

        $this->line("Total Active: {$totalActive} | Total Inactive: {$totalInactive}");

        return Command::SUCCESS;
    }
}
