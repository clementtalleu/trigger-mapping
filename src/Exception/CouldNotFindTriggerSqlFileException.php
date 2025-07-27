<?php

declare(strict_types=1);

namespace Talleu\TriggerMapping\Exception;

class CouldNotFindTriggerSqlFileException extends \RuntimeException
{
    public function __construct(string $triggerFilePath)
    {
        parent::__construct(sprintf('Could not find .sql file for the path : %s', $triggerFilePath));
    }
}
