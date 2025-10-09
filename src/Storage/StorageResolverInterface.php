<?php

declare(strict_types=1);

namespace Talleu\TriggerMapping\Storage;

use Talleu\TriggerMapping\Model\ResolvedTrigger;

interface StorageResolverInterface
{
    public function getResolvedDirectory(string $namespace): string;

    public function getResolvedNamespace(string $namespace): string;

    public function getType(string $namespace): string;

    /**
     * @return list<string>
     */
    public function getAvailableNamespaces(): array;

    public function getFunctionSqlFilePathForNamespace(string $namespace, ResolvedTrigger $trigger): string;

    public function getTriggerSqlFilePathForNamespace(string $namespace, ResolvedTrigger $trigger): string;
}
