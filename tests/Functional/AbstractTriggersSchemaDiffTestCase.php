<?php

declare(strict_types=1);

namespace Talleu\TriggerMapping\Tests\Functional;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Common scaffolding for `triggers:schema:diff` tests across platforms.
 *
 * Each test boots the kernel, drops & recreates the test database, and ensures
 * the trigger storage directory is empty so generated files can be asserted.
 */
abstract class AbstractTriggersSchemaDiffTestCase extends KernelTestCase
{
    use DatabaseCleanupTrait;

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
        $this->triggersDir = $kernel->getProjectDir().'/triggers';

        $this->runCommand('doctrine:database:create --if-not-exists');
        $this->cleanupDatabase($this->connection);

        $fs = new Filesystem();
        if ($fs->exists($this->triggersDir)) {
            $fs->remove($this->triggersDir);
        }
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        $fs = new Filesystem();
        if ($fs->exists($this->triggersDir)) {
            $fs->remove($this->triggersDir);
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
}
