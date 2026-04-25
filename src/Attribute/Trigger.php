<?php

declare(strict_types=1);

namespace Talleu\TriggerMapping\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
class Trigger
{
    /** Pattern matching a SQL identifier safe to inline (no quoting needed, no injection vector). */
    public const IDENTIFIER_PATTERN = '/^[A-Za-z_][A-Za-z0-9_]{0,62}$/';

    public const ALLOWED_EVENTS = ['INSERT', 'UPDATE', 'DELETE', 'TRUNCATE'];
    public const ALLOWED_TIMINGS = ['BEFORE', 'AFTER', 'INSTEAD OF'];
    public const ALLOWED_SCOPES = ['ROW', 'STATEMENT'];

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
        // Identifier validation closes a wide range of attacks at the boundary:
        //  - SQL injection through name/table interpolation in DROP/CREATE statements
        //  - Path traversal when name/function is inlined into file paths
        //  - PHP code injection when name/function is rendered into generated migration files
        //  - Heredoc-breakout in skeleton templates that inline these values
        $this->assertIdentifier('name', $this->name);
        $this->assertIdentifierOrNull('function', $this->function);
        $this->assertIdentifierOrNull('onTable', $this->onTable);

        $this->assertEnum('when', $this->when, self::ALLOWED_TIMINGS);
        $this->assertEnum('scope', $this->scope, self::ALLOWED_SCOPES);

        foreach ($this->on as $i => $event) {
            $this->assertEnum("on[$i]", $event, self::ALLOWED_EVENTS);
        }
    }

    private function assertIdentifier(string $field, string $value): void
    {
        if (1 !== preg_match(self::IDENTIFIER_PATTERN, $value)) {
            throw new \InvalidArgumentException(sprintf(
                'Trigger %s "%s" is not a valid SQL identifier (must match %s).',
                $field,
                $value,
                self::IDENTIFIER_PATTERN,
            ));
        }
    }

    private function assertIdentifierOrNull(string $field, ?string $value): void
    {
        if (null !== $value) {
            $this->assertIdentifier($field, $value);
        }
    }

    /**
     * @param string[] $allowed
     */
    private function assertEnum(string $field, string $value, array $allowed): void
    {
        if (!in_array(strtoupper($value), $allowed, true)) {
            throw new \InvalidArgumentException(sprintf(
                'Trigger %s "%s" is not allowed. Expected one of: %s.',
                $field,
                $value,
                implode(', ', $allowed),
            ));
        }
    }
}
