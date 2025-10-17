<?php

declare(strict_types=1);

namespace Talleu\TriggerMapping\Storage;

use Symfony\Component\Config\FileLocatorInterface;
use Talleu\TriggerMapping\Model\ResolvedTrigger;
use Talleu\TriggerMapping\Platform\DatabasePlatformResolverInterface;

/**
 * @phpstan-type StorageConfiguration = array{
 *     directory: string,
 *     namespace?: string,
 *     type: value-of<Storage>,
 *  }
 */
final readonly class StorageResolver implements StorageResolverInterface
{
    public function __construct(
        /** @phpstan-var array<string, StorageConfiguration> */
        private array $storages,
        private DatabasePlatformResolverInterface $databasePlatformResolver,
        private FileLocatorInterface $fileLocator,
    ) {
    }

    public function getType(string $name): string
    {
        return $this->getStorage($name)['type'];
    }

    public function getNamespace(string $name): string
    {
        $storage = $this->getStorage($name);

        if (Storage::PHP_CLASSES->value !== $storage['type']) {
            throw new \LogicException('Only PHP classes storage supports namespaces');
        }

        if (!isset($storage['namespace'])) {
            throw new \LogicException('PHP classes storage requires a namespace');
        }

        return $storage['namespace'];
    }

    public function getFunctionSqlFilePath(string $name, ResolvedTrigger $trigger): string
    {
        if (!$this->databasePlatformResolver->isPostgreSQL()) {
            throw new \RuntimeException('Only PostgreSQL support functions file');
        }

        return $this->getStorageResolvedDirectory($name) . '/functions/' . $trigger->function . '.sql';
    }

    public function getTriggerSqlFilePath(string $name, ResolvedTrigger $trigger): string
    {
        $directory = $this->getStorageResolvedDirectory($name);

        if ($this->databasePlatformResolver->isPostgreSQL()) {
            return $directory . '/triggers/' . $trigger->name . '.sql';
        }

        return $directory . '/' . $trigger->name . '.sql';
    }

    public function guessFunctionSqlFilePath(ResolvedTrigger $trigger): string
    {
        if (!$this->databasePlatformResolver->isPostgreSQL()) {
            throw new \RuntimeException('Only PostgreSQL support functions file');
        }

        foreach ($this->storages as $storage) {
            $filePath = $this->getResolvedDirectory(
                    $storage['directory']
                ) . '/functions/' . $trigger->function . '.sql';
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

    public function hasStorage(string $name): bool
    {
        return \in_array($name, $this->getAvailableStorages(), true);
    }

    public function getAvailableStorages(): array
    {
        return array_keys($this->storages);
    }

    /**
     * @phpstan-return StorageConfiguration
     */
    private function getStorage(string $name): array
    {
        foreach ($this->storages as $storageName => $storage) {
            if ($storageName === $name) {
                return $storage;
            }
        }

        throw new \InvalidArgumentException(\sprintf('No storage named "%s" found', $name));
    }

    private function getStorageResolvedDirectory(string $name): string
    {
        return $this->getResolvedDirectory($this->getStorage($name)['directory']);
    }

    private function getResolvedDirectory(string $directory): string
    {
        if (!str_starts_with($directory, '@')) {
            return $directory;
        }

        return $this->fileLocator->locate($directory);
    }
}
