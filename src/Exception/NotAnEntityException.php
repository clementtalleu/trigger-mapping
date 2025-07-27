<?php

declare(strict_types=1);

namespace Talleu\TriggerMapping\Exception;

class NotAnEntityException extends \RuntimeException
{
    public function __construct(string $entityName)
    {
        parent::__construct(sprintf('%s is not a valid doctrine entity', $entityName));
    }
}
