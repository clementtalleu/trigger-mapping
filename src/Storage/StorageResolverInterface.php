<?php

declare(strict_types=1);

namespace Talleu\TriggerMapping\Storage;

use Talleu\TriggerMapping\Model\ResolvedTrigger;

interface StorageResolverInterface
{
    /**
     * @return value-of<Storage>
     */
    public function getType(string $name): string;

    public function getNamespace(string $name): string;

    public function hasStorage(string $name): bool;

    /**
     * @return list<string>
     */
    public function getAvailableStorages(): array;

    /**
     * @return value-of<Storage>
     */
    public function getResolvedTriggerStorageType(string $storage, ResolvedTrigger $resolvedTrigger): string;

    public function getFunctionSqlFilePath(string $name, ResolvedTrigger $trigger): string;

    public function getTriggerSqlFilePath(string $name, ResolvedTrigger $trigger): string;

    public function guessFunctionSqlFilePath(ResolvedTrigger $trigger): string;

    /**
     * @return list<string>
     */
    public function getPossibleFunctionSqlFilePaths(ResolvedTrigger $trigger): array;

    public function guessTriggerSqlFilePath(ResolvedTrigger $trigger): string;
}
