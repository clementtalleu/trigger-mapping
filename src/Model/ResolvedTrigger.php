<?php

declare(strict_types=1);

namespace Talleu\TriggerMapping\Model;

final class ResolvedTrigger
{
    /**
     * @param string[] $events
     */
    public function __construct(
        public string  $name,
        public string  $table,
        public array   $events,
        public string  $when,
        public string  $scope,
        public string $storage,
        public ?string $function = null,
        public ?string $definition = null,
        public ?string $content = null,
        public ?string $onTable = null,
        public ?string $className = null,
    ) {
    }

    /**
     * @param string[] $events
     */
    public static function create(
        string  $name,
        string  $table,
        array   $events,
        string  $when,
        string  $scope,
        string $storage,
        ?string $functionName = null,
        ?string $definition = null,
        ?string $content = null,
        ?string $onTable = null,
        ?string $className = null,
    ): self {
        return new self(
            name: $name,
            table: $table,
            events: $events,
            when: $when,
            scope: $scope,
            storage: $storage,
            function: $functionName,
            definition: $definition,
            content: $content,
            onTable: $onTable,
            className: $className
        );
    }
}
