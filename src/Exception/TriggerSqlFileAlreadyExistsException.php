<?php

declare(strict_types=1);

namespace Talleu\TriggerMapping\Exception;

class TriggerSqlFileAlreadyExistsException extends \RuntimeException
{
    public function __construct(string $fileName)
    {
        parent::__construct(sprintf('A SQL file for "%s" already exists, maybe you forget to execute the SQL query or run the dedicated migration ?', $fileName));
    }
}
