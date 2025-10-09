<?php

declare(strict_types=1);

namespace Talleu\TriggerMapping\Command;

use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Talleu\TriggerMapping\Contract\MySQLTriggerInterface;
use Talleu\TriggerMapping\Contract\PostgreSQLTriggerInterface;
use Talleu\TriggerMapping\Exception\CouldNotFindTriggerSqlFileException;
use Talleu\TriggerMapping\Exception\NotAnValidTriggerClassException;
use Talleu\TriggerMapping\Metadata\TriggersMappingInterface;
use Talleu\TriggerMapping\Platform\DatabasePlatformResolverInterface;
use Talleu\TriggerMapping\Storage\Storage;
use Talleu\TriggerMapping\Storage\StorageResolverInterface;

#[AsCommand(
    name: 'triggers:schema:update',
    description: 'Applies trigger changes from local files to the database schema.',
    aliases: ['t:s:u']
)]
final class TriggersSchemaUpdateCommand extends Command
{
    public function __construct(
        private readonly TriggersMappingInterface          $triggersMapping,
        private readonly StorageResolverInterface          $storageResolver,
        private readonly DatabasePlatformResolverInterface $databasePlatformResolver,
        private readonly Connection                        $connection,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption(
            'force',
            'f',
            InputOption::VALUE_NONE,
            'Execute the SQL queries to update the database schema.'
        );

        $this->addOption(
            'entity',
            null,
            InputOption::VALUE_REQUIRED,
            'Validate only the entity within the specified namespace.'
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $isForceMode = $input->getOption('force');

        if (!$isForceMode) {
            $io->note('Running in DRY-RUN mode. No changes will be made to the database.');
            $io->info('The following SQL queries would be executed. Use the --force option to apply them.');
        } else {
            $io->warning('Running in FORCE mode. The database schema will be modified.');
            if (!$io->confirm('Are you sure you want to continue?', false)) {
                $io->comment('Operation cancelled.');
                return Command::SUCCESS;
            }
        }

        $entityName = $input->getOption('entity');
        $triggersToApply = $this->triggersMapping->extractTriggerMapping($entityName);

        if (empty($triggersToApply)) {
            $io->success('No triggers are currently mapped. Nothing to do.');
            return Command::SUCCESS;
        }

        $io->section('Applying Mapped Triggers');
        $io->progressStart(count($triggersToApply));

        foreach ($triggersToApply as $trigger) {
            $io->progressAdvance();
            $io->writeln(sprintf(' > Processing trigger <info>%s</info>', $trigger->name));

            $queries = [];
            if ($trigger->storage === Storage::PHP_CLASSES->value) {
                $fqcn = $trigger->className;

                if (!$fqcn) {
                    // No class related in trigger attribute, could not find sql logic : do nothing
                    $io->warning("The trigger {$trigger->name} className property is empty, could not retrieve your trigger sql logiq");
                    continue;
                }

                if (!is_a($fqcn, MySQLTriggerInterface::class, true) && !is_a($fqcn, PostgreSQLTriggerInterface::class, true)) {
                    throw new NotAnValidTriggerClassException($fqcn);
                }

                if (method_exists($fqcn, 'getFunction')) {
                    $queryFunction = $fqcn::getFunction();
                    if ($this->databasePlatformResolver->isPostgreSQL()) {
                        $queryFunction = str_replace('CREATE FUNCTION', 'CREATE OR REPLACE FUNCTION', $queryFunction);
                    }

                    $queries[] = $queryFunction;
                }

                if (method_exists($fqcn, 'getTrigger')) {
                    // Drop before create for mysql
                    if ($this->databasePlatformResolver->isMySQL()) {
                        $queries[] = "DROP TRIGGER IF EXISTS $trigger->name;";
                    }

                    $queryTrigger = $fqcn::getTrigger();
                    if ($this->databasePlatformResolver->isPostgreSQL()) {
                        $queryTrigger = str_replace('CREATE TRIGGER', 'CREATE OR REPLACE TRIGGER', $queryTrigger);
                    }

                    $queries[] = $queryTrigger;
                }
            } else {
                if ($trigger->function) {
                    try {
                        $queries[] = file_get_contents($this->storageResolver->getFunctionSqlFilePath($trigger));
                    } catch (\InvalidArgumentException) {
                        // No sql file, but it does not means the function doesn't exists, so just warning. It will crash if no function in DB
                        $io->warning("No .sql file found for function $trigger->function");
                    }

                    // $functionFilePath = sprintf('%s/functions/%s.sql', $dir, $trigger->function);
                    // if (file_exists($functionFilePath)) {
                    //     $queries[] = file_get_contents(sprintf('%s/functions/%s.sql', $dir, $trigger->function));
                    // } else {
                    //     // No sql file, but it does not means the function doesn't exists, so just warning. It will crash if no function in DB
                    //     $io->warning("No .sql file found for function {$trigger->function}, path should be : $functionFilePath");
                    // }
                }

                // $triggerFilePath = sprintf('%s/triggers/%s.sql', $dir, $trigger->name);
                $triggerFilePath = $this->storageResolver->getTriggerSqlFilePath($trigger);
                if (!file_exists($triggerFilePath)) {
                    throw new CouldNotFindTriggerSqlFileException($triggerFilePath);
                }

                // If mysql we drop the trigger before re-create it
                if ($this->databasePlatformResolver->isMySQL()) {
                    $queries[] = "DROP TRIGGER IF EXISTS $trigger->name;";
                }

                /** @var string $query */
                $query = file_get_contents($triggerFilePath);
                if ($this->databasePlatformResolver->isPostgreSQL()) {
                    $query = str_replace('CREATE TRIGGER', 'CREATE OR REPLACE TRIGGER', $query);
                }

                $queries[] = $query;
            }

            foreach ($queries as $query) {
                $io->writeln(sprintf('<fg=gray>%s</>', trim($query)));
                if ($isForceMode) {
                    try {
                        $this->connection->executeStatement($query);
                    } catch (\Exception $e) {
                        $io->error(sprintf(
                            'An error occurred while executing the query for trigger "%s": %s',
                            $trigger->name,
                            $e->getMessage()
                        ));

                        return Command::FAILURE;
                    }
                }
            }
        }

        $io->progressFinish();

        if ($isForceMode) {
            $io->success('Database schema updated successfully.');
        } else {
            $io->info('Dry-run finished. No changes were made.');
        }

        return Command::SUCCESS;
    }
}
