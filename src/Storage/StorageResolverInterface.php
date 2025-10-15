<?php

declare(strict_types=1);

namespace Talleu\TriggerMapping\Storage;

use Talleu\TriggerMapping\Model\ResolvedTrigger;

interface StorageResolverInterface
{
    public function getResolvedDirectoryForNamespace(string $namespace): string;

    public function getType(string $namespace): string;

    public function hasNamespace(string $namespace): bool;

    /**
     * @return list<string>
     */
    public function getAvailableNamespaces(): array;

    public function getFunctionSqlFilePathForNamespace(string $namespace, ResolvedTrigger $trigger): string;

    public function getTriggerSqlFilePathForNamespace(string $namespace, ResolvedTrigger $trigger): string;

    public function guessFunctionSqlFilePath(ResolvedTrigger $trigger): string;

    /**
     * @return list<string>
     */
    public function getPossibleFunctionSqlFilePaths(ResolvedTrigger $trigger): array;

    public function guessTriggerSqlFilePath(ResolvedTrigger $trigger): string;
}
