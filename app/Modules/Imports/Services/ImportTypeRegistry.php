<?php

namespace App\Modules\Imports\Services;

use App\Modules\Imports\Contracts\ImportTypeHandler;

/**
 * Registry of available import type handlers. Currently only the client import
 * is registered; adding a new type is a matter of appending its handler here.
 */
class ImportTypeRegistry
{
    /** @var array<string, ImportTypeHandler> */
    private array $handlers = [];

    public function __construct(ClientImportHandler $clientImportHandler)
    {
        $this->register($clientImportHandler);
    }

    /**
     * Add a handler to the registry, keyed by its own key().
     *
     * @param ImportTypeHandler $handler
     * @return void
     */
    public function register(ImportTypeHandler $handler): void
    {
        $this->handlers[$handler->key()] = $handler;
    }

    /**
     * Whether a handler exists for the given type key.
     *
     * @param string $type
     * @return bool
     */
    public function has(string $type): bool
    {
        return isset($this->handlers[$type]);
    }

    /**
     * Resolve a handler by type key.
     *
     * @param string $type
     * @return ImportTypeHandler
     * @throws \InvalidArgumentException When no handler is registered for the type.
     */
    public function get(string $type): ImportTypeHandler
    {
        if (!$this->has($type)) {
            throw new \InvalidArgumentException("Unsupported import type: {$type}");
        }
        return $this->handlers[$type];
    }

    /**
     * All registered handlers.
     *
     * @return array<int, ImportTypeHandler>
     */
    public function all(): array
    {
        return array_values($this->handlers);
    }
}
