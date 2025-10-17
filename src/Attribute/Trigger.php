<?php

declare(strict_types=1);

namespace Talleu\TriggerMapping\Attribute;

use Attribute;
use Talleu\TriggerMapping\Storage\Storage;

#[Attribute(Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
class Trigger
{
    /**
     * @param string[]           $on
     * @param ?value-of<Storage> $storage
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
        $validStorages = array_column(Storage::cases(), 'value');
        if (!\in_array($this->storage, $validStorages, true)) {
            throw new \InvalidArgumentException(
                'Invalid storage "' . $this->storage . '", should be one of: "' . implode(', ', $validStorages)
            );
        }
    }
}
