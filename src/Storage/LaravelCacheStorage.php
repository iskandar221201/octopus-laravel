<?php

declare(strict_types=1);

namespace OctopusLLM\Laravel\Storage;

use Illuminate\Contracts\Cache\Repository as CacheRepository;
use OctopusLLM\Laravel\Contracts\StorageInterface;

class LaravelCacheStorage implements StorageInterface
{
    public function __construct(
        protected CacheRepository $cache,
        protected string $prefix,
        protected int $ttl,
    ) {}

    /**
     * Load state array dari storage.
     * Jika belum ada, return [].
     */
    public function load(): array
    {
        return $this->cache->get($this->prefix, []);
    }

    /**
     * Simpan state array ke storage.
     */
    public function save(array $state): void
    {
        $this->cache->put($this->prefix, $state, $this->ttl);
    }
}
