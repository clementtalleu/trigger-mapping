<?php

declare(strict_types=1);

namespace Talleu\TriggerMapping\Storage;

use Symfony\Component\Config\FileLocatorInterface;
use Talleu\TriggerMapping\Model\ResolvedTrigger;
use Talleu\TriggerMapping\Platform\DatabasePlatformResolverInterface;

/**
 * @phpstan-type StorageConfiguration = array{
 *     directory: string,
 *     namespace: string,
 *     type: string,
 *  }
 */
final readonly class StorageResolver implements StorageResolverInterface
{
    public function __construct(
        /** @phpstan-var list<StorageConfiguration> */
        private array $storages,
        private DatabasePlatformResolverInterface $databasePlatformResolver,
        private FileLocatorInterface $fileLocator,
    ) {
    }

    public function getResolvedDirectory(string $namespace): string
    {
        $directory = $this->getStorage($namespace)['directory'];

        if (!str_starts_with($directory, '@')) {
            return $directory;
        }

        return $this->fileLocator->locate($directory);
    }

    public function getResolvedNamespace(string $namespace): string
    {
        return $this->getStorage($namespace)['namespace'];
    }

    public function getType(string $namespace): string
    {
        return $this->getStorage($namespace)['type'];
    }

    public function getFunctionSqlFilePathForNamespace(string $namespace, ResolvedTrigger $trigger): string
    {
        if (!$this->databasePlatformResolver->isPostgreSQL()) {
            throw new \RuntimeException('Only PostgreSQL support functions file');
        }

        return $this->getResolvedDirectory($namespace) . '/functions/' . $trigger->function . '.sql';
    }

    public function getTriggerSqlFilePathForNamespace(string $namespace, ResolvedTrigger $trigger): string
    {
        $directory = $this->getResolvedDirectory($namespace);

        if ($this->databasePlatformResolver->isPostgreSQL()) {
            return $directory . '/triggers/' . $trigger->name . '.sql';
        }

        return $directory . '/' . $trigger->function . '.sql';
    }

    public function getAvailableNamespaces(): array
    {
        return array_map(static fn (array $storage): string => $storage['namespace'], $this->storages);
    }

    /**
     * @phpstan-return StorageConfiguration
     */
    private function getStorage(string $namespace): array
    {
        foreach ($this->storages as $storage) {
            if ($namespace === $storage['namespace']) {
                return $storage;
            }
        }

        throw new \InvalidArgumentException(\sprintf('No storage found for namespace "%s"', $namespace));
    }
}
