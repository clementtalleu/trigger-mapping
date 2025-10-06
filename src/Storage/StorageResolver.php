<?php

declare(strict_types=1);

namespace Talleu\TriggerMapping\Storage;

final readonly class StorageResolver implements StorageResolverInterface
{
    public function __construct(
        /** @var list<array{
         *     directory: string,
         *     namespace: string,
         *     type: string,
         * }>
         */
        private array $storages,
    ) {
    }

    public function getResolvedDirectory(): string
    {
        return $this->directory;
    }

    public function getResolvedNamespace(): string
    {
        return $this->namespace;
    }

    public function getType(?string $namespace = null): string
    {
        /*
         * If no namespace is provided, return the last storage type, which is the one from the application
         * configuration.
         */
        if (null === $namespace) {
            return $this->storages[array_key_last($this->storages)]['type'];
        }

        foreach ($this->storages as $storage) {
            if (\str_starts_with($storage['namespace'], $namespace)) {
                return $storage['type'];
            }
        }

        throw new \InvalidArgumentException(\sprintf('No storage found for namespace "%s"', $namespace));
    }
}
