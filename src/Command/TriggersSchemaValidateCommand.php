<?php

declare(strict_types=1);

namespace Talleu\TriggerMapping\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Talleu\TriggerMapping\DatabaseSchema\TriggersDbExtractorInterface;
use Talleu\TriggerMapping\Metadata\TriggersMappingInterface;
use Talleu\TriggerMapping\Model\ResolvedTrigger;

#[AsCommand(name: 'triggers:schema:validate', description: 'Validate the triggers schema', aliases: ['t:s:v'])]
final class TriggersSchemaValidateCommand extends Command
{
    public function __construct(
        private readonly TriggersMappingInterface     $triggersMapping,
        private readonly TriggersDbExtractorInterface $triggersDbExtractor,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
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
        $entityName = $input->getOption('entity');

        $triggersMapping = $this->triggersMapping->extractTriggerMapping($entityName);
        $triggersSchema = $this->triggersDbExtractor->listTriggers($entityName);

        $entitiesTriggersKeys = array_keys($triggersMapping);
        $dbTriggersKeys = array_keys($triggersSchema);
        $missingTriggers = array_diff($entitiesTriggersKeys, $dbTriggersKeys);
        $missingMappings = array_diff($dbTriggersKeys, $entitiesTriggersKeys);
        $mismatchedParams = $this->compareParameters($triggersMapping, $triggersSchema);

        if ([] === $missingTriggers && [] === $missingMappings && [] === $mismatchedParams) {
            $io->success('The database triggers are in sync with the mapping.');
            return Command::SUCCESS;
        }

        $io->error('The database triggers are not sync with the current mapping.');

        if (!empty($missingTriggers)) {
            $io->section('Missing in Database');
            $io->warning([
                'The following triggers are mapped in your code but are missing from the database.',
                'A migration is likely needed to create them.',
            ]);
            $listItems = [];
            foreach ($missingTriggers as $missingTrigger) {
                $listItems[] = sprintf('<fg=yellow>%s</>', $missingTrigger);
            }
            $io->listing($listItems);
        }

        if (!empty($missingMappings)) {
            $io->section('Missing in Mapping');
            $io->note([
                'The following triggers exist in the database but are not mapped in your code.',
                'They might be old, unused, or require mapping.',
            ]);
            $listItems = [];
            foreach ($missingMappings as $missingMapping) {
                $listItems[] = sprintf('<fg=cyan>%s</>', $missingMapping);
            }
            $io->listing($listItems);

            // Suggest mapping:update
            $io->newLine();
            $io->info('To automatically create these missing mappings, you can run:');
            $io->comment('php bin/console triggers:mapping:update --apply --create-files');
        }

        if (!empty($mismatchedParams)) {
            $io->section('Mismatched Trigger Details');
            $io->text('The following triggers have parameters that do not match the database:');

            $tableHeaders = ['Trigger', 'Parameter', 'Expected', 'Actual'];
            $tableRows = [];

            foreach ($mismatchedParams as $triggerName => $mismatches) {
                foreach ($mismatches as $param => $values) {
                    $expected = is_array($values['expected']) ? '[' . implode(', ', $values['expected']) . ']' : $values['expected'];
                    $actual = is_array($values['actual']) ? '[' . implode(', ', $values['actual']) . ']' : $values['actual'];

                    $tableRows[] = [
                        sprintf('<fg=yellow>%s</>', $triggerName),
                        $param,
                        sprintf('<info>%s</>', $expected),
                        sprintf('<error>%s</>', $actual),
                    ];
                }
            }

            $io->table($tableHeaders, $tableRows);
        }

        return Command::FAILURE;
    }

    /**
     * @param array<string, ResolvedTrigger> $triggersMapping
     * @param array<string, array{
     *       name: string,
     *       table: string,
     *       events: string[],
     *       when: string,
     *       scope: string,
     *       content: string,
     *       function: ?string
     *  }> $dbTriggers
     *
     * @return array<string, array<string, array<string, mixed>>>
     */
    private function compareParameters(array $triggersMapping, array $dbTriggers): array
    {
        $mismatch = [];
        foreach ($dbTriggers as $triggerName => $triggerData) {
            if (!array_key_exists($triggerName, $triggersMapping)) {
                continue;
            }

            $mapping = $triggersMapping[$triggerName];

            if ($mapping->table !== $triggerData['table'] && $mapping->onTable !== $triggerData['table']) {
                $mismatch[$triggerName]['table'] = [
                    'expected' => $mapping->table,
                    'actual' => $triggerData['table'],
                ];
            }

            $mappingEvents = $mapping->events;
            $triggerEvents = $triggerData['events'];
            $lowerMappingEvents = array_map('strtolower', $mappingEvents);
            $lowerTriggerEvents = array_map('strtolower', $triggerEvents);
            sort($lowerMappingEvents);
            sort($lowerTriggerEvents);
            if ($lowerMappingEvents !== $lowerTriggerEvents) {
                $mismatch[$triggerName]['events'] = [
                    'expected' => $mapping->events,
                    'actual' => $triggerData['events'],
                ];
            }

            if ($mapping->when !== $triggerData['when']) {
                $mismatch[$triggerName]['when'] = [
                    'expected' => $mapping->when,
                    'actual' => $triggerData['when'],
                ];
            }

            if ($mapping->scope !== $triggerData['scope']) {
                $mismatch[$triggerName]['scope'] = [
                    'expected' => $mapping->scope,
                    'actual' => $triggerData['scope'],
                ];
            }

            if ($mapping->function !== $triggerData['function']) {
                $mismatch[$triggerName]['function'] = [
                    'expected' => $mapping->function,
                    'actual' => $triggerData['function'],
                ];
            }
        }

        return $mismatch;
    }
}
