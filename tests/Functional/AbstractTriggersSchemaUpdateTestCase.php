<?php

declare(strict_types=1);

namespace Talleu\TriggerMapping\Tests\Functional;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\NullOutput;

abstract class AbstractTriggersSchemaUpdateTestCase extends KernelTestCase
{
    protected Application $application;
    protected Connection $connection;
    protected EntityManagerInterface $entityManager;
    protected string $triggersDir;

    protected function setUp(): void
    {
        $kernel = self::bootKernel();
        $this->application = new Application($kernel);
        $this->application->setAutoExit(false);

        $container = $kernel->getContainer();
        $this->connection = $container->get('doctrine.dbal.default_connection');
        $this->entityManager = $container->get('doctrine.orm.entity_manager');

        $this->cleanup();
        $this->runCommand('doctrine:database:drop --force --if-exists');
        $this->runCommand('doctrine:database:create');
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->cleanup();
    }

    private function cleanup(): void
    {
        $schemaManager = $this->connection->createSchemaManager();
        $tables = $schemaManager->listTables();
        foreach ($tables as $table) {
            $schemaManager->dropTable($table->getName());
        }
    }

    /**
     * @param string[] $entityClasses
     */
    protected function createSchemaForEntities(array $entityClasses): void
    {
        $schemaTool = new SchemaTool($this->entityManager);
        $metadata = [];
        foreach ($entityClasses as $class) {
            $metadata[] = $this->entityManager->getClassMetadata($class);
        }
        $schemaTool->createSchema($metadata);
    }

    protected function runCommand(string $command): void
    {
        $input = new StringInput($command);
        $this->application->run($input, new NullOutput());
    }

    protected function executeSql(string $sql): void
    {
        $this->connection->executeStatement($sql);
    }

    protected function triggerExists(string $triggerName): bool
    {
        $platform = $this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform ? 'postgresql' : 'mysql';

        if ($platform === 'mysql') {
            $sql = "SELECT COUNT(*) FROM information_schema.TRIGGERS WHERE TRIGGER_NAME = ?";
        } else {
            $sql = "SELECT COUNT(*) FROM pg_trigger WHERE tgname = ?";
        }

        $result = $this->connection->fetchOne($sql, [$triggerName]);

        return $result > 0;
    }
}
