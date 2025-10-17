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
            ->fixXmlConfig('storage', 'storages')
            ->children()
                ->arrayNode('storages')
                    ->beforeNormalization()
                        ->always(static function ($v) {
                            foreach ($v as $key => $value) {
                                if (!isset($value['name'])) {
                                    $v[$key]['name'] = \is_int($key) ? 'default' : $key;
                                }
                            }

                            return $v;
                        })
                    ->end()
                    ->addDefaultChildrenIfNoneSet('default')
                    ->useAttributeAsKey('name')
                    ->arrayPrototype()
                        ->children()
                            ->scalarNode('name')
                                ->cannotBeEmpty()
                                ->info('The name of the storage.')
                            ->end()
                            ->enumNode('type')
                                ->cannotBeEmpty()
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
                                ->cannotBeEmpty()
                                ->defaultValue('%kernel.project_dir%/triggers')
                                ->info('Directory where triggers/functions are stored, depending on the selected type.')
                            ->end()
                        ->end()
                        ->validate()
                            ->always(static function (array $v): array {
                                $type = $v['type'] ?? Storage::PHP_CLASSES->value;

                                if ($type === Storage::SQL_FILES->value) {
                                    unset($v['namespace']);
                                }

                                return $v;
                            })
                        ->end()
                    ->end()
                ->end()
                ->booleanNode('migrations')
                    ->defaultTrue()
                    ->info('Whether to automatically generate migrations for triggers.')
                ->end()
                ->arrayNode('excludes')
                    ->info('The triggers you want to exclude from your mapping.')
                    ->scalarPrototype()->end()
                ->end()
            ->end();

        return $treeBuilder;
    }
}
