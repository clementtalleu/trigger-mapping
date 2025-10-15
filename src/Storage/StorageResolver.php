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

    public function getResolvedDirectoryForNamespace(string $namespace): string
    {
        return $this->getResolvedDirectory($this->getStorage($namespace)['directory']);
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

        return $this->getResolvedDirectoryForNamespace($namespace) . '/functions/' . $trigger->function . '.sql';
    }

    public function getTriggerSqlFilePathForNamespace(string $namespace, ResolvedTrigger $trigger): string
    {
        $directory = $this->getResolvedDirectoryForNamespace($namespace);

        if ($this->databasePlatformResolver->isPostgreSQL()) {
            return $directory . '/triggers/' . $trigger->name . '.sql';
        }

        return $directory . '/' . $trigger->function . '.sql';
    }

    public function guessFunctionSqlFilePath(ResolvedTrigger $trigger): string
    {
        if (!$this->databasePlatformResolver->isPostgreSQL()) {
            throw new \RuntimeException('Only PostgreSQL support functions file');
        }

        foreach ($this->storages as $storage) {
            $filePath = $this->getResolvedDirectory($storage['directory']) . '/functions/' . $trigger->function . '.sql';
            if (file_exists($filePath)) {
                return $filePath;
            }
        }

        throw new \InvalidArgumentException(\sprintf('No function sql file found for trigger "%s"', $trigger->name));
    }

    public function getPossibleFunctionSqlFilePaths(ResolvedTrigger $trigger): array
    {
        $paths = [];

        foreach ($this->storages as $storage) {
            $paths[] = $this->getResolvedDirectory($storage['directory']) . '/functions/' . $trigger->function . '.sql';
        }

        return $paths;
    }

    public function guessTriggerSqlFilePath(ResolvedTrigger $trigger): string
    {
        foreach ($this->storages as $storage) {
            $directory = $this->getResolvedDirectory($storage['directory']);

            if ($this->databasePlatformResolver->isPostgreSQL()) {
                $filePath = $directory . '/triggers/' . $trigger->name . '.sql';
            } else {
                $filePath = $directory . '/' . $trigger->name . '.sql';
            }

            if (file_exists($filePath)) {
                return $filePath;
            }
        }

        throw new \InvalidArgumentException(\sprintf('No triggers sql file found for trigger "%s"', $trigger->name));
    }

    public function hasNamespace(string $namespace): bool
    {
        return \in_array($namespace, $this->getAvailableNamespaces(), true);
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

    private function getResolvedDirectory(string $directory): string
    {
        if (!str_starts_with($directory, '@')) {
            return $directory;
        }

        return $this->fileLocator->locate($directory);
    }
}
