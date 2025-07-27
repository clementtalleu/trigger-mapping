<?php

declare(strict_types=1);

namespace Talleu\TriggerMapping\Exception;

class NotAnValidTriggerClassException extends \RuntimeException
{
    public function __construct(string $className)
    {
        parent::__construct(sprintf('%s is not a valid trigger class, should be instance of MySQLTriggerInterface or PostgreSQLTriggerInterface', $className));
    }
}
