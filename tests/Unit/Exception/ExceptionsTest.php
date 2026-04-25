<?php

declare(strict_types=1);

namespace Talleu\TriggerMapping\Tests\Unit\Exception;

use PHPUnit\Framework\TestCase;
use Talleu\TriggerMapping\Exception\CouldNotFindTriggerSqlFileException;
use Talleu\TriggerMapping\Exception\NotAnEntityException;
use Talleu\TriggerMapping\Exception\NotAnValidTriggerClassException;
use Talleu\TriggerMapping\Exception\TriggerClassAlreadyExistsException;
use Talleu\TriggerMapping\Exception\TriggerSqlFileAlreadyExistsException;

final class ExceptionsTest extends TestCase
{
    public function testNotAnEntityExceptionMessage(): void
    {
        $e = new NotAnEntityException('App\\NotAnEntity');

        self::assertInstanceOf(\RuntimeException::class, $e);
        self::assertSame('App\\NotAnEntity is not a valid doctrine entity', $e->getMessage());
    }

    public function testNotAnValidTriggerClassExceptionMessage(): void
    {
        $e = new NotAnValidTriggerClassException('App\\Triggers\\Plain');

        self::assertInstanceOf(\RuntimeException::class, $e);
        self::assertStringContainsString('App\\Triggers\\Plain', $e->getMessage());
        self::assertStringContainsString('MySQLTriggerInterface', $e->getMessage());
        self::assertStringContainsString('PostgreSQLTriggerInterface', $e->getMessage());
    }

    public function testCouldNotFindTriggerSqlFileExceptionMessage(): void
    {
        $e = new CouldNotFindTriggerSqlFileException('/var/triggers/foo.sql');

        self::assertInstanceOf(\RuntimeException::class, $e);
        self::assertStringContainsString('/var/triggers/foo.sql', $e->getMessage());
        self::assertStringContainsString('Could not find', $e->getMessage());
    }

    public function testTriggerClassAlreadyExistsExceptionMessage(): void
    {
        $e = new TriggerClassAlreadyExistsException('App\\Triggers\\Existing');

        self::assertInstanceOf(\RuntimeException::class, $e);
        self::assertStringContainsString('App\\Triggers\\Existing', $e->getMessage());
        self::assertStringContainsString('already exists', $e->getMessage());
    }

    public function testTriggerSqlFileAlreadyExistsExceptionMessage(): void
    {
        $e = new TriggerSqlFileAlreadyExistsException('/var/triggers/foo.sql');

        self::assertInstanceOf(\RuntimeException::class, $e);
        self::assertStringContainsString('/var/triggers/foo.sql', $e->getMessage());
        self::assertStringContainsString('already exists', $e->getMessage());
    }
}
