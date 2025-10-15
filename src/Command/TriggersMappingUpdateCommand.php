<?php

declare(strict_types=1);

namespace Talleu\TriggerMapping\Command;

use Symfony\Bundle\MakerBundle\Generator;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Talleu\TriggerMapping\DatabaseSchema\TriggersDbExtractorInterface;
use Talleu\TriggerMapping\Factory\MappingCreatorInterface;
use Talleu\TriggerMapping\Factory\TriggerCreatorInterface;
use Talleu\TriggerMapping\Metadata\TriggersMappingInterface;
use Talleu\TriggerMapping\Model\ResolvedTrigger;
use Talleu\TriggerMapping\Storage\StorageResolverInterface;
use Talleu\TriggerMapping\Utils\EntityFinder;

#[AsCommand(name: 'triggers:mapping:update', description: 'Update the entities mapping from the current database triggers', aliases: ['t:m:u'])]
final class TriggersMappingUpdateCommand extends Command
{
    use WithNamespaceOptionTrait;

    public function __construct(
        private readonly TriggersMappingInterface     $triggersMapping,
        private readonly TriggersDbExtractorInterface $triggersDbExtractor,
        private readonly StorageResolverInterface     $storageResolver,
        private readonly MappingCreatorInterface      $mappingCreator,
        private readonly TriggerCreatorInterface      $triggerCreator,
        private readonly Generator                    $generator,
        private readonly EntityFinder                 $entityFinder,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption(
            'apply',
            'a',
            InputOption::VALUE_NONE,
            'Apply the changes and create the missing mappings on entity files.'
        );

        $this->addOption(
            'create-files',
            'c',
            InputOption::VALUE_NONE,
            'Create dedicated files with content from the database.'
        );

        $this->addOption(
            'namespace',
            null,
            InputOption::VALUE_REQUIRED,
            'The namespace to use for the triggers (must be in the list of configured storages\' namespaces)',
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $isApplyMode = $input->getOption('apply');
        $isCreateFiles = $input->getOption('create-files');

        if ($isApplyMode) {
            $io->note('Running in APPLY mode: Changes will be written to files.');
        } else {
            $io->note('Running in DRY-RUN mode. No files will be changed. Use the --apply option to execute changes and --create-files to create dedicated files with SQL content.');
        }

        if (!$isCreateFiles) {
            $io->note('Running without option --create-files will only add the mapping infos with the attribute #[Trigger].');
        } else {
            if (!$isApplyMode) {
                $io->note('You cannot run this command with --create-files option but without --apply option.');
                return Command::INVALID;
            }
        }

        $namespace = $this->getNamespace($this->storageResolver, $io, $input);
        $entitiesTriggersNames = array_keys($this->triggersMapping->extractTriggerMapping());
        $dbTriggers = $this->triggersDbExtractor->listTriggers();
        $dbTriggersKeys = array_keys($dbTriggers);
        $missingTriggersMapping = array_diff($dbTriggersKeys, $entitiesTriggersNames);

        if (empty($missingTriggersMapping)) {
            $io->success('All triggers found in the database are already mapped. Nothing to do.');
            return Command::SUCCESS;
        }

        $io->section('The following trigger mappings can be created:');
        /** @var string $missingTriggerKey */
        foreach ($missingTriggersMapping as $missingTriggerKey) {
            $dbTriggerMissing = $dbTriggers[$missingTriggerKey];

            $entityFqcn = $this->entityFinder->findEntityFqcnForTable($dbTriggerMissing['table']);

            // No entity found, check if the trigger is on a join table without doctrine entity representation
            $onTable = null;
            if (null === $entityFqcn) {
                $entityFqcn = $this->entityFinder->findEntityFqcnForJoinTable($dbTriggerMissing['table']);
                $onTable = $dbTriggerMissing['table'];
            }

            if ($entityFqcn === null) {
                $io->warning(sprintf(
                    'Could not find a Doctrine entity for table "<comment>%s</comment>". Skipping trigger "<info>%s</info>".',
                    $dbTriggerMissing['table'],
                    $dbTriggerMissing['name']
                ));
                continue;
            }

            $io->text(sprintf(
                '-> Adding mapping for trigger "<info>%s</info>" to entity "<comment>%s</comment>".',
                $dbTriggerMissing['name'],
                $entityFqcn
            ));

            if ($isApplyMode) {
                $resolvedTrigger = ResolvedTrigger::create(
                    name: $dbTriggerMissing['name'],
                    table: $dbTriggerMissing['table'],
                    events: $dbTriggerMissing['events'],
                    when: $dbTriggerMissing['when'],
                    scope: $dbTriggerMissing['scope'],
                    storage: $this->storageResolver->getType($namespace),
                    functionName: $dbTriggerMissing['function'],
                    definition: $dbTriggerMissing['definition'],
                    content: $dbTriggerMissing['content']
                );

                $triggerClassFqcn = null;
                if ($isCreateFiles) {
                    $triggersClassesDetails = $this->triggerCreator->create($namespace, [$resolvedTrigger], false, $io);
                    $triggerClassFqcn = !empty($triggersClassesDetails) ? $triggersClassesDetails[0]->getFullName() : null;
                    $this->generator->writeChanges();
                }

                $this->mappingCreator->createMapping(
                    resolvedTrigger: $resolvedTrigger,
                    entityFqcn: $entityFqcn,
                    triggerClassFqcn: $triggerClassFqcn,
                    onTable: $onTable
                );
            }
        }

        if ($isApplyMode) {
            $io->success('Mapping update process finished successfully.');
        } else {
            $io->newLine();
            $io->info('To apply these changes, re-run the command with the --apply option.');
        }

        return Command::SUCCESS;
    }
}
