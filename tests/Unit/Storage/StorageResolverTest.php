<?php

declare(strict_types=1);

namespace Talleu\TriggerMapping\Tests\Unit\Storage;

use PHPUnit\Framework\TestCase;
use Talleu\TriggerMapping\Storage\StorageResolver;

final class StorageResolverTest extends TestCase
{
    public function testGettersReturnInjectedValues(): void
    {
        $resolver = new StorageResolver(
            type: 'php',
            directory: '/var/triggers',
            namespace: 'App\\Triggers',
        );

        self::assertSame('php', $resolver->getType());
        self::assertSame('/var/triggers', $resolver->getResolvedDirectory());
        self::assertSame('App\\Triggers', $resolver->getResolvedNamespace());
    }

    public function testTypeIsExposedAsRawString(): void
    {
        // The resolver does not validate the type by itself — it merely transports
        // whatever the bundle configuration injected. Validation happens in the
        // DI extension. This test pins that contract.
        $resolver = new StorageResolver(
            type: 'sql',
            directory: '/whatever',
            namespace: 'X',
        );

        self::assertSame('sql', $resolver->getType());
    }
}
