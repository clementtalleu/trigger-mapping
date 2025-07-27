<?php

declare(strict_types=1);

namespace Talleu\TriggerMapping\Contract;

interface MySQLTriggerInterface
{
    public static function getTrigger(): string;
}
