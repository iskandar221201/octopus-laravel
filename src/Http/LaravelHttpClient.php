<?php

declare(strict_types=1);

namespace OctopusLLM\Laravel\Http;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as HttpFactory;
use OctopusLLM\Laravel\Contracts\HttpClientInterface;
use OctopusLLM\Laravel\Exceptions\AuthenticationException;
use OctopusLLM\Laravel\Exceptions\ProviderException;
use OctopusLLM\Laravel\Exceptions\RateLimitException;
use OctopusLLM\Laravel\Exceptions\TimeoutException;

class LaravelHttpClient implements HttpClientInterface
{
    public function __construct(protected HttpFactory $http) {}

    /**
     * HTTP POST synchronous, return decoded response body sebagai array.
     *
     * @param string $url        Full URL endpoint
     * @param array  $headers    Key-value HTTP headers
     * @param array  $payload    Request body (akan di-encode ke JSON)
     * @param int    $timeoutMs  Timeout dalam milliseconds
     * @return array             Decoded response body
     * @throws ProviderException  jika HTTP error
     * @throws TimeoutException   jika timeout
     * @throws RateLimitException  jika 429
     * @throws AuthenticationException jika 401/403
     */
    public function post(string $url, array $headers, array $payload, int $timeoutMs): array
    {
        try {
            $response = $this->http->withHeaders($headers)
                ->timeout((int) ceil($timeoutMs / 1000))
                ->post($url, $payload);
        } catch (ConnectionException $e) {
            throw new TimeoutException($this->extractProviderName($url), $timeoutMs);
        }

        $status = $response->status();

        if ($status === 429) {
            $retryAfter = $response->header('Retry-After');
            $retryAfterInt = is_numeric($retryAfter) ? (int) $retryAfter : null;
            throw new RateLimitException($this->extractProviderName($url), $retryAfterInt);
        }

        if ($status === 401 || $status === 403) {
            throw new AuthenticationException($this->extractProviderName($url), -1);
        }

        if ($status >= 400) {
            throw new ProviderException($this->extractProviderName($url), $response->body());
        }

        return (array) $response->json();
    }

    /**
     * HTTP POST dengan streaming SSE (Server-Sent Events).
     * Setiap chunk delta dipanggil via $onChunk callback.
     *
     * @param callable $onChunk  fn(string $delta): void
     */
    public function stream(string $url, array $headers, array $payload, int $timeoutMs, callable $onChunk): void
    {
        try {
            $response = $this->http->withHeaders($headers)
                ->timeout((int) ceil($timeoutMs / 1000))
                ->withOptions(['stream' => true])
                ->post($url, $payload);
        } catch (ConnectionException $e) {
            throw new TimeoutException($this->extractProviderName($url), $timeoutMs);
        }

        $status = $response->status();

        if ($status === 429) {
            $retryAfter = $response->header('Retry-After');
            $retryAfterInt = is_numeric($retryAfter) ? (int) $retryAfter : null;
            throw new RateLimitException($this->extractProviderName($url), $retryAfterInt);
        }

        if ($status === 401 || $status === 403) {
            throw new AuthenticationException($this->extractProviderName($url), -1);
        }

        if ($status >= 400) {
            throw new ProviderException($this->extractProviderName($url), $response->body());
        }

        $psrBody = $response->toPsrResponse()->getBody();
        $stream = $psrBody->detach();

        if (is_resource($stream)) {
            try {
                while (!feof($stream)) {
                    $line = fgets($stream);
                    if ($line === false) {
                        break;
                    }
                    $line = trim($line);
                    if (str_starts_with($line, 'data: ')) {
                        $data = substr($line, 6);
                        if ($data === '[DONE]') {
                            break;
                        }
                        $decoded = json_decode($data, true);
                        $delta = $decoded['choices'][0]['delta']['content'] ?? '';
                        if ($delta !== '') {
                            $onChunk($delta);
                        }
                    }
                }
            } finally {
                fclose($stream);
            }
        }
    }

    /**
     * HTTP GET synchronous, return decoded response body sebagai array.
     * Dipakai oleh recovery ping ke {baseURL}/models.
     */
    public function get(string $url, array $headers, int $timeoutMs): array
    {
        try {
            $response = $this->http->withHeaders($headers)
                ->timeout((int) ceil($timeoutMs / 1000))
                ->get($url);
        } catch (ConnectionException $e) {
            throw new TimeoutException($this->extractProviderName($url), $timeoutMs);
        }

        $status = $response->status();

        if ($status === 429) {
            $retryAfter = $response->header('Retry-After');
            $retryAfterInt = is_numeric($retryAfter) ? (int) $retryAfter : null;
            throw new RateLimitException($this->extractProviderName($url), $retryAfterInt);
        }

        if ($status === 401 || $status === 403) {
            throw new AuthenticationException($this->extractProviderName($url), -1);
        }

        if ($status >= 400) {
            throw new ProviderException($this->extractProviderName($url), $response->body());
        }

        return (array) $response->json();
    }

    /**
     * Helper to extract the provider name (host) from the URL.
     */
    private function extractProviderName(string $url): string
    {
        return parse_url($url, PHP_URL_HOST) ?? $url;
    }
}
