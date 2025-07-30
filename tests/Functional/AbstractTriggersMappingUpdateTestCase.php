<?php

namespace Talleu\TriggerMapping\Tests\Functional;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Filesystem\Filesystem;
use Talleu\TriggerMapping\Tests\Application\Entity\UpdateMappingTestEntity;

abstract class AbstractTriggersMappingUpdateTestCase extends KernelTestCase
{
    protected Application $application;
    protected Connection $connection;
    protected EntityManagerInterface $entityManager;
    private ?string $originalEntityContent = null;
    private ?string $entityFilePath = null;

    /**
     * @return string The SQL statement to create a trigger for the test.
     */
    abstract protected function getCreateTriggerSql(string $triggerName, string $tableName, string $when, string $events): string;

    protected function setUp(): void
    {
        $kernel = self::bootKernel();
        $this->application = new Application($kernel);
        $container = $kernel->getContainer();

        $this->connection = $container->get('doctrine.dbal.default_connection');
        $this->entityManager = $container->get('doctrine.orm.entity_manager');

        // Drop and create the database
        $this->runCommand('doctrine:database:drop --force --if-exists');
        $this->runCommand('doctrine:database:create');

        // Prepare the entity file for modification tracking
        $this->backupEntityFile();
    }

    protected function tearDown(): void
    {
        // Restore the entity file to its original state after each test
        $this->restoreEntityFile();
        parent::tearDown();
    }

    protected function runCommand(string $command): void
    {
        $this->application->setAutoExit(false);
        $input = new StringInput($command);
        $this->application->run($input, new NullOutput());
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

    protected function executeSql(string $sql): void
    {
        $this->connection->executeStatement($sql);
    }

    private function backupEntityFile(): void
    {
        $class = UpdateMappingTestEntity::class;
        $reflection = new \ReflectionClass($class);
        $this->entityFilePath = $reflection->getFileName();

        if ($this->entityFilePath && file_exists($this->entityFilePath)) {
            $this->originalEntityContent = file_get_contents($this->entityFilePath);
        }
    }

    private function restoreEntityFile(): void
    {
        if ($this->entityFilePath && $this->originalEntityContent !== null) {
            $filesystem = new Filesystem();
            $filesystem->dumpFile($this->entityFilePath, $this->originalEntityContent);
        }
    }
}
