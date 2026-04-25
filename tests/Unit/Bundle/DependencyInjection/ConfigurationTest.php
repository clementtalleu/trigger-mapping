<?php

declare(strict_types=1);

namespace Talleu\TriggerMapping\Tests\Unit\Bundle\DependencyInjection;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Processor;
use Talleu\TriggerMapping\Bundle\DependencyInjection\Configuration;

final class ConfigurationTest extends TestCase
{
    public function testEmptyConfigurationProducesDefaults(): void
    {
        $config = $this->process([[]]);

        self::assertSame('php', $config['storage']['type']);
        self::assertSame('App\\Triggers', $config['storage']['namespace']);
        self::assertSame('%kernel.project_dir%/triggers', $config['storage']['directory']);
        self::assertTrue($config['migrations']);
        self::assertSame([], $config['excludes']);
    }

    public function testStorageTypeSqlIsAccepted(): void
    {
        $config = $this->process([['storage' => ['type' => 'sql']]]);

        self::assertSame('sql', $config['storage']['type']);
    }

    public function testStorageTypeInvalidIsRejected(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessageMatches('/storage\\.type/');

        $this->process([['storage' => ['type' => 'yaml']]]);
    }

    public function testCustomNamespaceAndDirectory(): void
    {
        $config = $this->process([[
            'storage' => [
                'namespace' => 'Acme\\MyApp\\Triggers',
                'directory' => '/var/app/triggers',
            ],
        ]]);

        self::assertSame('Acme\\MyApp\\Triggers', $config['storage']['namespace']);
        self::assertSame('/var/app/triggers', $config['storage']['directory']);
    }

    public function testEmptyNamespaceIsRejected(): void
    {
        $this->expectException(InvalidConfigurationException::class);

        $this->process([['storage' => ['namespace' => '']]]);
    }

    public function testMigrationsCanBeDisabled(): void
    {
        $config = $this->process([['migrations' => false]]);

        self::assertFalse($config['migrations']);
    }

    public function testExcludesIsAnArrayOfStrings(): void
    {
        $config = $this->process([['excludes' => ['foo_trigger', 'bar_trigger']]]);

        self::assertSame(['foo_trigger', 'bar_trigger'], $config['excludes']);
    }

    /**
     * @param array<int, array<string, mixed>> $configs
     * @return array<string, mixed>
     */
    private function process(array $configs): array
    {
        return (new Processor())->processConfiguration(new Configuration(), $configs);
    }
}
