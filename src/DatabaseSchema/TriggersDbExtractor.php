<?php

declare(strict_types=1);

namespace Talleu\TriggerMapping\DatabaseSchema;

use Doctrine\Migrations\DependencyFactory;
use Talleu\TriggerMapping\Attribute\Trigger;
use Talleu\TriggerMapping\Platform\DatabasePlatformResolver;

final readonly class TriggersDbExtractor implements TriggersDbExtractorInterface
{
    /**
     * Returns true if the given DB identifier (trigger / function / table name)
     * is safe to inline in generated SQL, file paths and PHP migration source.
     * If a database happens to contain a trigger with a name violating this
     * pattern, the bundle silently skips it during extraction so that no
     * unsafe value ever reaches the file system or the generated migration.
     */
    private function isSafeIdentifier(string $value): bool
    {
        return '' !== $value
            && 1 === preg_match(Trigger::IDENTIFIER_PATTERN, $value);
    }

    /**
     * @param string[] $excludedTriggers
     */
    public function __construct(
        private DependencyFactory        $dependencyFactory,
        private DatabasePlatformResolver $databasePlatformResolver,
        private array                    $excludedTriggers,
    ) {
    }

    /**
     * {@inheritdoc}
     */
    public function listTriggers(?string $entityName = null): array
    {
        $connection = $this->dependencyFactory->getConnection();

        if ($this->databasePlatformResolver->isMySQL()) {
            $sql = 'SELECT
                        TRIGGER_NAME,
                        EVENT_OBJECT_TABLE,
                        EVENT_MANIPULATION,
                        ACTION_TIMING,
                        ACTION_STATEMENT
                    FROM information_schema.TRIGGERS
                    WHERE TRIGGER_SCHEMA = DATABASE()';

            $rawTriggers = $connection->fetchAllAssociative($sql);
            $triggers = $this->normalizeMysqlTriggers($rawTriggers);
        } elseif ($this->databasePlatformResolver->isSQLServer()) {
            // - parent_class = 1 filters out database-level DDL triggers (only DML triggers on tables/views).
            // - is_instead_of_trigger distinguishes INSTEAD OF triggers from AFTER/FOR triggers.
            // - sys.sql_modules.definition gives us the trigger body (was always empty before).
            // - sys.schemas + SCHEMA_NAME() restrict the result to the current default schema.
            // - parent_class = 1 filters out database-level DDL triggers (only DML triggers on tables/views).
            // - is_instead_of_trigger distinguishes INSTEAD OF triggers from AFTER/FOR triggers.
            // - sys.sql_modules.definition gives us the trigger body (was always empty before).
            // - sys.schemas + SCHEMA_NAME() restrict the result to the current default schema.
            // - The CASE on TE.type avoids depending on sys.trigger_event_types whose mapping
            //   rows are not always populated for DML events depending on the SQL Server image.
            $sql = "SELECT
                        T.name AS name,
                        T.is_instead_of_trigger AS is_instead_of,
                        T.is_disabled AS is_disabled,
                        CASE TE.type
                            WHEN 1 THEN 'INSERT'
                            WHEN 2 THEN 'UPDATE'
                            WHEN 3 THEN 'DELETE'
                            ELSE NULL
                        END AS event_type,
                        O.name AS table_name,
                        S.name AS schema_name,
                        SM.definition AS body
                    FROM sys.triggers AS T
                    INNER JOIN sys.trigger_events AS TE ON T.object_id = TE.object_id
                    INNER JOIN sys.objects AS O ON T.parent_id = O.object_id
                    INNER JOIN sys.schemas AS S ON O.schema_id = S.schema_id
                    LEFT JOIN sys.sql_modules AS SM ON T.object_id = SM.object_id
                    WHERE T.parent_class = 1
                      AND S.name = SCHEMA_NAME()";

            $rawTriggers = $connection->fetchAllAssociative($sql);
            $triggers = $this->normalizeSqlServerTriggers($rawTriggers);
        } elseif ($this->databasePlatformResolver->isPostgreSQL()) {
            $sql = 'SELECT
                        tg.tgname AS trigger_name,
                        tbl.relname AS table_name,
                        p.proname AS function_name,
                        pg_get_triggerdef(tg.oid) AS definition,
                        p.prosrc AS content
                    FROM pg_trigger tg
                    JOIN pg_class tbl ON tg.tgrelid = tbl.oid
                    JOIN pg_proc p ON tg.tgfoid = p.oid
                    JOIN pg_namespace ns ON tbl.relnamespace = ns.oid
                    WHERE NOT tg.tgisinternal';

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

        return $this->removeExcludesTriggers($triggers);
    }

    /**
     * @param array<int, array<string, mixed>> $rawTriggers
     *
     * @return array<string, array{
     * name: string,
     * table: string,
     * events: string[],
     * when: string,
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
            $tableName = (string) $trigger['EVENT_OBJECT_TABLE'];

            if (!$this->isSafeIdentifier($name) || !$this->isSafeIdentifier($tableName)) {
                continue;
            }

            $normalized[$name] = [
                'name' => $name,
                'table' => $tableName,
                'events' => [(string) $trigger['EVENT_MANIPULATION']],
                'when' => (string) $trigger['ACTION_TIMING'],
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
     * when: string,
     * scope: string,
     * content: string,
     * function: ?string,
     * definition: ?string
     * }>
     */
    private function normalizeSqlServerTriggers(array $rawTriggers): array
    {
        $normalized = [];
        foreach ($rawTriggers as $trigger) {
            $name = (string) $trigger['name'];
            $tableName = (string) $trigger['table_name'];
            $event = strtoupper((string) $trigger['event_type']);

            if (!$this->isSafeIdentifier($name) || !$this->isSafeIdentifier($tableName)) {
                continue;
            }

            if (!isset($normalized[$name])) {
                $normalized[$name] = [
                    'name' => $name,
                    'table' => $tableName,
                    'events' => [],
                    'when' => ((bool) $trigger['is_instead_of']) ? 'INSTEAD OF' : 'AFTER',
                    // SQL Server triggers fire once per statement, not per row.
                    'scope' => 'STATEMENT',
                    'content' => (string) ($trigger['body'] ?? ''),
                    'function' => null,
                    'definition' => null,
                ];
            }

            // sys.trigger_events returns one row per event — aggregate them all
            // (without this guard, 'AFTER INSERT, UPDATE' would only keep one event).
            if ('' !== $event && !in_array($event, $normalized[$name]['events'], true)) {
                $normalized[$name]['events'][] = $event;
            }
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
     * when: string,
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
            $tableName = (string) $trigger['table_name'];
            $functionName = (string) $trigger['function_name'];

            if (!$this->isSafeIdentifier($name) || !$this->isSafeIdentifier($tableName) || !$this->isSafeIdentifier($functionName)) {
                continue;
            }

            $definition = (string) $trigger['definition'];

            $upperDefinition = strtoupper($definition);
            $when = 'UNKNOWN';
            if (str_contains($upperDefinition, 'BEFORE')) {
                $when = 'BEFORE';
            } elseif (str_contains($upperDefinition, 'AFTER')) {
                $when = 'AFTER';
            }

            $scope = 'UNKNOWN';
            if (str_contains($upperDefinition, ' ROW ')) {
                $scope = 'ROW';
            } elseif (str_contains($upperDefinition, ' STATEMENT ')) {
                $scope = 'STATEMENT';
            }

            $events = [];
            if (str_contains($upperDefinition, ' INSERT ')) {
                $events[] = 'INSERT';
            }
            if (str_contains($upperDefinition, ' UPDATE ')) {
                $events[] = 'UPDATE';
            }
            if (str_contains($upperDefinition, ' DELETE ')) {
                $events[] = 'DELETE';
            }

            $normalized[$name] = [
                'name' => $name,
                'table' => $tableName,
                'events' => $events,
                'when' => $when,
                'scope' => $scope,
                'content' => (string) $trigger['content'],
                'function' => $functionName,
                'definition' => $definition,
            ];
        }

        return $normalized;
    }

    /**
     * @param array<string, array{
     *  name: string,
     *  table: string,
     *  events: string[],
     *  when: string,
     *  scope: string,
     *  content: string,
     *  function: ?string,
     *  definition: ?string
     *  }> $normalizedTriggers
     *
     * @return array<string, array{
     * name: string,
     * table: string,
     * events: string[],
     * when: string,
     * scope: string,
     * content: string,
     * function: ?string,
     * definition: ?string
     * }>
     */
    private function removeExcludesTriggers(array $normalizedTriggers): array
    {
        foreach ($this->excludedTriggers as $excludedTrigger) {
            if (array_key_exists($excludedTrigger, $normalizedTriggers)) {
                unset($normalizedTriggers[$excludedTrigger]);
            }
        }

        return $normalizedTriggers;
    }
}
