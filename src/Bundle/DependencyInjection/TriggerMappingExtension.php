<?php

declare(strict_types=1);

namespace Talleu\TriggerMapping\Bundle\DependencyInjection;

use Symfony\Bundle\MakerBundle\Maker\AbstractMaker;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;
use Talleu\TriggerMapping\Storage\Storage;
use Talleu\TriggerMapping\Storage\StorageResolver;

final class TriggerMappingExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $loader = new XmlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
        $loader->load('services.xml');

        // Loads services requiring the maker bundle (everything to do with file generation)
        if (class_exists(AbstractMaker::class)) {
            $loader->load('maker.xml');
        }

        foreach ($config['storages'] as $storage) {
            // Validate storage type
            if (null === Storage::tryFrom($storage['type'])) {
                throw new \InvalidArgumentException(
                    sprintf(
                        'Invalid storage type "%s". Allowed values are: %s',
                        $storage['type'],
                        implode(', ', array_column(Storage::cases(), 'value'))
                    )
                );
            }
        }

        $definition = new Definition(StorageResolver::class, [$config['storages']]);
        $container->setDefinition('trigger_mapping.storage_resolver', $definition);

        $container->setParameter('trigger_mapping.migrations', $config['migrations']);

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
