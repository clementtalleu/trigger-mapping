<?php

declare(strict_types=1);

namespace Talleu\TriggerMapping\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
class Trigger
{
    /**
     * @param string[] $on
     */
    public function __construct(
        public string $name,
        public ?string $function = null,
        public array $on = ['insert'],
        public string $when = 'AFTER',
        public string $scope = 'ROW',
        public ?string $storage = null,
        public ?string $className = null,
        public ?string $onTable = null,
    ) {
    }
}
