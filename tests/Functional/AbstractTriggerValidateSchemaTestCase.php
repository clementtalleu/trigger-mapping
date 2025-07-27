<?php

namespace Talleu\TriggerMapping\Tests\Functional;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Console\Tester\CommandTester;
use Talleu\TriggerMapping\Tests\Application\Entity\CorrectlyMappedEntity;
use Talleu\TriggerMapping\Tests\Application\Entity\MissingInDbEntity;
use Talleu\TriggerMapping\Tests\Application\Entity\MysqlCorrectlyMappedEntity;
use Talleu\TriggerMapping\Tests\Application\Entity\NoTriggerEntity;
use Talleu\TriggerMapping\Tests\Application\Entity\TriggerBadParamsEntity;

abstract class AbstractTriggerValidateSchemaTestCase extends KernelTestCase
{
    protected CommandTester $commandTester;
    protected Connection $connection;
    protected EntityManagerInterface $entityManager;
    protected Application $application;

    protected function setUp(): void
    {
        $kernel = self::bootKernel();
        $this->application = new Application($kernel);
        $container = $kernel->getContainer();
        $this->connection = $container->get('doctrine.dbal.default_connection');
        $this->entityManager = $container->get('doctrine.orm.entity_manager');

        $this->runCommand('doctrine:database:drop --force --if-exists');
        $this->runCommand('doctrine:database:create');

        $this->createSchemaForEntities([
            NoTriggerEntity::class,
            CorrectlyMappedEntity::class,
            MissingInDbEntity::class,
            MysqlCorrectlyMappedEntity::class,
            TriggerBadParamsEntity::class,
        ]);
    }

    public function runCommand(string $command)
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
}
