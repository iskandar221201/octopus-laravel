<?php

declare(strict_types=1);

namespace OctopusLLM\Laravel\Storage;

use OctopusLLM\Laravel\Contracts\StorageInterface;

class NullStorage implements StorageInterface
{
    private array $data = [];

    /**
     * Load state array dari storage.
     * Jika belum ada, return [].
     */
    public function load(): array
    {
        return $this->data;
    }

    /**
     * Simpan state array ke storage.
     */
    public function save(array $state): void
    {
        $this->data = $state;
    }
}
