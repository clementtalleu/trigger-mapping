<?php

declare(strict_types=1);

namespace Talleu\TriggerMapping\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Style\SymfonyStyle;
use Talleu\TriggerMapping\Storage\StorageResolverInterface;

trait WithNamespaceOptionTrait
{
    protected function getNamespace(
        StorageResolverInterface $storageResolver,
        SymfonyStyle $io,
        InputInterface $input,
        OutputInterface $output
    ): string {
        $namespace = $input->getOption('namespace');
        if ($namespace === '') {
            $namespace = null;
        }

        $dirs = $storageResolver->getAvailableNamespaces();
        if ($namespace === null && \count($dirs) === 1) {
            $namespace = current($dirs);
        } elseif ($namespace === null && \count($dirs) > 1) {
            $helper = $this->getHelper('question');
            $question = new ChoiceQuestion(
                'Please choose a namespace (defaults to the first one)',
                $dirs,
                0,
            );
            $namespace = $helper->ask($input, $output, $question);
            $io->text(\sprintf('You have selected the "%s" namespace', $namespace));
        }

        if (!\in_array($namespace, $dirs, true)) {
            throw new \InvalidArgumentException(\sprintf('The namespace "%s" is not invalid', $namespace));
        }

        assert(is_string($namespace));

        return $namespace;
    }
}
