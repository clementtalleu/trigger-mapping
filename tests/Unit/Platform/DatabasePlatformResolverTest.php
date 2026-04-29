<?php

declare(strict_types=1);

namespace Talleu\TriggerMapping\Tests\Unit\Platform;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Platforms\SQLServerPlatform;
use PHPUnit\Framework\TestCase;
use Talleu\TriggerMapping\Platform\DatabasePlatformResolver;

final class DatabasePlatformResolverTest extends TestCase
{
    public function testIsMySQLForMySQLPlatform(): void
    {
        $resolver = $this->resolverWithPlatform($this->createMock(AbstractMySQLPlatform::class));

        self::assertTrue($resolver->isMySQL());
        self::assertFalse($resolver->isPostgreSQL());
        self::assertFalse($resolver->isSQLServer());
        self::assertSame('mysql', $resolver->getPlatformName());
    }

    public function testIsPostgreSQLForPostgreSQLPlatform(): void
    {
        $resolver = $this->resolverWithPlatform($this->createMock(PostgreSQLPlatform::class));

        self::assertTrue($resolver->isPostgreSQL());
        self::assertFalse($resolver->isMySQL());
        self::assertFalse($resolver->isSQLServer());
        self::assertSame('postgresql', $resolver->getPlatformName());
    }

    public function testIsSQLServerForSQLServerPlatform(): void
    {
        $resolver = $this->resolverWithPlatform($this->createMock(SQLServerPlatform::class));

        self::assertTrue($resolver->isSQLServer());
        self::assertFalse($resolver->isMySQL());
        self::assertFalse($resolver->isPostgreSQL());
        self::assertSame('sqlsrv', $resolver->getPlatformName());
    }

    /**
     * Audit finding F-14 fix: an unknown platform must be reported by its FQCN
     * (truthful) instead of being silently labelled as "postgresql".
     */
    public function testUnknownPlatformReturnsItsFqcn(): void
    {
        $platform = $this->createMock(AbstractPlatform::class);
        $resolver = $this->resolverWithPlatform($platform);

        self::assertFalse($resolver->isMySQL());
        self::assertFalse($resolver->isPostgreSQL());
        self::assertFalse($resolver->isSQLServer());
        self::assertSame($platform::class, $resolver->getPlatformName());
    }

    private function resolverWithPlatform(AbstractPlatform $platform): DatabasePlatformResolver
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('getDatabasePlatform')->willReturn($platform);

        return new DatabasePlatformResolver($connection);
    }
}
