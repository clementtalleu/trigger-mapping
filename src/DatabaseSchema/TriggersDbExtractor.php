<?php

declare(strict_types=1);

namespace Talleu\TriggerMapping\DatabaseSchema;

use Doctrine\Migrations\DependencyFactory;
use Talleu\TriggerMapping\Platform\DatabasePlatformResolver;

final readonly class TriggersDbExtractor implements TriggersDbExtractorInterface
{
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
        } else if ($this->databasePlatformResolver->isSQLServer()) {
            $sql = "SELECT 
                        T.name AS name,
                        T.object_id AS id,
                        T.parent_class_desc AS parent_type,
                        T.type_desc AS type,
                        TE.type_desc AS event_type,
                        O.name AS table_name,
                        TT.type_name AS type_name
                    FROM sys.triggers AS T
                    INNER JOIN sys.trigger_events AS TE
                    ON T.object_id = TE.object_id
                    INNER JOIN sys.objects AS O
                    ON T.parent_id = O.object_id
                    LEFT JOIN sys.trigger_event_types AS TT
                    ON TE.type = TT.type";
                    

            $rawTriggers = $connection->fetchAllAssociative($sql);
            $triggers = $this->normalizeSqlServerTriggers($rawTriggers);
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
                    WHERE NOT tg.tgisinternal";

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
            $name = (string)$trigger['TRIGGER_NAME'];
            $normalized[$name] = [
                'name' => $name,
                'table' => (string)$trigger['EVENT_OBJECT_TABLE'],
                'events' => [(string)$trigger['EVENT_MANIPULATION']],
                'when' => (string)$trigger['ACTION_TIMING'],
                'scope' => 'ROW',
                'content' => (string)$trigger['ACTION_STATEMENT'],
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
            $name = (string)$trigger['name'];
            $normalized[$name] = [
                'name' => $name,
                'table' => (string)$trigger['table_name'],
                'events' => [(string)$trigger['event_type']],
                'when' => 'AFTER',
                'scope' => 'ROW',
                'content' => '',
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
     * definition: string,
     * function: ?string
     * }>
     */
    private function normalizePostgresqlTriggers(array $rawTriggers): array
    {
        $normalized = [];
        foreach ($rawTriggers as $trigger) {

            $name = (string)$trigger['trigger_name'];
            $definition = (string)$trigger['definition'];

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
                'table' => (string)$trigger['table_name'],
                'events' => $events,
                'when' => $when,
                'scope' => $scope,
                'content' => (string)$trigger['content'],
                'function' => (string)$trigger['function_name'],
                'definition' => $definition
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
