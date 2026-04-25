<?php

namespace Talleu\TriggerMapping\Tests\Functional;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Filesystem\Filesystem;
use Talleu\TriggerMapping\Tests\Application\Entity\NoTriggerEntity;

abstract class AbstractMakeTriggerTestCase extends KernelTestCase
{
    protected Application $application;
    protected EntityManagerInterface $entityManager;
    protected ?string $triggersDir = null;

    protected function setUp(): void
    {
        $kernel = self::bootKernel();
        $this->application = new Application($kernel);
        $this->application->setAutoExit(false);

        $container = $kernel->getContainer();
        $this->entityManager = $container->get('doctrine.orm.entity_manager');
        $this->triggersDir = $kernel->getProjectDir().'/triggers';

        // Ensure the test database exists (SQL Server does not auto-create it like MySQL/PostgreSQL)
        $this->runCommand('doctrine:database:drop --force --if-exists');
        $this->runCommand('doctrine:database:create');

        $filesystem = new Filesystem();
        if ($filesystem->exists($this->triggersDir)) {
            $filesystem->remove($this->triggersDir);
        }
        // Not same directories for mysql and postgre
        $this->createDirs();

        $this->createSchemaForEntities([NoTriggerEntity::class]);
    }

    protected function runCommand(string $command): void
    {
        $input = new StringInput($command);
        $this->application->run($input, new NullOutput());
    }

    abstract public function createDirs();

    protected function tearDown(): void
    {
        parent::tearDown();

        $filesystem = new Filesystem();
        $filesystem->remove($this->triggersDir);

        // Clean up entity file modifications if necessary
        $reflection = new \ReflectionClass(NoTriggerEntity::class);
        $filePath = $reflection->getFileName();
        $originalContent = file_get_contents(str_replace('.php', '.original', $filePath));
        file_put_contents($filePath, $originalContent);
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
        $schemaTool->dropSchema($metadata);
        $schemaTool->createSchema($metadata);
    }
}
