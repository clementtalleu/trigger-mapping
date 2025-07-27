<?php

declare(strict_types=1);

namespace Talleu\TriggerMapping\Storage;

interface StorageResolverInterface
{
    public function getResolvedDirectory(): string;

    public function getResolvedNamespace(): string;

    public function getType(): string;
}
