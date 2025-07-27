<?php

declare(strict_types=1);

namespace Talleu\TriggerMapping\DatabaseSchema;

use Doctrine\Migrations\DependencyFactory;
use Talleu\TriggerMapping\Platform\DatabasePlatformResolver;

final class TriggersDbExtractor implements TriggersDbExtractorInterface
{
    public function __construct(
        private readonly DependencyFactory $dependencyFactory,
        private readonly DatabasePlatformResolver $databasePlatformResolver,
    ) {
    }

    /**
     * {@inheritdoc}
     */
    public function listTriggers(?string $entityName = null): array
    {
        $connection = $this->dependencyFactory->getConnection();

        if ($this->databasePlatformResolver->isMySQL()) {
            $sql = "SELECT
                        TRIGGER_NAME,
                        EVENT_OBJECT_TABLE,
                        EVENT_MANIPULATION,
                        ACTION_TIMING,
                        ACTION_STATEMENT
                    FROM information_schema.TRIGGERS
                    WHERE TRIGGER_SCHEMA = DATABASE()";

            $rawTriggers = $connection->fetchAllAssociative($sql);
            $triggers = $this->normalizeMysqlTriggers($rawTriggers);
        } elseif ($this->databasePlatformResolver->isPostgreSQL()) {
            $sql = "SELECT
                        tg.tgname AS trigger_name,
                        tbl.relname AS table_name,
                        p.proname AS function_name,
                        pg_get_triggerdef(tg.oid) AS definition,
                        p.prosrc AS content
                    FROM pg_trigger tg
                    JOIN pg_class tbl ON tg.tgrelid = tbl.oid
                    JOIN pg_proc p ON tg.tgfoid = p.oid
                    JOIN pg_namespace ns ON tbl.relnamespace = ns.oid
                    WHERE NOT tg.tgisinternal AND ns.nspname = 'public'";

            $rawTriggers = $connection->fetchAllAssociative($sql);
            $triggers = $this->normalizePostgresqlTriggers($rawTriggers);
        } else {
            throw new \RuntimeException("Unsupported platform: {$this->databasePlatformResolver->getPlatformName()}. Should be mysql/mariadb or postgresql");
        }

        // Here, we will assume that it is a valid entity name, as it has been verified beforehand. If this is not the case, it will crash, and that's too bad.
        if (null !== $entityName) {
            /** @var class-string $entityName */
            $tableName = $this->dependencyFactory->getEntityManager()->getMetadataFactory()->getMetadataFor($entityName)->getTableName();
            return array_filter($triggers, function ($trigger) use ($tableName) {
                return $trigger['table'] === $tableName;
            });
        }

        return $triggers;
    }

    /**
     * @param array<int, array<string, mixed>> $rawTriggers
     *
     * @return array<string, array{
     * name: string,
     * table: string,
     * events: string[],
     * timing: string,
     * scope: string,
     * content: string,
     * function: ?string,
     * definition: ?string
     * }>
     */
    private function normalizeMysqlTriggers(array $rawTriggers): array
    {
        $normalized = [];
        foreach ($rawTriggers as $trigger) {
            $name = (string) $trigger['TRIGGER_NAME'];
            $normalized[$name] = [
                'name' => $name,
                'table' => (string) $trigger['EVENT_OBJECT_TABLE'],
                'events' => [(string) $trigger['EVENT_MANIPULATION']],
                'timing' => (string) $trigger['ACTION_TIMING'],
                'scope' => 'ROW',
                'content' => (string) $trigger['ACTION_STATEMENT'],
                'function' => null,
                'definition' => null,
            ];
        }

        return $normalized;
    }

    /**
     * @param array<int, array<string, mixed>> $rawTriggers
     *
     * @return array<string, array{
     * name: string,
     * table: string,
     * events: string[],
     * timing: string,
     * scope: string,
     * content: string,
     * definition: string,
     * function: ?string
     * }>
     */
    private function normalizePostgresqlTriggers(array $rawTriggers): array
    {
        $normalized = [];
        foreach ($rawTriggers as $trigger) {

            $name = (string) $trigger['trigger_name'];
            $definition = (string) $trigger['definition'];

            $upperDefinition = strtoupper($definition);
            $timing = 'UNKNOWN';
            if (str_contains($upperDefinition, 'BEFORE')) {
                $timing = 'BEFORE';
            } elseif (str_contains($upperDefinition, 'AFTER')) {
                $timing = 'AFTER';
            }

            $scope = 'UNKNOWN';
            if (str_contains($upperDefinition, 'FOR EACH ROW')) {
                $scope = 'ROW';
            } elseif (str_contains($upperDefinition, 'FOR EACH STATEMENT')) {
                $scope = 'STATEMENT';
            }

            $events = [];
            if (str_contains($upperDefinition, 'INSERT')) {
                $events[] = 'INSERT';
            }
            if (str_contains($upperDefinition, 'UPDATE')) {
                $events[] = 'UPDATE';
            }
            if (str_contains($upperDefinition, 'DELETE')) {
                $events[] = 'DELETE';
            }

            $normalized[$name] = [
                'name' => $name,
                'table' => (string) $trigger['table_name'],
                'events' => $events,
                'timing' => $timing,
                'scope' => $scope,
                'content' => (string) $trigger['content'],
                'function' => (string) $trigger['function_name'],
                'definition' => $definition
            ];
        }

        return $normalized;
    }
}
