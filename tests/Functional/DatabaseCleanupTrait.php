<?php

declare(strict_types=1);

namespace Talleu\TriggerMapping\Tests\Functional;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Platforms\SQLServerPlatform;

/**
 * Brings the test database back to a clean, empty state without dropping
 * the database itself. This is important because `doctrine:database:drop`
 * is unreliable on SQL Server when the test runner still holds a connection
 * to the database, leaving stale tables across tests on GitHub Actions.
 *
 * The trait removes all user triggers, then all tables (which cascades to
 * remaining attached triggers and FKs), and on PostgreSQL also drops user
 * functions that the bundle may have created during a previous test.
 */
trait DatabaseCleanupTrait
{
    protected function cleanupDatabase(Connection $connection): void
    {
        $platform = $connection->getDatabasePlatform();

        if ($platform instanceof PostgreSQLPlatform) {
            $this->cleanupPostgresql($connection);

            return;
        }

        if ($platform instanceof SQLServerPlatform) {
            $this->cleanupSqlServer($connection);

            return;
        }

        if ($platform instanceof AbstractMySQLPlatform) {
            $this->cleanupMysql($connection);

            return;
        }

        throw new \RuntimeException('Unsupported platform for cleanup: '.$platform::class);
    }

    private function cleanupPostgresql(Connection $connection): void
    {
        // Reset the public schema in one shot — drops triggers, functions, tables, sequences.
        $connection->executeStatement('DROP SCHEMA IF EXISTS public CASCADE');
        $connection->executeStatement('CREATE SCHEMA public');
        $connection->executeStatement('GRANT ALL ON SCHEMA public TO public');
    }

    private function cleanupSqlServer(Connection $connection): void
    {
        // 1. Drop user DML triggers (parent_class = 1 excludes DDL/server triggers).
        $triggers = $connection->fetchFirstColumn(
            'SELECT T.name FROM sys.triggers AS T WHERE T.parent_class = 1'
        );
        foreach ($triggers as $name) {
            $connection->executeStatement(sprintf('DROP TRIGGER [%s]', $name));
        }

        // 2. Drop all FK constraints first to allow tables to be dropped in any order.
        $fks = $connection->fetchAllAssociative(
            "SELECT s.name AS schema_name, t.name AS table_name, fk.name AS fk_name
             FROM sys.foreign_keys fk
             JOIN sys.tables t ON fk.parent_object_id = t.object_id
             JOIN sys.schemas s ON t.schema_id = s.schema_id"
        );
        foreach ($fks as $fk) {
            $connection->executeStatement(sprintf(
                'ALTER TABLE [%s].[%s] DROP CONSTRAINT [%s]',
                $fk['schema_name'],
                $fk['table_name'],
                $fk['fk_name']
            ));
        }

        // 3. Drop user tables.
        $tables = $connection->createSchemaManager()->listTables();
        foreach ($tables as $table) {
            $connection->executeStatement(sprintf('DROP TABLE [%s]', $table->getName()));
        }
    }

    private function cleanupMysql(Connection $connection): void
    {
        // 1. Drop triggers belonging to the current database.
        $triggers = $connection->fetchFirstColumn(
            'SELECT TRIGGER_NAME FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA = DATABASE()'
        );
        foreach ($triggers as $name) {
            $connection->executeStatement(sprintf('DROP TRIGGER IF EXISTS `%s`', $name));
        }

        // 2. Disable FK checks so we don't have to compute drop order, then drop tables.
        $connection->executeStatement('SET FOREIGN_KEY_CHECKS = 0');
        try {
            $tables = $connection->createSchemaManager()->listTables();
            foreach ($tables as $table) {
                $connection->executeStatement(sprintf('DROP TABLE IF EXISTS `%s`', $table->getName()));
            }
        } finally {
            $connection->executeStatement('SET FOREIGN_KEY_CHECKS = 1');
        }
    }
}
