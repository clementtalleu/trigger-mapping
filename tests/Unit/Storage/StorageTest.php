<?php

declare(strict_types=1);

namespace Talleu\TriggerMapping\Tests\Unit\Storage;

use PHPUnit\Framework\TestCase;
use Talleu\TriggerMapping\Storage\Storage;

final class StorageTest extends TestCase
{
    public function testEnumValues(): void
    {
        self::assertSame('sql', Storage::SQL_FILES->value);
        self::assertSame('php', Storage::PHP_CLASSES->value);
    }

    public function testTryFromValid(): void
    {
        self::assertSame(Storage::SQL_FILES, Storage::tryFrom('sql'));
        self::assertSame(Storage::PHP_CLASSES, Storage::tryFrom('php'));
    }

    public function testTryFromInvalidReturnsNull(): void
    {
        self::assertNull(Storage::tryFrom('nope'));
        self::assertNull(Storage::tryFrom(''));
        self::assertNull(Storage::tryFrom('PHP'));
    }

    public function testCasesIsExhaustive(): void
    {
        $values = array_map(static fn (Storage $s) => $s->value, Storage::cases());
        sort($values);

        self::assertSame(['php', 'sql'], $values);
    }
}
