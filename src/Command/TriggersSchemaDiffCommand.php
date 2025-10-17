<?php

declare(strict_types=1);

namespace Talleu\TriggerMapping\Command;

use Doctrine\Migrations\Configuration\Configuration;
use Symfony\Bundle\MakerBundle\Generator;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Style\SymfonyStyle;
use Talleu\TriggerMapping\DatabaseSchema\TriggersDbExtractorInterface;
use Talleu\TriggerMapping\Factory\TriggerCreatorInterface;
use Talleu\TriggerMapping\Metadata\TriggersMappingInterface;
use Talleu\TriggerMapping\Storage\Storage;
use Talleu\TriggerMapping\Storage\StorageResolverInterface;

#[AsCommand(
    name: 'triggers:schema:diff',
    description: 'Compare entity mappings with the database schema (and create missing trigger files).',
    aliases: ['t:s:d']
)]
final class TriggersSchemaDiffCommand extends Command
{
    use WithStorageOptionTrait;

    public function __construct(
        private readonly TriggersMappingInterface     $triggersMapping,
        private readonly TriggersDbExtractorInterface $triggersDbExtractor,
        private readonly TriggerCreatorInterface      $triggerCreator,
        private readonly Generator                    $generator,
        private readonly StorageResolverInterface     $storageResolver,
        private readonly Configuration                $migrationsConfiguration,
        private readonly bool                         $createMigrations,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption(
            'apply',
            'a',
            InputOption::VALUE_NONE,
            'Create the SQL/PHP templates from the entity mapping.'
        );

        $this->addOption(
            'storage',
            null,
            InputOption::VALUE_REQUIRED,
            'The storage to use for the triggers',
        );

        $this->addOption(
            'namespace',
            null,
            InputOption::VALUE_REQUIRED,
            'The namespace to use for the migration (must be in the list of configured namespaces)',
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = (new SymfonyStyle($input, $output));
        $isApplyMode = $input->getOption('apply');

        if ($isApplyMode) {
            $io->note('Running in APPLY mode: Changes will be written to files.');
        } else {
            $io->note('Running in DRY-RUN mode. No files will be changed. Use the --apply option to execute changes.');
        }

        $storage = $this->getStorage($this->storageResolver, $io, $input);
        $migrationsNamespace = $this->createMigrations ? $this->getMigrationsNamespace($io, $input) : null;
        $entitiesTriggers = $this->triggersMapping->extractTriggerMapping();
        $entitiesTriggersNames = array_keys($entitiesTriggers);
        $dbTriggersNames = array_keys($this->triggersDbExtractor->listTriggers());
        $missingTriggersKeysNames = array_diff($entitiesTriggersNames, $dbTriggersNames);

        if (empty($missingTriggersKeysNames)) {
            $io->success('All mapped triggers already exist in the database. Nothing to do.');

            return Command::SUCCESS;
        }

        $io->section('The following triggers are mapped but missing from the database:');
        $triggersToCreate = [];
        foreach ($missingTriggersKeysNames as $missingTriggerName) {
            $triggersToCreate[] = $entitiesTriggers[$missingTriggerName];
        }

        $listItems = [];
        foreach ($triggersToCreate as $trigger) {
            $storageType = $this->storageResolver->getResolvedTriggerStorageType($storage, $trigger) === Storage::PHP_CLASSES->value ? 'PHP Class' : 'SQL File(s)';
            $listItems[] = sprintf(
                'Trigger "<info>%s</info>" will be created (Storage: <comment>%s</comment>)',
                $trigger->name,
                $storageType
            );
        }
        $io->listing($listItems);
        $io->newLine();

        if ($isApplyMode) {
            $io->text('Applying changes and creating files...');

            $this->triggerCreator->create(
                storage: $storage,
                resolvedTriggers: $triggersToCreate,
                io: $io,
                migrationsNamespace: $migrationsNamespace
            );

            $io->success('Trigger files created successfully.');
        } else {
            $io->info('To create these files, re-run the command with the --apply option.');
        }

        $this->generator->writeChanges();

        return Command::SUCCESS;
    }

    protected function getMigrationsNamespace(SymfonyStyle $io, InputInterface $input): string
    {
        $namespace = $input->getOption('namespace');
        if ($namespace === '') {
            $namespace = null;
        }

        $dirs = $this->migrationsConfiguration->getMigrationDirectories();
        if ($namespace === null && count($dirs) === 1) {
            $namespace = key($dirs);
        } elseif ($namespace === null && count($dirs) > 1) {
            $namespace = $io->askQuestion(
                new ChoiceQuestion(
                    'Please choose a namespace (defaults to the first one)',
                    array_keys($dirs),
                    0,
                )
            );
            $io->text(\sprintf('You have selected the "%s" namespace', $namespace));
        }

        if (!isset($dirs[$namespace])) {
            throw new \InvalidArgumentException(\sprintf('Path not defined for the namespace "%s"', $namespace));
        }

        assert(is_string($namespace));

        return $namespace;
    }
}
