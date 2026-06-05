<?php

namespace OctopusLLM\Laravel;

use OctopusLLM\Laravel\Contracts\StorageInterface;
use OctopusLLM\Laravel\Contracts\HttpClientInterface;

class OctopusGateway
{
    public function __construct(
        protected array $config,
        protected StorageInterface $storage,
        protected HttpClientInterface $http,
    ) {}

    // ─── Step 2 ────────────────────────────────────────────────────────────────

    protected function loadState(): array
    {
        $state = $this->storage->load();
        if (empty($state)) {
            $state = [];
            foreach ($this->config['providers'] as $provider) {
                $state[$provider['id']]['keys'] = array_map(fn($i) => [
                    'status'       => 'active',
                    'failureCount' => 0,
                    'lastUsed'     => null,
                    'markedAt'     => null,
                ], range(0, count($provider['keys']) - 1));
            }
        }
        return $state;
    }

    // ─── Step 3 ────────────────────────────────────────────────────────────────

    protected function saveState(array $state): void
    {
        $this->storage->save($state);
    }

    // ─── Step 4 ────────────────────────────────────────────────────────────────

    protected function selectKey(array $providerConfig, array $providerState): ?array
    {
        $activeKeys = [];
        foreach ($providerState['keys'] as $index => $keyState) {
            if ($keyState['status'] === 'active') {
                $activeKeys[] = [
                    'keyIndex' => $index,
                    'lastUsed' => $keyState['lastUsed'],
                    'key'      => $providerConfig['keys'][$index],
                ];
            }
        }

        if (empty($activeKeys)) {
            return null;
        }

        usort($activeKeys, function ($a, $b) {
            $aLast = $a['lastUsed'];
            $bLast = $b['lastUsed'];

            if ($aLast === null && $bLast === null) {
                return 0;
            }
            if ($aLast === null) {
                return -1;
            }
            if ($bLast === null) {
                return 1;
            }

            return $aLast <=> $bLast;
        });

        return [
            'keyIndex' => $activeKeys[0]['keyIndex'],
            'key'      => $activeKeys[0]['key'],
        ];
    }

    // ─── Step 5 ────────────────────────────────────────────────────────────────

    protected function markKeyUsed(array &$state, string $providerId, int $keyIndex): void
    {
        $state[$providerId]['keys'][$keyIndex]['lastUsed'] = microtime(true);
    }

    // ─── Step 6 ────────────────────────────────────────────────────────────────

    protected function markKeyFailure(array &$state, string $providerId, int $keyIndex, string $reason): void
    {
        $state[$providerId]['keys'][$keyIndex]['failureCount']++;

        $threshold = $this->config['circuit_breaker']['failure_threshold'] ?? 3;
        if ($state[$providerId]['keys'][$keyIndex]['failureCount'] >= $threshold) {
            $state[$providerId]['keys'][$keyIndex]['status']       = 'inactive';
            $state[$providerId]['keys'][$keyIndex]['markedAt']     = microtime(true);
            $state[$providerId]['keys'][$keyIndex]['failureCount'] = 0;

            event(new Events\KeyDeactivated($providerId, $keyIndex, $reason));
        }
    }

    // ─── Step 7 ────────────────────────────────────────────────────────────────

    protected function markKeyInactive(array &$state, string $providerId, int $keyIndex, string $reason): void
    {
        $state[$providerId]['keys'][$keyIndex]['status']       = 'inactive';
        $state[$providerId]['keys'][$keyIndex]['markedAt']     = microtime(true);
        $state[$providerId]['keys'][$keyIndex]['failureCount'] = 0;

        event(new Events\KeyDeactivated($providerId, $keyIndex, $reason));
    }

    // ─── Step 8 ────────────────────────────────────────────────────────────────

    protected function estimateTokens(array $messages): int
    {
        $concatenated = '';
        foreach ($messages as $message) {
            $concatenated .= $message['content'];
        }

        return (int) ceil(strlen($concatenated) / 4);
    }

    // ─── Step 9 ────────────────────────────────────────────────────────────────

    protected function sortedProviders(): array
    {
        $providers = $this->config['providers'];
        usort($providers, fn($a, $b) => $a['priority'] <=> $b['priority']);
        return $providers;
    }

    // ─── Step 10 ───────────────────────────────────────────────────────────────

    public function chat(array $messages, array $options = []): DTOs\ChatResponse
    {
        // a. Token guard
        $estimated = $this->estimateTokens($messages);
        $limit     = $options['maxTokens'] ?? $this->config['guard']['max_input_tokens'];
        if ($estimated > $limit) {
            throw new Exceptions\InputTooLongException($estimated, $limit);
        }

        // b. Load state
        $state = $this->loadState();

        // c. Provider list
        $providers = isset($options['forceProvider'])
            ? array_values(array_filter(
                $this->config['providers'],
                fn($p) => $p['id'] === $options['forceProvider']
            ))
            : $this->sortedProviders();

        $fallbackUsed   = false;
        $prevProviderId = null;
        $attempts       = 0;

        // d. Outer loop — providers
        foreach ($providers as $provider) {
            if ($prevProviderId !== null) {
                $fallbackUsed = true;
                event(new Events\ProviderFallback($prevProviderId, $provider['id']));
            }

            $selected = $this->selectKey($provider, $state[$provider['id']]);
            if ($selected === null) {
                event(new Events\ProviderExhausted($provider['id']));
                $prevProviderId = $provider['id'];
                continue;
            }

            $this->markKeyUsed($state, $provider['id'], $selected['keyIndex']);

            $payload = [
                'model'       => $provider['model'],
                'messages'    => $messages,
                'temperature' => $options['temperature'] ?? $this->config['guard']['temperature'],
                'max_tokens'  => $options['maxTokens']   ?? $this->config['guard']['max_output_tokens'],
                'stream'      => $options['streaming']   ?? $this->config['streaming'],
            ];
            $headers = array_merge(
                [
                    'Authorization' => 'Bearer ' . $selected['key'],
                    'Content-Type'  => 'application/json',
                ],
                $provider['extraHeaders'] ?? []
            );
            $timeoutMs  = $this->config['guard']['timeout_ms'];
            $maxRetries = $this->config['guard']['max_retries'];
            $retryCount = 0;

            // Inner loop — retries
            while (true) {
                $attempts++;
                $startTime = microtime(true);

                try {
                    $streaming = $options['streaming'] ?? $this->config['streaming'];

                    if ($streaming) {
                        $this->http->stream(
                            $provider['baseURL'] . '/chat/completions',
                            $headers,
                            $payload,
                            $timeoutMs,
                            $options['onChunk']
                        );
                        $this->saveState($state);
                        return new DTOs\ChatResponse(
                            '',
                            $provider['id'],
                            $selected['keyIndex'],
                            (int) round((microtime(true) - $startTime) * 1000),
                            $provider['model'],
                            $fallbackUsed,
                            $attempts
                        );
                    }

                    $result    = $this->http->post(
                        $provider['baseURL'] . '/chat/completions',
                        $headers,
                        $payload,
                        $timeoutMs
                    );
                    $content   = $result['choices'][0]['message']['content'];
                    $latencyMs = (int) round((microtime(true) - $startTime) * 1000);
                    $this->saveState($state);
                    return new DTOs\ChatResponse(
                        $content,
                        $provider['id'],
                        $selected['keyIndex'],
                        $latencyMs,
                        $result['model'] ?? $provider['model'],
                        $fallbackUsed,
                        $attempts
                    );
                } catch (Exceptions\RateLimitException $e) {
                    $this->markKeyInactive($state, $provider['id'], $selected['keyIndex'], 'rate_limit');
                    break;
                } catch (Exceptions\AuthenticationException $e) {
                    $this->markKeyInactive($state, $provider['id'], $selected['keyIndex'], 'auth_failure');
                    break;
                } catch (Exceptions\TimeoutException | Exceptions\ProviderException $e) {
                    if ($retryCount < $maxRetries) {
                        $retryCount++;
                        continue;
                    }
                    $this->markKeyFailure($state, $provider['id'], $selected['keyIndex'], 'failure_threshold');
                    break;
                }
            }

            $prevProviderId = $provider['id'];
        }

        // e. All providers exhausted
        $this->saveState($state);
        throw new Exceptions\GatewayExhaustedException();
    }

    // ─── Step 11 ───────────────────────────────────────────────────────────────

    public function getStatus(): DTOs\StatusResponse
    {
        $state            = $this->loadState();
        $providerStatuses = [];
        $totalActive      = 0;
        $totalInactive    = 0;

        foreach ($this->config['providers'] as $provider) {
            $keyStatuses = [];
            foreach ($state[$provider['id']]['keys'] as $index => $keyState) {
                $lastUsed = $keyState['lastUsed'] !== null
                    ? date('c', (int) $keyState['lastUsed'])
                    : null;
                $markedAt = $keyState['markedAt'] !== null
                    ? date('c', (int) $keyState['markedAt'])
                    : null;

                $keyStatuses[] = new DTOs\KeyStatus(
                    $index,
                    $keyState['status'],
                    $keyState['failureCount'],
                    $lastUsed,
                    $markedAt
                );

                if ($keyState['status'] === 'active') {
                    $totalActive++;
                } else {
                    $totalInactive++;
                }
            }

            $providerStatuses[] = new DTOs\ProviderStatus(
                $provider['id'],
                $provider['priority'],
                $keyStatuses
            );
        }

        return new DTOs\StatusResponse($providerStatuses, $totalActive, $totalInactive);
    }

    // ─── Step 12 ───────────────────────────────────────────────────────────────

    public function ping(string $providerId, int $keyIndex): bool
    {
        $providerConfig = null;
        foreach ($this->config['providers'] as $provider) {
            if ($provider['id'] === $providerId) {
                $providerConfig = $provider;
                break;
            }
        }

        if ($providerConfig === null) {
            return false;
        }

        if (!isset($providerConfig['keys'][$keyIndex])) {
            return false;
        }

        $key           = $providerConfig['keys'][$keyIndex];
        $state         = $this->loadState();
        $pingTimeoutMs = ($this->config['recovery']['ping_timeout'] ?? 5) * 1000;

        try {
            $this->http->get(
                $providerConfig['baseURL'] . '/models',
                ['Authorization' => 'Bearer ' . $key],
                $pingTimeoutMs
            );

            $state[$providerId]['keys'][$keyIndex]['status']   = 'active';
            $state[$providerId]['keys'][$keyIndex]['markedAt'] = null;
            $this->saveState($state);

            event(new Events\KeyRecovered($providerId, $keyIndex));

            return true;
        } catch (\Exception $e) {
            $state[$providerId]['keys'][$keyIndex]['markedAt'] = microtime(true);
            $this->saveState($state);

            return false;
        }
    }

    // ─── Step 13 ───────────────────────────────────────────────────────────────

    public function runRecovery(): DTOs\RecoveryReport
    {
        $state     = $this->loadState();
        $recovered = [];
        $failed    = [];
        $total     = 0;

        foreach ($state as $providerId => $providerState) {
            $providerConfig = null;
            foreach ($this->config['providers'] as $provider) {
                if ($provider['id'] === $providerId) {
                    $providerConfig = $provider;
                    break;
                }
            }

            if ($providerConfig === null) {
                continue;
            }

            foreach ($providerState['keys'] as $keyIndex => $keyState) {
                if ($keyState['status'] !== 'inactive') {
                    continue;
                }

                $markedAt = $keyState['markedAt'];
                $cooldown = $providerConfig['cooldown'];

                if ($markedAt !== null && (microtime(true) - $markedAt) < $cooldown) {
                    continue;
                }

                $total++;
                $result = $this->ping($providerId, $keyIndex);

                if ($result) {
                    $recovered[] = ['provider' => $providerId, 'keyIndex' => $keyIndex];
                } else {
                    $failed[] = ['provider' => $providerId, 'keyIndex' => $keyIndex];
                }
            }
        }

        return new DTOs\RecoveryReport($recovered, $failed, $total);
    }
}
