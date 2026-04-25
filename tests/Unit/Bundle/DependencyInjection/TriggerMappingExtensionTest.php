<?php

declare(strict_types=1);

namespace Talleu\TriggerMapping\Tests\Unit\Bundle\DependencyInjection;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Talleu\TriggerMapping\Bundle\DependencyInjection\TriggerMappingExtension;

final class TriggerMappingExtensionTest extends TestCase
{
    public function testGetAlias(): void
    {
        self::assertSame('trigger_mapping', (new TriggerMappingExtension())->getAlias());
    }

    public function testDefaultParametersAreSet(): void
    {
        $container = $this->loadExtensionWith([]);

        self::assertSame('php', $container->getParameter('trigger_mapping.storage.type'));
        self::assertSame('%kernel.project_dir%/triggers', $container->getParameter('trigger_mapping.storage.directory'));
        self::assertSame('App\\Triggers', $container->getParameter('trigger_mapping.storage.namespace'));
        self::assertTrue($container->getParameter('trigger_mapping.migrations'));
        self::assertSame([], $container->getParameter('trigger_mapping.exclude'));
    }

    public function testCustomConfigurationIsApplied(): void
    {
        $container = $this->loadExtensionWith([
            'storage' => [
                'type' => 'sql',
                'namespace' => 'Acme\\Triggers',
                'directory' => '/var/triggers',
            ],
            'migrations' => false,
            'excludes' => ['legacy_trigger'],
        ]);

        self::assertSame('sql', $container->getParameter('trigger_mapping.storage.type'));
        self::assertSame('/var/triggers', $container->getParameter('trigger_mapping.storage.directory'));
        self::assertSame('Acme\\Triggers', $container->getParameter('trigger_mapping.storage.namespace'));
        self::assertFalse($container->getParameter('trigger_mapping.migrations'));
        self::assertSame(['legacy_trigger'], $container->getParameter('trigger_mapping.exclude'));
    }

    public function testInvalidStorageTypeRaises(): void
    {
        // Configuration tree validation rejects unknown enum values BEFORE Extension::load() runs.
        $this->expectException(InvalidConfigurationException::class);

        $this->loadExtensionWith(['storage' => ['type' => 'yaml']]);
    }

    public function testCoreServicesAreRegistered(): void
    {
        $container = $this->loadExtensionWith([]);

        self::assertTrue($container->hasDefinition('trigger_mapping.command.validate'));
        self::assertTrue($container->hasDefinition('trigger_mapping.command.schema_update'));
        self::assertTrue($container->hasDefinition('trigger_mapping.metadata.triggers_mapping'));
        self::assertTrue($container->hasDefinition('trigger_mapping.database.triggers_db_extractor'));
        self::assertTrue($container->hasDefinition('trigger_mapping.factory.trigger_definition_factory'));
        self::assertTrue($container->hasDefinition('trigger_mapping.platform_resolver'));
        self::assertTrue($container->hasDefinition('trigger_mapping.storage_resolver'));
        self::assertTrue($container->hasDefinition('trigger_mapping.utils.entity_finder'));
    }

    /**
     * @param array<string, mixed> $config
     */
    private function loadExtensionWith(array $config): ContainerBuilder
    {
        $container = new ContainerBuilder();
        (new TriggerMappingExtension())->load([$config], $container);

        return $container;
    }
}
