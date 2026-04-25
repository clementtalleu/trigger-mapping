<?php

declare(strict_types=1);

namespace Talleu\TriggerMapping\Tests\Unit\Attribute;

use Attribute;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Talleu\TriggerMapping\Attribute\Trigger;

final class TriggerTest extends TestCase
{
    public function testDefaultsAreApplied(): void
    {
        $trigger = new Trigger(name: 'trg_foo');

        self::assertSame('trg_foo', $trigger->name);
        self::assertNull($trigger->function);
        self::assertSame(['insert'], $trigger->on);
        self::assertSame('AFTER', $trigger->when);
        self::assertSame('ROW', $trigger->scope);
        self::assertNull($trigger->storage);
        self::assertNull($trigger->className);
        self::assertNull($trigger->onTable);
    }

    public function testAllParametersAreStored(): void
    {
        $trigger = new Trigger(
            name: 'trg_audit_user',
            function: 'fn_audit',
            on: ['INSERT', 'UPDATE'],
            when: 'BEFORE',
            scope: 'STATEMENT',
            storage: 'php',
            className: 'App\\Triggers\\AuditUser',
            onTable: 'user_role',
        );

        self::assertSame('trg_audit_user', $trigger->name);
        self::assertSame('fn_audit', $trigger->function);
        self::assertSame(['INSERT', 'UPDATE'], $trigger->on);
        self::assertSame('BEFORE', $trigger->when);
        self::assertSame('STATEMENT', $trigger->scope);
        self::assertSame('php', $trigger->storage);
        self::assertSame('App\\Triggers\\AuditUser', $trigger->className);
        self::assertSame('user_role', $trigger->onTable);
    }

    public function testIsTargetClassAndRepeatable(): void
    {
        $reflection = new ReflectionClass(Trigger::class);
        $attributes = $reflection->getAttributes(Attribute::class);

        self::assertCount(1, $attributes, 'Trigger must declare a #[Attribute(...)] meta-attribute');

        /** @var Attribute $attribute */
        $attribute = $attributes[0]->newInstance();
        $flags = $attribute->flags;

        self::assertSame(Attribute::TARGET_CLASS, $flags & Attribute::TARGET_CLASS, 'Trigger must target classes');
        self::assertSame(Attribute::IS_REPEATABLE, $flags & Attribute::IS_REPEATABLE, 'Trigger must be repeatable');
        self::assertSame(0, $flags & Attribute::TARGET_METHOD, 'Trigger must not target methods');
        self::assertSame(0, $flags & Attribute::TARGET_PROPERTY, 'Trigger must not target properties');
    }

    public function testRepeatableInPracticeOnEntity(): void
    {
        $entity = new class {
        };
        // Simulated stacking via reflection: we just confirm we can build several instances
        $a = new Trigger(name: 'trg_a');
        $b = new Trigger(name: 'trg_b');

        self::assertNotSame($a, $b);
        self::assertSame('trg_a', $a->name);
        self::assertSame('trg_b', $b->name);
        // class_exists on the anonymous class to silence unused-variable lint
        self::assertNotEmpty($entity::class);
    }

    // -------------------------------------------------------------------------
    // Validation (audit Phase 1.1 — closes RCE / SQLi / path traversal at boundary)
    // -------------------------------------------------------------------------

    /**
     * @dataProvider invalidIdentifierProvider
     */
    public function testInvalidNameIsRejected(string $invalid): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/Trigger name/');

        new Trigger(name: $invalid);
    }

    /**
     * @dataProvider invalidIdentifierProvider
     */
    public function testInvalidFunctionIsRejected(string $invalid): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/Trigger function/');

        new Trigger(name: 'trg_ok', function: $invalid);
    }

    /**
     * @dataProvider invalidIdentifierProvider
     */
    public function testInvalidOnTableIsRejected(string $invalid): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/Trigger onTable/');

        new Trigger(name: 'trg_ok', onTable: $invalid);
    }

    public function testInvalidWhenIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/Trigger when/');

        new Trigger(name: 'trg_ok', when: 'WHENEVER');
    }

    public function testInvalidScopeIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/Trigger scope/');

        new Trigger(name: 'trg_ok', scope: 'GLOBAL');
    }

    public function testInvalidEventIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/Trigger on\[0]/');

        new Trigger(name: 'trg_ok', on: ['SELECT']);
    }

    public function testValidValuesAreAccepted(): void
    {
        // sanity: round-trip every allowed enum value
        foreach (Trigger::ALLOWED_TIMINGS as $when) {
            foreach (Trigger::ALLOWED_SCOPES as $scope) {
                foreach (Trigger::ALLOWED_EVENTS as $event) {
                    $t = new Trigger(name: 'trg_ok', on: [$event], when: $when, scope: $scope);
                    self::assertSame($when, $t->when);
                }
            }
        }
    }

    public function testCaseInsensitiveTimingAndScope(): void
    {
        // Lowercase / mixed-case enum values are accepted because we normalize
        // for comparison; the original casing is preserved on the property.
        $t = new Trigger(name: 'trg_ok', when: 'before', scope: 'statement', on: ['insert']);
        self::assertSame('before', $t->when);
    }

    /**
     * @return iterable<string, array{0: string}>
     */
    public static function invalidIdentifierProvider(): iterable
    {
        yield 'sql injection in DROP'          => ['foo`; DROP TABLE users; --'];
        yield 'path traversal'                 => ['../../../etc/cron.d/payload'];
        yield 'PHP heredoc breakout'           => ["foo\nSQL;\nsystem('id');\n//"];
        yield 'space in middle'                => ['foo bar'];
        yield 'starts with digit'              => ['1foo'];
        yield 'empty string'                   => [''];
        yield 'too long (> 63 chars)'          => [str_repeat('a', 64)];
        yield 'contains special char (semi)'   => ['foo;bar'];
        yield 'contains quote'                 => ["foo'bar"];
    }
}
