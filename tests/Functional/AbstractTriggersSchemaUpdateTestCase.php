<?php

declare(strict_types=1);

namespace Talleu\TriggerMapping\Tests\Functional;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Platforms\SQLServerPlatform;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Console\Tester\CommandTester;

abstract class AbstractTriggersSchemaUpdateTestCase extends KernelTestCase
{
    use DatabaseCleanupTrait;

    protected Application $application;
    protected Connection $connection;
    protected EntityManagerInterface $entityManager;
    protected string $triggersDir;

    /**
     * @return class-string The entity class fixture to drive update tests against.
     */
    abstract protected function getTriggerEntityClass(): string;

    public function testDryRunDoesNotApplyChanges(): void
    {
        $entityClass = $this->getTriggerEntityClass();
        $this->createSchemaForEntities([$entityClass]);

        $command = $this->application->find('triggers:schema:update');
        $tester = new CommandTester($command);
        $tester->execute(['--entity' => $entityClass]);

        $tester->assertCommandIsSuccessful();
        self::assertStringContainsString('DRY-RUN', $tester->getDisplay());
        self::assertFalse(
            $this->triggerExists('trg_update_schema_test'),
            'Dry-run mode must not create the trigger in the database.'
        );
    }

    public function testConfirmationNoCancelsForceMode(): void
    {
        $entityClass = $this->getTriggerEntityClass();
        $this->createSchemaForEntities([$entityClass]);

        $command = $this->application->find('triggers:schema:update');
        $tester = new CommandTester($command);
        $tester->setInputs(['no']);
        $tester->execute(['--force' => true, '--entity' => $entityClass]);

        // Command exits successfully but should NOT have created the trigger.
        $tester->assertCommandIsSuccessful();
        self::assertStringContainsString('Operation cancelled', $tester->getDisplay());
        self::assertFalse(
            $this->triggerExists('trg_update_schema_test'),
            'Answering "no" to the confirmation prompt must not create the trigger.'
        );
    }

    protected function setUp(): void
    {
        $kernel = self::bootKernel();
        $this->application = new Application($kernel);
        $this->application->setAutoExit(false);

        $container = $kernel->getContainer();
        $this->connection = $container->get('doctrine.dbal.default_connection');
        $this->entityManager = $container->get('doctrine.orm.entity_manager');

        $this->runCommand('doctrine:database:create --if-not-exists');
        $this->cleanupDatabase($this->connection);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->cleanupDatabase($this->connection);
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
        switch ($this->connection->getDatabasePlatform()::class) {
            case PostgreSQLPlatform::class:
                $platform = 'postgresql';
                break;
            case SQLServerPlatform::class:
                $platform = 'sqlsrv';
                break;
            default:
                $platform = 'mysql';
                break;
        }

        if ($platform === 'mysql') {
            $sql = "SELECT COUNT(*) FROM information_schema.TRIGGERS WHERE TRIGGER_NAME = ?";
        } elseif ($platform === 'sqlsrv') { 
            $sql = "SELECT COUNT(*) FROM sys.triggers AS T WHERE T.name = ?";
        } else {
            $sql = "SELECT COUNT(*) FROM pg_trigger WHERE tgname = ?";
        }

        $result = $this->connection->fetchOne($sql, [$triggerName]);

        return $result > 0;
    }
}
