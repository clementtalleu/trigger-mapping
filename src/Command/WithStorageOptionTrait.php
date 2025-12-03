<?php

declare(strict_types=1);

namespace Talleu\TriggerMapping\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Style\SymfonyStyle;
use Talleu\TriggerMapping\Storage\StorageResolverInterface;

trait WithStorageOptionTrait
{
    protected function getStorage(
        StorageResolverInterface $storageResolver,
        SymfonyStyle $io,
        InputInterface $input,
    ): string {
        $storage = $input->getOption('storage');
        if ($storage === '') {
            $storage = null;
        }

        $dirs = $storageResolver->getAvailableStorages();
        if ($storage === null && \count($dirs) === 1) {
            $storage = current($dirs);
        } elseif ($storage === null && \count($dirs) > 1) {
            $storage = $io->askQuestion(
                new ChoiceQuestion(
                    'Please choose a storage (defaults to the first one)',
                    $dirs,
                    0,
                )
            );
            $io->text(\sprintf('You have selected the "%s" storage', $storage));
        }

        if (!\in_array($storage, $dirs, true)) {
            throw new \InvalidArgumentException(\sprintf('The storage "%s" is not invalid', $storage));
        }

        assert(is_string($storage));

        return $storage;
    }
}
