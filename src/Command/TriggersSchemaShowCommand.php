<?php

declare(strict_types=1);

namespace Talleu\TriggerMapping\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Talleu\TriggerMapping\Metadata\TriggersMappingInterface;
use Talleu\TriggerMapping\Model\ResolvedTrigger;
use Talleu\TriggerMapping\Storage\Storage;
use Talleu\TriggerMapping\Storage\StorageResolverInterface;

#[AsCommand(
    name: 'triggers:schema:show',
    description: 'Print the SQL of each mapped trigger without touching the database. Useful for code reviews and CI logs.',
    aliases: ['t:s:show']
)]
final class TriggersSchemaShowCommand extends Command
{
    public function __construct(
        private readonly TriggersMappingInterface $triggersMapping,
        private readonly StorageResolverInterface $storageResolver,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption(
            'entity',
            null,
            InputOption::VALUE_REQUIRED,
            'Show only the triggers of the given entity FQCN (e.g. App\\Entity\\User).'
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $entityName = $input->getOption('entity');
        $triggers = $this->triggersMapping->extractTriggerMapping($entityName);

        if ([] === $triggers) {
            $io->info('No triggers are currently mapped.');

            return Command::SUCCESS;
        }

        $shown = 0;
        $unresolved = 0;
        foreach ($triggers as $trigger) {
            $io->section(sprintf(
                '%s — table: %s, when: %s, scope: %s, events: [%s]',
                $trigger->name,
                $trigger->table,
                $trigger->when,
                $trigger->scope,
                implode(', ', $trigger->events),
            ));

            $sql = $this->resolveSql($trigger);
            if (null === $sql) {
                $io->warning(sprintf(
                    'No SQL source could be resolved for "%s". Check `className` (storage=php) or the .sql files in %s.',
                    $trigger->name,
                    $this->storageResolver->getResolvedDirectory(),
                ));
                ++$unresolved;
                continue;
            }

            $output->writeln('<fg=gray>'.trim($sql).'</>');
            ++$shown;
        }

        $io->newLine();
        $io->success(sprintf(
            '%d trigger(s) shown, %d unresolved.',
            $shown,
            $unresolved
        ));

        return Command::SUCCESS;
    }

    private function resolveSql(ResolvedTrigger $trigger): ?string
    {
        if (Storage::PHP_CLASSES->value === $trigger->storage) {
            return $this->resolveFromPhpClass($trigger);
        }

        return $this->resolveFromSqlFiles($trigger);
    }

    private function resolveFromPhpClass(ResolvedTrigger $trigger): ?string
    {
        $fqcn = $trigger->className;
        if (null === $fqcn || !class_exists($fqcn)) {
            return null;
        }

        $sql = '';
        if (method_exists($fqcn, 'getFunction')) {
            $sql .= "-- Function:\n".$fqcn::getFunction()."\n\n";
        }
        if (method_exists($fqcn, 'getTrigger')) {
            $sql .= "-- Trigger:\n".$fqcn::getTrigger();
        }

        return '' !== $sql ? $sql : null;
    }

    private function resolveFromSqlFiles(ResolvedTrigger $trigger): ?string
    {
        $dir = $this->storageResolver->getResolvedDirectory();
        $sql = '';

        if (null !== $trigger->function) {
            $functionPath = sprintf('%s/functions/%s.sql', $dir, $trigger->function);
            if (is_file($functionPath)) {
                $sql .= "-- Function ({$functionPath}):\n".file_get_contents($functionPath)."\n\n";
            }
        }

        // PostgreSQL puts the trigger under triggers/, MySQL/SqlServer at the root.
        $candidates = [
            sprintf('%s/triggers/%s.sql', $dir, $trigger->name),
            sprintf('%s/%s.sql', $dir, $trigger->name),
        ];
        foreach ($candidates as $path) {
            if (is_file($path)) {
                $sql .= "-- Trigger ({$path}):\n".file_get_contents($path);
                break;
            }
        }

        return '' !== $sql ? $sql : null;
    }
}
