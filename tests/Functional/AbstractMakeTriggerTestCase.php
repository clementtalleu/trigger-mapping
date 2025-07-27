<?php

namespace Talleu\TriggerMapping\Tests\Functional;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;
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

        $container = $kernel->getContainer();
        $this->entityManager = $container->get('doctrine.orm.entity_manager');
        $this->triggersDir =  $kernel->getProjectDir().'/triggers';

        $filesystem = new Filesystem();
        if ($filesystem->exists($this->triggersDir)) {
            $filesystem->remove($this->triggersDir);
        }
        // Not same directories for mysql and postgre
        $this->createDirs();

        $this->createSchemaForEntities([NoTriggerEntity::class]);
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
