<?php

declare(strict_types=1);

namespace Talleu\TriggerMapping\Contract;

interface PostgreSQLTriggerInterface extends MySQLTriggerInterface
{
    public static function getFunction(): string;
}
