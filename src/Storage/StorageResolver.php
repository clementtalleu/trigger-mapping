<?php

declare(strict_types=1);

namespace Talleu\TriggerMapping\Storage;

final class StorageResolver implements StorageResolverInterface
{
    public function __construct(
        private readonly string $type,
        private readonly string $directory,
        private readonly string $namespace,
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

    public function getType(): string
    {
        return $this->type;
    }
}
