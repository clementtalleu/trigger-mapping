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
    public function create(array $resolvedTriggers, ?bool $createMigrations = null, ?StyleInterface $io = null): array
    {
        $triggersForMigrationsPHP = [];
        $triggersForMigrationsSQL = [];

        foreach ($resolvedTriggers as $resolvedTrigger) {
            if ($resolvedTrigger->storage === Storage::PHP_CLASSES->value) {
                $triggerClassDetails = $this->createTriggerClass($resolvedTrigger);
                $triggersForMigrationsPHP[] = [
                    'classDetails' => $triggerClassDetails,
                    'resolvedTrigger' => $resolvedTrigger,
                ];
                $triggersClassesDetails[] = $triggerClassDetails;
            } else {
                $this->createTriggerSqlFiles($resolvedTrigger);
                $triggersForMigrationsSQL[] = $resolvedTrigger;
            }
        }

        if (true === $createMigrations || (null === $createMigrations && true === $this->migrations)) {
            $this->createMigration($triggersForMigrationsPHP, $triggersForMigrationsSQL, $io);
        }

        return $triggersClassesDetails ?? [];
    }

    private function createTriggerClass(ResolvedTrigger $resolvedTrigger): ClassNameDetails
    {
        $className = Str::asClassName($resolvedTrigger->name);
        $namespace = $this->storageResolver->getResolvedNamespace();

        // The createClassNameDetails says "but *without* the "App\\" part"
        if (str_starts_with($namespace, 'App\\')) {
            $namespace = str_replace('App\\', '', $namespace);
        }

        $triggerClassNameDetails = $this->generator->createClassNameDetails($className, $namespace);

        $params = [
            'trigger_name' => $resolvedTrigger->name,
            'table_name' => $resolvedTrigger->table,
            'function_name' => $resolvedTrigger->function,
            'timing' => $resolvedTrigger->timing,
            'scope' => $resolvedTrigger->scope,
            'namespace' => $namespace,
            'class_name' => $className,
            'definition' => $resolvedTrigger->definition,
            'content' => $resolvedTrigger->content,
        ];

        if ($this->databasePlatformResolver->isPostgreSQL()) {
            $template = 'PostgresqlTrigger.tpl.php';
            $params['events'] = strtoupper(implode(' OR ', $resolvedTrigger->events));
            $params['return_value'] = ($resolvedTrigger->timing === 'AFTER') ? 'NULL' : 'NEW';

        } elseif ($this->databasePlatformResolver->isMySQL()) {
            if (count($resolvedTrigger->events) > 1) {
                throw new \InvalidArgumentException(
                    sprintf('MySQL does not support multiple events for a single trigger. Found: %s', implode(', ', $resolvedTrigger->events))
                );
            }
            $template = 'MysqlTrigger.tpl.php';
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

    private function createTriggerSqlFiles(ResolvedTrigger $resolvedTrigger): void
    {
        $storageDirectory = $this->storageResolver->getResolvedDirectory();

        if ($this->databasePlatformResolver->isPostgreSQL()) {
            $params = [
                'trigger_name' => $resolvedTrigger->name,
                'table_name' => $resolvedTrigger->table,
                'function_name' => $resolvedTrigger->function,
                'timing' => $resolvedTrigger->timing,
                'scope' => $resolvedTrigger->scope,
                'events' => strtoupper(implode(' OR ', $resolvedTrigger->events)),
                'return_value' => ($resolvedTrigger->timing === 'AFTER') ? 'NULL' : 'NEW',
                'content' => $resolvedTrigger->content,
                'definition' => $resolvedTrigger->definition,
            ];

            $this->generator->generateFile(
                sprintf('%s/functions/%s.sql', $storageDirectory, $resolvedTrigger->function),
                __DIR__ . '/../Symfony/Maker/Resources/skeleton/sql/postgresql_function.tpl.php',
                $params
            );

            $this->generator->generateFile(
                sprintf('%s/triggers/%s.sql', $storageDirectory, $resolvedTrigger->name),
                __DIR__ . '/../Symfony/Maker/Resources/skeleton/sql/postgresql_trigger.tpl.php',
                $params
            );

        } elseif ($this->databasePlatformResolver->isMySQL()) {
            if (count($resolvedTrigger->events) > 1) {
                throw new \InvalidArgumentException('MySQL does not support multiple events for a single trigger.');
            }

            $params = [
                'trigger_name' => $resolvedTrigger->name,
                'table_name' => $resolvedTrigger->table,
                'timing' => $resolvedTrigger->timing,
                'events' => strtoupper($resolvedTrigger->events[0]),
            ];

            $triggerFilePath = sprintf('%s/%s.sql', $storageDirectory, $resolvedTrigger->name);

            try {
                $this->generator->generateFile(
                    $triggerFilePath,
                    __DIR__ . '/../Symfony/Maker/Resources/skeleton/sql/mysql_trigger.tpl.php',
                    $params
                );
            } catch (RuntimeCommandException $exception) {
                if (str_contains($exception->getMessage(), 'already exists')) {
                    throw new TriggerSqlFileAlreadyExistsException($triggerFilePath);
                }

                throw $exception;
            }
        }
    }

    /**
     * @param array<int, array{
     *           classDetails: ClassNameDetails,
     *           resolvedTrigger: ResolvedTrigger
     *       }> $triggersForMigrationsPHP
     * @param array<int, ResolvedTrigger> $triggersForMigrationsSQL
     */
    private function createMigration(array $triggersForMigrationsPHP, array $triggersForMigrationsSQL, ?StyleInterface $io = null): void
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
    private function createMigrationFromSqlFiles(ResolvedTrigger $resolvedTrigger, array &$upPhpCode, array &$downPhpCode): void
    {
        $storageDirectory = $this->storageResolver->getResolvedDirectory();
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
