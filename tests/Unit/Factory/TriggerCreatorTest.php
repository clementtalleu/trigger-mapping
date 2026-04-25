<?php

declare(strict_types=1);

namespace Talleu\TriggerMapping\Tests\Unit\Factory;

use Doctrine\Migrations\DependencyFactory;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\MakerBundle\Generator;
use Symfony\Bundle\MakerBundle\Util\ClassNameDetails;
use Talleu\TriggerMapping\Factory\TriggerCreator;
use Talleu\TriggerMapping\Model\ResolvedTrigger;
use Talleu\TriggerMapping\Platform\DatabasePlatformResolverInterface;
use Talleu\TriggerMapping\Storage\StorageResolverInterface;

final class TriggerCreatorTest extends TestCase
{
    // ----------------------------------------------------------------------
    // PHP class generation
    // ----------------------------------------------------------------------

    public function testGeneratesPostgreSqlPhpClass(): void
    {
        $generator = $this->createMock(Generator::class);
        $generator
            ->expects($this->once())
            ->method('createClassNameDetails')
            ->with('TrgFoo', 'Triggers')
            ->willReturn($this->classNameDetails('App\\Triggers\\TrgFoo'));

        $generator
            ->expects($this->once())
            ->method('generateClass')
            ->willReturnCallback(function (string $fqcn, string $template, array $params): string {
                self::assertSame('App\\Triggers\\TrgFoo', $fqcn);
                self::assertStringContainsString('PostgresqlTrigger.tpl.php', $template);
                self::assertSame('INSERT OR UPDATE', $params['events']);
                // RETURN value matrix: AFTER => NULL (current behaviour, will be revisited in Phase 2.3)
                self::assertSame('NULL', $params['return_value']);

                return 'app/Triggers/TrgFoo.php';
            });

        $creator = $this->creatorWithPlatform('postgresql', $generator, migrations: false);

        $resolved = $this->resolved(name: 'trg_foo', events: ['INSERT', 'UPDATE'], when: 'AFTER', storage: 'php');

        $details = $creator->create([$resolved]);
        self::assertCount(1, $details);
    }

    public function testGeneratesMySqlPhpClass(): void
    {
        $generator = $this->createMock(Generator::class);
        $generator
            ->expects($this->once())
            ->method('createClassNameDetails')
            ->willReturn($this->classNameDetails('App\\Triggers\\TrgBar'));

        $generator
            ->expects($this->once())
            ->method('generateClass')
            ->willReturnCallback(function (string $fqcn, string $template, array $params): string {
                self::assertStringContainsString('MysqlTrigger.tpl.php', $template);
                self::assertSame('INSERT', $params['events']);

                return 'x';
            });

        $creator = $this->creatorWithPlatform('mysql', $generator, migrations: false);
        $creator->create([$this->resolved(name: 'trg_bar', events: ['INSERT'], when: 'AFTER', storage: 'php')]);
    }

    public function testGeneratesSqlServerPhpClass(): void
    {
        $generator = $this->createMock(Generator::class);
        $generator
            ->method('createClassNameDetails')
            ->willReturn($this->classNameDetails('App\\Triggers\\TrgBaz'));

        $generator
            ->expects($this->once())
            ->method('generateClass')
            ->willReturnCallback(function (string $fqcn, string $template, array $params): string {
                self::assertStringContainsString('SqlServerTrigger.tpl.php', $template);

                return 'x';
            });

        $creator = $this->creatorWithPlatform('sqlsrv', $generator, migrations: false);
        $creator->create([$this->resolved(name: 'trg_baz', events: ['INSERT'], when: 'AFTER', storage: 'php')]);
    }

    public function testMySqlPhpClassRejectsMultipleEvents(): void
    {
        $generator = $this->createMock(Generator::class);
        $generator->method('createClassNameDetails')->willReturn($this->classNameDetails('App\\Triggers\\T'));

        $creator = $this->creatorWithPlatform('mysql', $generator, migrations: false);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/MySQL does not support multiple events/');

        $creator->create([$this->resolved(name: 't', events: ['INSERT', 'UPDATE'], when: 'AFTER', storage: 'php')]);
    }

    /**
     * @dataProvider postgresReturnValueProvider
     *
     * @param string[] $events
     */
    public function testPostgresReturnValueMatrix(string $when, array $events, string $scope, string $expected): void
    {
        $generator = $this->createMock(Generator::class);
        $generator->method('createClassNameDetails')->willReturn($this->classNameDetails('App\\Triggers\\T'));

        $captured = null;
        $generator
            ->expects($this->once())
            ->method('generateClass')
            ->willReturnCallback(function (string $fqcn, string $template, array $params) use (&$captured): string {
                $captured = $params['return_value'];

                return 'x';
            });

        $creator = $this->creatorWithPlatform('postgresql', $generator, migrations: false);
        $creator->create([ResolvedTrigger::create(
            name: 't',
            table: 't',
            events: $events,
            when: $when,
            scope: $scope,
            storage: 'php',
            functionName: 'fn',
        )]);

        self::assertSame($expected, $captured);
    }

    /**
     * @return iterable<string, array{0: string, 1: string[], 2: string, 3: string}>
     */
    public static function postgresReturnValueProvider(): iterable
    {
        yield 'AFTER any → NULL'                => ['AFTER',  ['INSERT'], 'ROW',       'NULL'];
        yield 'STATEMENT scope → NULL'          => ['BEFORE', ['INSERT'], 'STATEMENT', 'NULL'];
        yield 'BEFORE INSERT → NEW'             => ['BEFORE', ['INSERT'], 'ROW',       'NEW'];
        yield 'BEFORE UPDATE → NEW'             => ['BEFORE', ['UPDATE'], 'ROW',       'NEW'];
        yield 'BEFORE DELETE → OLD (was a bug)' => ['BEFORE', ['DELETE'], 'ROW',       'OLD'];
        yield 'BEFORE INSERT,UPDATE → NEW'      => ['BEFORE', ['INSERT', 'UPDATE'], 'ROW', 'NEW'];
    }

    public function testSqlServerPhpClassRejectsBefore(): void
    {
        $generator = $this->createMock(Generator::class);
        $generator->method('createClassNameDetails')->willReturn($this->classNameDetails('App\\Triggers\\T'));

        $creator = $this->creatorWithPlatform('sqlsrv', $generator, migrations: false);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/SQL Server does not support before/');

        $creator->create([$this->resolved(name: 't', events: ['INSERT'], when: 'BEFORE', storage: 'php')]);
    }

    // ----------------------------------------------------------------------
    // SQL file generation
    // ----------------------------------------------------------------------

    public function testGeneratesPostgreSqlSqlFiles(): void
    {
        $generator = $this->createMock(Generator::class);
        $calls = [];
        $generator
            ->expects($this->exactly(2))
            ->method('generateFile')
            ->willReturnCallback(function (string $path, string $template, array $params) use (&$calls): string {
                $calls[] = ['path' => $path, 'template' => $template];
                return $path;
            });

        $creator = $this->creatorWithPlatform('postgresql', $generator, migrations: false);
        $creator->create([
            $this->resolved(name: 'trg_pg', events: ['INSERT'], when: 'AFTER', storage: 'sql', function: 'fn_pg'),
        ]);

        // PG generates BOTH the function file and the trigger file.
        self::assertCount(2, $calls);
        self::assertStringContainsString('functions/fn_pg.sql', $calls[0]['path']);
        self::assertStringContainsString('postgresql_function.tpl.php', $calls[0]['template']);
        self::assertStringContainsString('triggers/trg_pg.sql', $calls[1]['path']);
        self::assertStringContainsString('postgresql_trigger.tpl.php', $calls[1]['template']);
    }

    public function testGeneratesMySqlSqlFile(): void
    {
        $generator = $this->createMock(Generator::class);
        $generator
            ->expects($this->once())
            ->method('generateFile')
            ->willReturnCallback(function (string $path, string $template) {
                self::assertStringEndsWith('/trg_my.sql', $path);
                self::assertStringContainsString('mysql_trigger.tpl.php', $template);
                return $path;
            });

        $creator = $this->creatorWithPlatform('mysql', $generator, migrations: false);
        $creator->create([$this->resolved(name: 'trg_my', events: ['INSERT'], when: 'AFTER', storage: 'sql')]);
    }

    public function testGeneratesSqlServerSqlFile(): void
    {
        $generator = $this->createMock(Generator::class);
        $generator
            ->expects($this->once())
            ->method('generateFile')
            ->willReturnCallback(function (string $path, string $template) {
                self::assertStringEndsWith('/trg_ss.sql', $path);
                self::assertStringContainsString('sqlserver_trigger.tpl.php', $template);
                return $path;
            });

        $creator = $this->creatorWithPlatform('sqlsrv', $generator, migrations: false);
        $creator->create([$this->resolved(name: 'trg_ss', events: ['INSERT'], when: 'AFTER', storage: 'sql')]);
    }

    public function testMySqlSqlFileRejectsMultipleEvents(): void
    {
        $creator = $this->creatorWithPlatform('mysql', $this->createMock(Generator::class), migrations: false);

        $this->expectException(InvalidArgumentException::class);

        $creator->create([$this->resolved(name: 't', events: ['INSERT', 'UPDATE'], when: 'AFTER', storage: 'sql')]);
    }

    public function testSqlServerSqlFileRejectsBefore(): void
    {
        $creator = $this->creatorWithPlatform('sqlsrv', $this->createMock(Generator::class), migrations: false);

        $this->expectException(InvalidArgumentException::class);

        $creator->create([$this->resolved(name: 't', events: ['INSERT'], when: 'BEFORE', storage: 'sql')]);
    }

    // ----------------------------------------------------------------------
    // Migrations branch
    // ----------------------------------------------------------------------

    public function testMigrationsAreSkippedWhenDisabled(): void
    {
        $generator = $this->createMock(Generator::class);
        $generator->method('generateFile')->willReturn('x');

        $depFactory = $this->createMock(DependencyFactory::class);
        // If migrations are disabled we should NEVER touch the migrations subsystem.
        $depFactory->expects($this->never())->method('getMigrationGenerator');

        $creator = new TriggerCreator(
            generator: $generator,
            storageResolver: $this->resolverWithDir('/tmp/triggers', 'App\\Triggers'),
            databasePlatformResolver: $this->platformResolver('mysql'),
            dependencyFactory: $depFactory,
            migrations: false,
        );

        $creator->create([$this->resolved(name: 't', events: ['INSERT'], when: 'AFTER', storage: 'sql')]);
    }

    /**
     * Returns the empty array when no PHP-class trigger was created.
     */
    public function testCreateReturnsEmptyArrayWhenOnlySqlTriggers(): void
    {
        $generator = $this->createMock(Generator::class);
        $generator->method('generateFile')->willReturn('x');

        $creator = $this->creatorWithPlatform('mysql', $generator, migrations: false);
        $details = $creator->create([
            $this->resolved(name: 't1', events: ['INSERT'], when: 'AFTER', storage: 'sql'),
            $this->resolved(name: 't2', events: ['UPDATE'], when: 'AFTER', storage: 'sql'),
        ]);

        self::assertSame([], $details);
    }

    // ----------------------------------------------------------------------
    // Helpers
    // ----------------------------------------------------------------------

    private function creatorWithPlatform(string $platform, Generator $generator, bool $migrations): TriggerCreator
    {
        return new TriggerCreator(
            generator: $generator,
            storageResolver: $this->resolverWithDir('/tmp/triggers', 'App\\Triggers'),
            databasePlatformResolver: $this->platformResolver($platform),
            dependencyFactory: $this->createMock(DependencyFactory::class),
            migrations: $migrations,
        );
    }

    private function platformResolver(string $platform): DatabasePlatformResolverInterface
    {
        $resolver = $this->createMock(DatabasePlatformResolverInterface::class);
        $resolver->method('isPostgreSQL')->willReturn($platform === 'postgresql');
        $resolver->method('isMySQL')->willReturn($platform === 'mysql');
        $resolver->method('isSQLServer')->willReturn($platform === 'sqlsrv');
        $resolver->method('getPlatformName')->willReturn($platform);

        return $resolver;
    }

    private function resolverWithDir(string $dir, string $namespace): StorageResolverInterface
    {
        $resolver = $this->createMock(StorageResolverInterface::class);
        $resolver->method('getResolvedDirectory')->willReturn($dir);
        $resolver->method('getResolvedNamespace')->willReturn($namespace);

        return $resolver;
    }

    private function classNameDetails(string $fullName): ClassNameDetails
    {
        // ClassNameDetails is `final` and cannot be doubled — use a real instance.
        $namespacePrefix = substr($fullName, 0, strrpos($fullName, '\\') ?: 0);

        return new ClassNameDetails($fullName, $namespacePrefix);
    }

    /**
     * @param string[] $events
     */
    private function resolved(
        string $name,
        array $events,
        string $when,
        string $storage,
        ?string $function = null,
    ): ResolvedTrigger {
        return ResolvedTrigger::create(
            name: $name,
            table: 't',
            events: $events,
            when: $when,
            scope: 'ROW',
            storage: $storage,
            functionName: $function,
        );
    }
}
