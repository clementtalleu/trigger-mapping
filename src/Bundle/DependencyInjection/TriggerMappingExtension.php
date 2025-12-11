<?php

declare(strict_types=1);

namespace Talleu\TriggerMapping\Bundle\DependencyInjection;

use Symfony\Bundle\MakerBundle\Maker\AbstractMaker;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Talleu\TriggerMapping\Storage\Storage;

final class TriggerMappingExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $loader = new PhpFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
        $loader->load('services.php');

        // Loads services requiring the maker bundle (everything to do with file generation)
        if (class_exists(AbstractMaker::class)) {
            $loader->load('maker.php');
        }

        if (null === Storage::tryFrom($config['storage']['type'])) {
            throw new \InvalidArgumentException(
                sprintf(
                    'Invalid storage type "%s". Allowed values are: %s',
                    $config['storage']['type'],
                    implode(', ', array_column(Storage::cases(), 'value'))
                )
            );
        }

        $container->setParameter('trigger_mapping.storage.type', $config['storage']['type']);
        $container->setParameter('trigger_mapping.storage.directory', $config['storage']['directory']);
        $container->setParameter('trigger_mapping.migrations', $config['migrations']);
        $container->setParameter('trigger_mapping.storage.namespace', $config['storage']['namespace']);

        // Exclude triggers from mapping or validation
        $excludes = $config['excludes'];
        if (!is_array($excludes)) {
            throw new \InvalidArgumentException("Excludes node should be an array");
        }

        $container->setParameter('trigger_mapping.exclude', $excludes);
    }

    public function getAlias(): string
    {
        return 'trigger_mapping';
    }
}
