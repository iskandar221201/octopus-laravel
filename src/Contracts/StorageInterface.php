<?php

declare(strict_types=1);

namespace OctopusLLM\Laravel\Contracts;

interface StorageInterface
{
    /**
     * Load state array dari storage.
     * Jika belum ada, return [].
     */
    public function load(): array;

    /**
     * Simpan state array ke storage.
     */
    public function save(array $state): void;
}
