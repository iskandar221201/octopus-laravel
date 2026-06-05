<?php

declare(strict_types=1);

namespace OctopusLLM\Laravel\Contracts;

interface HttpClientInterface
{
    /**
     * HTTP POST synchronous, return decoded response body sebagai array.
     *
     * @param string $url        Full URL endpoint
     * @param array  $headers    Key-value HTTP headers
     * @param array  $payload    Request body (akan di-encode ke JSON)
     * @param int    $timeoutMs  Timeout dalam milliseconds
     * @return array             Decoded response body
     * @throws \OctopusLLM\Laravel\Exceptions\ProviderException  jika HTTP error
     * @throws \OctopusLLM\Laravel\Exceptions\TimeoutException   jika timeout
     * @throws \OctopusLLM\Laravel\Exceptions\RateLimitException  jika 429
     * @throws \OctopusLLM\Laravel\Exceptions\AuthenticationException jika 401/403
     */
    public function post(string $url, array $headers, array $payload, int $timeoutMs): array;

    /**
     * HTTP POST dengan streaming SSE (Server-Sent Events).
     * Setiap chunk delta dipanggil via $onChunk callback.
     *
     * @param callable $onChunk  fn(string $delta): void
     */
    public function stream(string $url, array $headers, array $payload, int $timeoutMs, callable $onChunk): void;

    /**
     * HTTP GET synchronous, return decoded response body sebagai array.
     * Dipakai oleh recovery ping ke {baseURL}/models.
     */
    public function get(string $url, array $headers, int $timeoutMs): array;
}
