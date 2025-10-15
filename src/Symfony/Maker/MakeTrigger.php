<?php

declare(strict_types=1);

namespace Talleu\TriggerMapping\Symfony\Maker;

use Doctrine\ORM\Mapping\ClassMetadata;
use Symfony\Bundle\MakerBundle\ConsoleStyle;
use Symfony\Bundle\MakerBundle\DependencyBuilder;
use Symfony\Bundle\MakerBundle\Doctrine\DoctrineHelper;
use Symfony\Bundle\MakerBundle\Generator;
use Symfony\Bundle\MakerBundle\InputConfiguration;
use Symfony\Bundle\MakerBundle\Maker\AbstractMaker;
use Symfony\Bundle\MakerBundle\Str;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Talleu\TriggerMapping\Attribute\Trigger;
use Talleu\TriggerMapping\Factory\MappingCreator;
use Talleu\TriggerMapping\Factory\TriggerCreatorInterface;
use Talleu\TriggerMapping\Model\ResolvedTrigger;
use Talleu\TriggerMapping\Platform\DatabasePlatformResolver;
use Talleu\TriggerMapping\Storage\Storage;
use Symfony\Component\Console\Question\ConfirmationQuestion;

final class MakeTrigger extends AbstractMaker
{
    public static function getCommandName(): string
    {
        return 'make:trigger';
    }

    public static function getCommandDescription(): string
    {
        return 'Create triggers files and update a Doctrine entity';
    }

    public function __construct(
        private DoctrineHelper               $doctrineHelper,
        private DatabasePlatformResolver     $databasePlatformResolver,
        private TriggerCreatorInterface      $triggerCreator,
        private MappingCreator               $mappingCreator,
        private bool                         $migrations,
    ) {
    }

    public function configureCommand(Command $command, InputConfiguration $inputConfig): void
    {
        $helpContent = file_get_contents(__DIR__ . '/Resources/help/MakeTriggerClass.txt');
        $command
            ->setHelp($helpContent ?: '')
            ->addArgument('entity-class', InputArgument::OPTIONAL, sprintf('Class name of the entity to update (e.g. <fg=yellow>%s</>)', Str::asClassName(Str::getRandomTerm())))
            ->addArgument('trigger-name', InputArgument::OPTIONAL, 'The name for the database trigger (e.g. <fg=yellow>trg_contact_count_unread</>)');

        $isPostgreSQL = $this->databasePlatformResolver->isPostgreSQL();
        if ($isPostgreSQL) {
            $command->addArgument('function-name', InputArgument::OPTIONAL, 'The name for the SQL function to be executed by the trigger (e.g. <fg=yellow>fn_update_contact_total_unread</>)');
        }

        if ($isPostgreSQL) {
            $command->addArgument('on', InputArgument::OPTIONAL, 'When the trigger should fire: "insert", "update", "delete" (comma-separated for multiple e.g. <fg=yellow>insert,update</>)');
        } else {
            $command->addArgument('on', InputArgument::OPTIONAL, 'When the trigger should fire: "insert", "update", "delete" (only 1 for Mysql/MariaDB)');
        }

        if ($isPostgreSQL) {
            $command->addArgument('scope', InputArgument::OPTIONAL, 'Trigger scope: "ROW" or "STATEMENT"');
        }

        $command
            ->addArgument('when', InputArgument::OPTIONAL, 'Trigger timing: "AFTER" or "BEFORE"')
            ->addArgument('storage', InputArgument::OPTIONAL, 'Storage for the logic: "php" or "sql"')
            ->addOption(
                'migration',
                'm',
                InputOption::VALUE_NONE,
                'Create a Doctrine migration file for execute the trigger'
            );
    }

    public function interact(InputInterface $input, ConsoleStyle $io, Command $command): void
    {
        if (!$input->getOption('migration')) {
            $question = new ConfirmationQuestion(
                'Do you want to generate a Doctrine migration for this trigger?',
                $this->migrations
            );

            $createMigration = $io->askQuestion($question);
            $input->setOption('migration', $createMigration);
        }
    }

    public function generate(InputInterface $input, ConsoleStyle $io, Generator $generator): void
    {
        $entityClassName = $input->getArgument('entity-class');
        $entityFqcn = $this->getEntityFqcn($entityClassName);
        /** @var ClassMetadata<object> $entityMetadata */
        $entityMetadata = $this->doctrineHelper->getMetadata($entityFqcn);
        $tableName = $entityMetadata->getTableName();

        // Build the trigger parameters
        $functionName = $input->hasArgument('function-name') ? $input->getArgument('function-name') : null;
        $scope = $input->hasArgument('scope') ? $input->getArgument('scope') : 'ROW';

        $allowedEvents = ['INSERT', 'UPDATE', 'DELETE'];
        $events = array_unique(array_map('trim', explode(',', strtoupper($input->getArgument('on')))));
        foreach ($events as $event) {
            if (!in_array($event, $allowedEvents)) {
                $allowedEventsString = implode(',', $allowedEvents);
                throw new \InvalidArgumentException("$event is not a valid event, should be one of : $allowedEventsString");
            }
        }

        $allowedTimings = ['AFTER', 'BEFORE'];
        $when = trim(strtoupper($input->getArgument('when')));
        if (!in_array($when, $allowedTimings)) {
            $allowedTimingsString = implode(',', $allowedTimings);
            throw new \InvalidArgumentException("{$input->getArgument('when')} is not a valid timing, should be one of : $allowedTimingsString");
        }

        $allowedStorages = [Storage::PHP_CLASSES->value, Storage::SQL_FILES->value];
        $storage = trim($input->getArgument('storage'));
        if (!in_array($storage, $allowedStorages)) {
            $allowedStoragesString = implode(',', $allowedStorages);
            throw new \InvalidArgumentException("{$storage} is not a valid storage, should be one of : $allowedStoragesString");
        }

        $resolvedTrigger = ResolvedTrigger::create(
            name: $input->getArgument('trigger-name'),
            table: $tableName,
            events: $events,
            when: $when,
            scope: $scope,
            storage: $storage,
            functionName: $functionName
        );

        $migration = $input->getOption('migration');
        $triggersClassesDetails = $this->triggerCreator->create('', [$resolvedTrigger], $migration, $io);
        /** @var class-string|null $triggerClassFqcn */
        $triggerClassFqcn = !empty($triggersClassesDetails) ? $triggersClassesDetails[0]->getFullName() : null;

        $this->mappingCreator->createMapping(
            $resolvedTrigger,
            $entityFqcn,
            $triggerClassFqcn
        );

        $generator->writeChanges();
        $this->writeSuccessMessage($io);
        $io->text('Trigger files and entity mapping created successfully!');
    }

    private function getEntityFqcn(string $shortOrFqcn): string
    {
        $allMetadata = $this->doctrineHelper->getRegistry()->getManager()->getMetadataFactory()->getAllMetadata();
        foreach ($allMetadata as $metadata) {
            if ($metadata->getReflectionClass()->getShortName() === $shortOrFqcn) {
                return $metadata->getName();
            }
        }

        if (class_exists($shortOrFqcn)) {
            return $shortOrFqcn;
        }

        throw new \InvalidArgumentException("Could not find a valid entity class for '{$shortOrFqcn}'.");
    }

    public function configureDependencies(DependencyBuilder $dependencies, ?InputInterface $input = null): void
    {
        $dependencies->addClassDependency(
            Trigger::class,
            'trigger'
        );
    }
}
