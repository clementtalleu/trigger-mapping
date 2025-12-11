<?php

declare(strict_types=1);

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Talleu\TriggerMapping\Command\TriggersSchemaUpdateCommand;
use Talleu\TriggerMapping\Command\TriggersSchemaValidateCommand;
use Talleu\TriggerMapping\DatabaseSchema\TriggersDbExtractor;
use Talleu\TriggerMapping\Factory\TriggerDefinitionFactory;
use Talleu\TriggerMapping\Metadata\TriggersMapping;
use Talleu\TriggerMapping\Platform\DatabasePlatformResolver;
use Talleu\TriggerMapping\Storage\StorageResolver;
use Talleu\TriggerMapping\Utils\EntityFinder;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    $services->set('trigger_mapping.command.validate', TriggersSchemaValidateCommand::class)
        ->args([
            service('trigger_mapping.metadata.triggers_mapping'),
            service('trigger_mapping.database.triggers_db_extractor'),
            service('trigger_mapping.utils.entity_finder'),
        ])
        ->tag('console.command');

    $services->set('trigger_mapping.metadata.triggers_mapping', TriggersMapping::class)
        ->args([
            service('doctrine.migrations.dependency_factory'),
            service('trigger_mapping.factory.trigger_definition_factory'),
            service('trigger_mapping.factory.trigger_definition_factory'),
        ]);

    $services->set('trigger_mapping.database.triggers_db_extractor', TriggersDbExtractor::class)
        ->args([
            service('doctrine.migrations.dependency_factory'),
            service('trigger_mapping.platform_resolver'),
            '%trigger_mapping.exclude%',
        ]);

    $services->set('trigger_mapping.factory.trigger_definition_factory', TriggerDefinitionFactory::class)
        ->autowire()
        ->autoconfigure()
        ->args([
            service('trigger_mapping.storage_resolver'),
        ]);

    $services->set('trigger_mapping.platform_resolver', DatabasePlatformResolver::class)
        ->args([
            service('doctrine.dbal.default_connection'),
        ]);

    $services->set('trigger_mapping.storage_resolver', StorageResolver::class)
        ->args([
            '%trigger_mapping.storage.type%',
            '%trigger_mapping.storage.directory%',
            '%trigger_mapping.storage.namespace%',
        ]);

    $services->set('trigger_mapping.command.schema_update', TriggersSchemaUpdateCommand::class)
        ->args([
            service('trigger_mapping.metadata.triggers_mapping'),
            service('trigger_mapping.storage_resolver'),
            service('trigger_mapping.platform_resolver'),
            service('doctrine.dbal.default_connection'),
        ])
        ->tag('console.command');

    $services->set('trigger_mapping.utils.entity_finder', EntityFinder::class)
        ->args([
            service('maker.doctrine_helper'),
        ]);
};


