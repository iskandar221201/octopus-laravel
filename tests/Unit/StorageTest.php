<?php

declare(strict_types=1);

namespace OctopusLLM\Laravel\Tests\Unit;

use OctopusLLM\Laravel\Storage\LaravelCacheStorage;
use OctopusLLM\Laravel\Storage\NullStorage;
use OctopusLLM\Laravel\Tests\TestCase;
use Illuminate\Support\Facades\Cache;

class StorageTest extends TestCase
{
    public function test_laravel_cache_storage_saves_and_loads_state(): void
    {
        $storage = new LaravelCacheStorage(Cache::store('array'), 'test_prefix', 86400);
        $state = ['groq' => ['keys' => [0 => ['status' => 'active', 'failureCount' => 0]]]];
        $storage->save($state);
        $loaded = $storage->load();
        $this->assertSame($state, $loaded);
    }

    public function test_laravel_cache_storage_returns_empty_array_on_miss(): void
    {
        $storage = new LaravelCacheStorage(Cache::store('array'), 'nonexistent_key_xyz', 86400);
        $this->assertSame([], $storage->load());
    }

    public function test_null_storage_saves_and_loads_in_memory(): void
    {
        $storage = new NullStorage();
        $state = ['test' => 'data'];
        $storage->save($state);
        $this->assertSame($state, $storage->load());
    }

    public function test_null_storage_new_instance_starts_empty(): void
    {
        $storage = new NullStorage();
        $this->assertSame([], $storage->load());
    }
}
