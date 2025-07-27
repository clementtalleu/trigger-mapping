<?php

declare(strict_types=1);

namespace Talleu\TriggerMapping\Bundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Talleu\TriggerMapping\Storage\Storage;

class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('trigger_mapping');

        $rootNode = $treeBuilder->getRootNode();
        $rootNode
            ->children()
                ->arrayNode('storage')
                    ->addDefaultsIfNotSet()
                        ->children()
                            ->enumNode('type')
                            ->values([Storage::SQL_FILES->value, Storage::PHP_CLASSES->value])
                            ->defaultValue(Storage::PHP_CLASSES->value)
                            ->info('Determines whether triggers are stored in SQL (.sql files) or PHP (static functions).')
                        ->end()
                        ->scalarNode('namespace')
                            ->cannotBeEmpty()
                            ->defaultValue('App\\Triggers')
                            ->info('Determines the namespace for triggers classes.')
                        ->end()
                        ->scalarNode('directory')
                            ->defaultValue('%kernel.project_dir%/triggers')
                            ->info('Directory where triggers/functions are stored, depending on the selected type.')
                        ->end()
                    ->end()
                ->end()
                ->booleanNode('migrations')
                    ->defaultTrue()
                    ->info('Whether to automatically generate migrations for triggers.')
                ->end()
            ->end();

        return $treeBuilder;
    }
}
