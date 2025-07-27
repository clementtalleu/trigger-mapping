<?php

declare(strict_types=1);

namespace Talleu\TriggerMapping\Exception;

class TriggerClassAlreadyExistsException extends \RuntimeException
{
    public function __construct(string $className)
    {
        parent::__construct(sprintf('A trigger class for "%s" already exists, maybe you forget to execute the SQL query or run the dedicated migration ?', $className));
    }
}
