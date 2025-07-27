<?php

declare(strict_types=1);

namespace Talleu\TriggerMapping\Storage;

enum Storage: string
{
    case SQL_FILES = 'sql';
    case PHP_CLASSES = 'php';
}
