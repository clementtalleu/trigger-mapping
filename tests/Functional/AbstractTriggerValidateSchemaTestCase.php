<?php

namespace Talleu\TriggerMapping\Tests\Functional;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Console\Tester\CommandTester;
use Talleu\TriggerMapping\Exception\NotAnEntityException;
use Talleu\TriggerMapping\Tests\Application\Entity\CorrectlyMappedEntity;
use Talleu\TriggerMapping\Tests\Application\Entity\MissingInDbEntity;
use Talleu\TriggerMapping\Tests\Application\Entity\MysqlCorrectlyMappedEntity;
use Talleu\TriggerMapping\Tests\Application\Entity\NoTriggerEntity;
use Talleu\TriggerMapping\Tests\Application\Entity\RepeatableTriggerEntity;
use Talleu\TriggerMapping\Tests\Application\Entity\SqlServerCorrectlyMappedEntity;
use Talleu\TriggerMapping\Tests\Application\Entity\TriggerBadParamsEntity;

abstract class AbstractTriggerValidateSchemaTestCase extends KernelTestCase
{
    use DatabaseCleanupTrait;

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

        // Make sure the test database exists (SQL Server doesn't auto-create it).
        $this->runCommand('doctrine:database:create --if-not-exists');
        // Always start each test from an empty schema — works around the fact that
        // `doctrine:database:drop` is flaky on SQL Server in CI when the runner still
        // holds an open connection to the database.
        $this->cleanupDatabase($this->connection);

        $this->createSchemaForEntities([
            NoTriggerEntity::class,
            CorrectlyMappedEntity::class,
            MissingInDbEntity::class,
            MysqlCorrectlyMappedEntity::class,
            SqlServerCorrectlyMappedEntity::class,
            TriggerBadParamsEntity::class,
            RepeatableTriggerEntity::class,
        ]);
    }

    public function testInvalidEntityNameThrows(): void
    {
        $command = $this->application->find('triggers:schema:validate');
        $tester = new CommandTester($command);

        $this->expectException(NotAnEntityException::class);
        $tester->execute(['--entity' => 'App\\Entity\\DoesNotExist']);
    }

    public function testNonDoctrineClassThrows(): void
    {
        $command = $this->application->find('triggers:schema:validate');
        $tester = new CommandTester($command);

        $this->expectException(NotAnEntityException::class);
        // \stdClass exists but is not a Doctrine entity.
        $tester->execute(['--entity' => \stdClass::class]);
    }

    public function testRepeatableTriggersAreBothDetected(): void
    {
        $command = $this->application->find('triggers:schema:validate');
        $tester = new CommandTester($command);
        $tester->execute(['--entity' => RepeatableTriggerEntity::class]);

        // Both triggers are mapped but neither exists in the DB so the command must fail
        // and surface the two distinct trigger names — proving IS_REPEATABLE is honored.
        self::assertSame(Command::FAILURE, $tester->getStatusCode());
        self::assertStringContainsString('trg_repeatable_a', $tester->getDisplay());
        self::assertStringContainsString('trg_repeatable_b', $tester->getDisplay());
    }

    public function testExcludedTriggerIsIgnoredFromValidation(): void
    {
        // The yaml config under tests/Application/config/packages/trigger_mapping.yaml
        // declares `excluded_trigger_for_test` in `excludes`. We create that trigger
        // in the database and assert that validate does NOT list it as "missing in mapping".
        $this->createExcludedTrigger('excluded_trigger_for_test');

        $command = $this->application->find('triggers:schema:validate');
        $tester = new CommandTester($command);
        $tester->execute([]);

        // Without the excludes filter, the trigger would surface as "Missing in Mapping".
        self::assertStringNotContainsString('excluded_trigger_for_test', $tester->getDisplay());
    }

    /**
     * Each platform creates the test trigger with its own DDL.
     * Platforms that don't override this method will skip the excludes test.
     */
    protected function createExcludedTrigger(string $triggerName): void
    {
        self::markTestSkipped('No createExcludedTrigger() implementation for this platform.');
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
