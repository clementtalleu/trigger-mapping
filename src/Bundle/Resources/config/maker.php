<?php

declare(strict_types=1);

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Talleu\TriggerMapping\Command\TriggersMappingUpdateCommand;
use Talleu\TriggerMapping\Command\TriggersSchemaDiffCommand;
use Talleu\TriggerMapping\Factory\MappingCreator;
use Talleu\TriggerMapping\Factory\TriggerCreator;
use Talleu\TriggerMapping\Symfony\Maker\MakeTrigger;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    $services->set('trigger_mapping.command.schema_diff', TriggersSchemaDiffCommand::class)
        ->args([
            service('trigger_mapping.metadata.triggers_mapping'),
            service('trigger_mapping.database.triggers_db_extractor'),
            service('trigger_mapping.factory.trigger_creator'),
            service('maker.generator'),
        ])
        ->tag('console.command');

    $services->set('trigger_mapping.command.mapping_update', TriggersMappingUpdateCommand::class)
        ->args([
            service('trigger_mapping.metadata.triggers_mapping'),
            service('trigger_mapping.database.triggers_db_extractor'),
            service('trigger_mapping.storage_resolver'),
            service('trigger_mapping.factory.mapping_creator'),
            service('trigger_mapping.factory.trigger_creator'),
            service('maker.generator'),
            service('trigger_mapping.utils.entity_finder'),
        ])
        ->tag('console.command');

    $services->set('trigger_mapping.factory.trigger_creator', TriggerCreator::class)
        ->args([
            service('maker.generator'),
            service('trigger_mapping.storage_resolver'),
            service('trigger_mapping.platform_resolver'),
            service('doctrine.migrations.dependency_factory'),
            '%trigger_mapping.migrations%',
        ]);

    $services->set('trigger_mapping.factory.mapping_creator', MappingCreator::class)
        ->args([
            service('maker.file_manager'),
        ]);

    $services->set('trigger_mapping.maker.command.trigger', MakeTrigger::class)
        ->args([
            service('maker.doctrine_helper'),
            service('trigger_mapping.platform_resolver'),
            service('trigger_mapping.factory.trigger_creator'),
            service('trigger_mapping.factory.mapping_creator'),
            '%trigger_mapping.migrations%',
        ])
        ->tag('maker.command');
};


