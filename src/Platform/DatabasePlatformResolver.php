<?php

declare(strict_types=1);

namespace Talleu\TriggerMapping\Platform;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Platforms\SQLServerPlatform;

final readonly class DatabasePlatformResolver implements DatabasePlatformResolverInterface
{
    public function __construct(private Connection $connection)
    {
    }

    public function isPostgreSQL(): bool
    {
        return $this->getPlatform() instanceof PostgreSQLPlatform;
    }

    /**
     * For mysql / mariadb
     */
    public function isMySQL(): bool
    {
        return $this->getPlatform() instanceof AbstractMySQLPlatform;
    }

    public function isSQLServer(): bool
    {
        return $this->getPlatform() instanceof SQLServerPlatform;
    }


    public function getPlatformName(): string
    {
        if ($this->isMySQL()) {
            return 'mysql';
        }

        if ($this->isSQLServer()) {
            return 'sqlsrv';
        }

        return 'postgresql';
    }

    private function getPlatform(): AbstractPlatform
    {
        return $this->connection->getDatabasePlatform();
    }
}
