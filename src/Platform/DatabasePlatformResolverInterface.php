<?php

declare(strict_types=1);

namespace Talleu\TriggerMapping\Platform;

interface DatabasePlatformResolverInterface
{
    public function isMySQL(): bool;

    public function isPostgreSQL(): bool;

    public function getPlatformName(): string;
}
