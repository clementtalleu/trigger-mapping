<?php

declare(strict_types=1);

namespace Talleu\TriggerMapping\Tests\Unit\Model;

use PHPUnit\Framework\TestCase;
use Talleu\TriggerMapping\Model\ResolvedTrigger;

final class ResolvedTriggerTest extends TestCase
{
    public function testConstructorStoresAllFields(): void
    {
        $trigger = new ResolvedTrigger(
            name: 'trg_x',
            table: 'tbl_x',
            events: ['INSERT'],
            when: 'AFTER',
            scope: 'ROW',
            storage: 'php',
            function: 'fn_x',
            definition: 'CREATE TRIGGER ...',
            content: 'BEGIN ... END;',
            onTable: 'tbl_x_join',
            className: 'App\\Triggers\\X',
        );

        self::assertSame('trg_x', $trigger->name);
        self::assertSame('tbl_x', $trigger->table);
        self::assertSame(['INSERT'], $trigger->events);
        self::assertSame('AFTER', $trigger->when);
        self::assertSame('ROW', $trigger->scope);
        self::assertSame('php', $trigger->storage);
        self::assertSame('fn_x', $trigger->function);
        self::assertSame('CREATE TRIGGER ...', $trigger->definition);
        self::assertSame('BEGIN ... END;', $trigger->content);
        self::assertSame('tbl_x_join', $trigger->onTable);
        self::assertSame('App\\Triggers\\X', $trigger->className);
    }

    public function testCreateFactoryRenamesFunctionNameToFunction(): void
    {
        $trigger = ResolvedTrigger::create(
            name: 'trg_x',
            table: 'tbl_x',
            events: ['UPDATE'],
            when: 'BEFORE',
            scope: 'STATEMENT',
            storage: 'sql',
            functionName: 'fn_renamed',
        );

        self::assertSame('fn_renamed', $trigger->function);
    }

    public function testCreateFactoryDefaults(): void
    {
        $trigger = ResolvedTrigger::create(
            name: 'trg_x',
            table: 'tbl_x',
            events: ['DELETE'],
            when: 'AFTER',
            scope: 'ROW',
            storage: 'php',
        );

        self::assertNull($trigger->function);
        self::assertNull($trigger->definition);
        self::assertNull($trigger->content);
        self::assertNull($trigger->onTable);
        self::assertNull($trigger->className);
    }

    public function testPropertiesAreEqualToConstructorWhenUsingFactory(): void
    {
        $a = ResolvedTrigger::create(
            name: 'trg',
            table: 't',
            events: ['INSERT', 'UPDATE'],
            when: 'AFTER',
            scope: 'ROW',
            storage: 'php',
            functionName: 'fn',
            definition: 'def',
            content: 'cnt',
            onTable: 'jt',
            className: 'Cls',
        );

        $b = new ResolvedTrigger(
            name: 'trg',
            table: 't',
            events: ['INSERT', 'UPDATE'],
            when: 'AFTER',
            scope: 'ROW',
            storage: 'php',
            function: 'fn',
            definition: 'def',
            content: 'cnt',
            onTable: 'jt',
            className: 'Cls',
        );

        self::assertEquals($a, $b);
    }
}
