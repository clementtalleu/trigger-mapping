<?php

declare(strict_types=1);

namespace Talleu\TriggerMapping\Factory;

use Doctrine\Migrations\DependencyFactory;
use Symfony\Bundle\MakerBundle\Exception\RuntimeCommandException;
use Symfony\Bundle\MakerBundle\Generator;
use Symfony\Bundle\MakerBundle\Str;
use Symfony\Bundle\MakerBundle\Util\ClassNameDetails;
use Symfony\Component\Console\Style\StyleInterface;
use Talleu\TriggerMapping\Exception\TriggerClassAlreadyExistsException;
use Talleu\TriggerMapping\Exception\TriggerSqlFileAlreadyExistsException;
use Talleu\TriggerMapping\Model\ResolvedTrigger;
use Talleu\TriggerMapping\Platform\DatabasePlatformResolverInterface;
use Talleu\TriggerMapping\Storage\Storage;
use Talleu\TriggerMapping\Storage\StorageResolverInterface;

/**
 * A class to create triggers templates (sql or php),
 * (Also create some migrations if needed, I should have separated these responsibilities, mais la flemme)
 */
final class TriggerCreator implements TriggerCreatorInterface
{
    public function __construct(
        private Generator                         $generator,
        private StorageResolverInterface          $storageResolver,
        private DatabasePlatformResolverInterface $databasePlatformResolver,
        private DependencyFactory                 $dependencyFactory,
        private bool                              $migrations,
    ) {
    }

    /**
     * @inheritdoc
     */
    public function create(string $namespace, array $resolvedTriggers, ?bool $createMigrations = null, ?StyleInterface $io = null): array
    {
        $triggersForMigrationsPHP = [];
        $triggersForMigrationsSQL = [];

        foreach ($resolvedTriggers as $resolvedTrigger) {
            if ($resolvedTrigger->storage === Storage::PHP_CLASSES->value) {
                $triggerClassDetails = $this->createTriggerClass($namespace, $resolvedTrigger);
                $triggersForMigrationsPHP[] = [
                    'classDetails' => $triggerClassDetails,
                    'resolvedTrigger' => $resolvedTrigger,
                ];
                $triggersClassesDetails[] = $triggerClassDetails;
            } else {
                $this->createTriggerSqlFiles($namespace, $resolvedTrigger);
                $triggersForMigrationsSQL[] = $resolvedTrigger;
            }
        }

        if (true === $createMigrations || (null === $createMigrations && true === $this->migrations)) {
            $this->createMigration($namespace, $triggersForMigrationsPHP, $triggersForMigrationsSQL, $io);
        }

        return $triggersClassesDetails ?? [];
    }

    private function createTriggerClass(string $namespace, ResolvedTrigger $resolvedTrigger): ClassNameDetails
    {
        $className = Str::asClassName($resolvedTrigger->name);
        $triggerClassNameDetails = $this->generator->createClassNameDetails(
            '\\' . $namespace . '\\' . $className,
            $namespace
        );

        $params = [
            'trigger_name' => $resolvedTrigger->name,
            'table_name' => $resolvedTrigger->table,
            'function_name' => $resolvedTrigger->function,
            'when' => $resolvedTrigger->when,
            'scope' => $resolvedTrigger->scope,
            'namespace' => $namespace,
            'class_name' => $className,
            'definition' => $resolvedTrigger->definition,
            'content' => $resolvedTrigger->content,
        ];

        if ($this->databasePlatformResolver->isPostgreSQL()) {
            $template = 'PostgresqlTrigger.tpl.php';
            $params['events'] = strtoupper(implode(' OR ', $resolvedTrigger->events));
            $params['return_value'] = ($resolvedTrigger->when === 'AFTER') ? 'NULL' : 'NEW';

        } elseif ($this->databasePlatformResolver->isMySQL()) {
            if (count($resolvedTrigger->events) > 1) {
                throw new \InvalidArgumentException(
                    sprintf('MySQL does not support multiple events for a single trigger. Found: %s', implode(', ', $resolvedTrigger->events))
                );
            }
            $template = 'MysqlTrigger.tpl.php';
            $params['events'] = strtoupper($resolvedTrigger->events[0]);

        } elseif ($this->databasePlatformResolver->isSQLServer()) {
            if ($resolvedTrigger->when === 'BEFORE') {
                throw new \InvalidArgumentException('SQL Server does not support before event');
            }
            $template = 'SqlServerTrigger.tpl.php';
            $params['events'] = strtoupper($resolvedTrigger->events[0]);

        } else {
            throw new \RuntimeException(sprintf('Database platform "%s" is not supported for PHP class generation.', $this->databasePlatformResolver->getPlatformName()));
        }

        try {
            $this->generator->generateClass(
                $triggerClassNameDetails->getFullName(),
                __DIR__ . "/../Symfony/Maker/Resources/skeleton/php/$template",
                $params
            );
        } catch (RuntimeCommandException $exception) {
            if (str_contains($exception->getMessage(), 'already exists')) {
                throw new TriggerClassAlreadyExistsException($triggerClassNameDetails->getFullName());
            }

            throw $exception;
        }

        return $triggerClassNameDetails;
    }

    private function createTriggerSqlFiles(string $namespace, ResolvedTrigger $resolvedTrigger): void
    {
        switch (true) {
            case $this->databasePlatformResolver->isPostgreSQL():
                $params = [
                    'trigger_name' => $resolvedTrigger->name,
                    'table_name' => $resolvedTrigger->table,
                    'function_name' => $resolvedTrigger->function,
                    'when' => $resolvedTrigger->when,
                    'scope' => $resolvedTrigger->scope,
                    'events' => strtoupper(implode(' OR ', $resolvedTrigger->events)),
                    'return_value' => ($resolvedTrigger->when === 'AFTER') ? 'NULL' : 'NEW',
                    'content' => $resolvedTrigger->content,
                    'definition' => $resolvedTrigger->definition,
                ];

                $functionFilePath = $this->storageResolver->getFunctionSqlFilePathForNamespace(
                    $namespace,
                    $resolvedTrigger
                );
                if (!file_exists($functionFilePath)) {
                    $this->generator->generateFile(
                        $functionFilePath,
                        __DIR__ . '/../Symfony/Maker/Resources/skeleton/sql/postgresql_function.tpl.php',
                        $params
                    );
                }

                $triggerTemplate = 'postgresql_trigger.tpl.php';

                break;
            case $this->databasePlatformResolver->isMySQL():
                if (count($resolvedTrigger->events) > 1) {
                    throw new \InvalidArgumentException('MySQL does not support multiple events for a single trigger.');
                }

                $params = [
                    'trigger_name' => $resolvedTrigger->name,
                    'table_name' => $resolvedTrigger->table,
                    'when' => $resolvedTrigger->when,
                    'events' => strtoupper($resolvedTrigger->events[0]),
                ];

                $triggerTemplate = 'mysql_trigger.tpl.php';

                break;
            case $this->databasePlatformResolver->isSQLServer():
                if ($resolvedTrigger->when === 'BEFORE') {
                    throw new \InvalidArgumentException('SQL Server does not support before event');
                }

                $params = [
                    'trigger_name' => $resolvedTrigger->name,
                    'table_name' => $resolvedTrigger->table,
                    'when' => $resolvedTrigger->when,
                    'events' => strtoupper($resolvedTrigger->events[0]),
                ];

                $triggerTemplate = 'sqlserver_trigger.tpl.php';

                break;
            default:
                throw new \InvalidArgumentException('Unsupported database platform');
        }

        $this->generateFile(
            $this->storageResolver->getTriggerSqlFilePathForNamespace($namespace, $resolvedTrigger),
            $triggerTemplate,
            $params
        );
    }

    /**
     * @param array<string, mixed> $params
     */
    private function generateFile(string $path, string $template, array $params): void
    {
        try {
            $this->generator->generateFile(
                $path,
                __DIR__ . '/../Symfony/Maker/Resources/skeleton/sql/' . $template,
                $params
            );
        } catch (RuntimeCommandException $exception) {
            if (str_contains($exception->getMessage(), 'already exists')) {
                throw new TriggerSqlFileAlreadyExistsException($path);
            }

            throw $exception;
        }
    }

    /**
     * @param array<int, array{
     *           classDetails: ClassNameDetails,
     *           resolvedTrigger: ResolvedTrigger
     *       }> $triggersForMigrationsPHP
     * @param array<int, ResolvedTrigger> $triggersForMigrationsSQL
     */
    private function createMigration(string $namespace, array $triggersForMigrationsPHP, array $triggersForMigrationsSQL, ?StyleInterface $io = null): void
    {
        if (!class_exists('Doctrine\Migrations\DependencyFactory')) {
            if ($io) {
                $io->warning([
                    'Migration generation is enabled, but "doctrine/doctrine-migrations-bundle" is not installed.',
                    'Please run "composer require doctrine/doctrine-migrations-bundle" to generate migrations.',
                ]);
            }

            return;
        }

        $upPhpCode = [];
        $upPhpCode[] = '// This migration get the trigger SQL by calling the static methods of your trigger class or reads the SQL from files.';

        $downPhpCode = [];
        $downPhpCode[] = '// Reverting this migration will drop the trigger and function.';

        foreach ($triggersForMigrationsPHP as $triggersData) {
            $this->createMigrationFromPhpClass(
                $triggersData['resolvedTrigger'],
                $triggersData['classDetails'],
                $upPhpCode,
                $downPhpCode
            );
        }

        foreach ($triggersForMigrationsSQL as $resolvedTrigger) {
            $this->createMigrationFromSqlFiles(
                $namespace,
                $resolvedTrigger,
                $upPhpCode,
                $downPhpCode
            );
        }

        $this->createMigrationFile($upPhpCode, $downPhpCode, $io);
    }

    /**
     * @param string[] $downPhpCode
     * @param string[] $upPhpCode
     */
    private function createMigrationFromPhpClass(ResolvedTrigger $resolvedTrigger, ClassNameDetails $classNameDetails, array &$upPhpCode, array &$downPhpCode): void
    {
        $fqcn = $classNameDetails->getFullName();

        if ($this->databasePlatformResolver->isPostgreSQL()) {
            $upPhpCode[] = '$this->addSql(\\' . $fqcn . '::getFunction());';
        }
        $upPhpCode[] = '$this->addSql(\\' . $fqcn . '::getTrigger());';

        if ($this->databasePlatformResolver->isPostgreSQL()) {
            $downPhpCode[] = '$this->addSql("DROP TRIGGER IF EXISTS ' . $resolvedTrigger->name . ' ON ' . $resolvedTrigger->table . ';");';
            $downPhpCode[] = '$this->addSql("DROP FUNCTION IF EXISTS ' . $resolvedTrigger->function . '();");';
        } else {
            $downPhpCode[] = '$this->addSql("DROP TRIGGER IF EXISTS ' . $resolvedTrigger->name . ';");';
        }
    }

    /**
     * @param string[] $downPhpCode
     * @param string[] $upPhpCode
     */
    private function createMigrationFromSqlFiles(string $namespace, ResolvedTrigger $resolvedTrigger, array &$upPhpCode, array &$downPhpCode): void
    {
        $storageDirectory = $this->storageResolver->getResolvedDirectoryForNamespace($namespace);
        $storageDirName = basename($storageDirectory);

        if ($this->databasePlatformResolver->isPostgreSQL()) {
            $functionRelativePath = sprintf('/../%s/functions/%s.sql', $storageDirName, $resolvedTrigger->function);
            $upPhpCode[] = '$this->addSql(file_get_contents(__DIR__ . \'' . $functionRelativePath . '\'));';
            $triggerRelativePath = sprintf('/../%s/triggers/%s.sql', $storageDirName, $resolvedTrigger->name);
            $upPhpCode[] = '$this->addSql(file_get_contents(__DIR__ . \'' . $triggerRelativePath . '\'));';
        } else {
            // First we have to drop the trigger if exists (only in MySQL mode, in postgre we "create or replace")
            $upPhpCode[] = '$this->addSql("DROP TRIGGER IF EXISTS ' . $resolvedTrigger->name . ';");';
            $triggerRelativePath = sprintf('/../%s/%s.sql', $storageDirName, $resolvedTrigger->name);
            $upPhpCode[] = '$this->addSql(file_get_contents(__DIR__ . \'' . $triggerRelativePath . '\'));';
        }

        $downPhpCode = [];
        $downPhpCode[] = '// Reverting this migration will drop the trigger and function.';
        if ($this->databasePlatformResolver->isPostgreSQL()) {
            $downPhpCode[] = '$this->addSql("DROP TRIGGER IF EXISTS ' . $resolvedTrigger->name . ' ON ' . $resolvedTrigger->table . ';");';
            if ($resolvedTrigger->function) {
                $downPhpCode[] = '$this->addSql("DROP FUNCTION IF EXISTS ' . $resolvedTrigger->function . '();");';
            }
        } else {
            $downPhpCode[] = '$this->addSql("DROP TRIGGER IF EXISTS ' . $resolvedTrigger->name . ';");';
        }
    }

    /**
     * @param string[] $downPhpCode
     * @param string[] $upPhpCode
     */
    private function createMigrationFile(array $upPhpCode, array $downPhpCode, ?StyleInterface $io = null): void
    {
        $migrationGenerator = $this->dependencyFactory->getMigrationGenerator();

        $configuration = $this->dependencyFactory->getConfiguration();
        $dirs = $configuration->getMigrationDirectories();

        if (count($dirs) === 1) {
            $namespace = key($dirs);
        } else {
            $namespace = 'DoctrineMigrations';
        }

        $up = implode("\n", $upPhpCode);
        $down = implode("\n", $downPhpCode);

        $className = $this->dependencyFactory->getClassNameGenerator()->generateClassName($namespace);
        $path = $migrationGenerator->generateMigration($className, $up, $down);

        if ($io) {
            $io->text('</>Generated new migration class to <info>' . basename($path) . '</>');
        }
    }
}
