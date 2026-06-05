<?php

namespace OctopusLLM\Laravel;

class OctopusGateway
{
    /**
     * Create a new OctopusGateway instance.
     */
    public function __construct(
        protected array $config,
        protected $storage,
        protected $http
    ) {}
}
