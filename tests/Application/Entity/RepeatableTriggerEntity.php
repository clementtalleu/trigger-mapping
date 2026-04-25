<?php

declare(strict_types=1);

namespace Talleu\TriggerMapping\Tests\Application\Entity;

use Doctrine\ORM\Mapping as ORM;
use Talleu\TriggerMapping\Attribute\Trigger;

/**
 * Fixture exercising the IS_REPEATABLE behaviour of #[Trigger]: two triggers
 * with distinct names on the same entity must both be discovered by the bundle.
 */
#[ORM\Entity]
#[Trigger(name: 'trg_repeatable_a', on: ['INSERT'], when: 'AFTER', scope: 'ROW')]
#[Trigger(name: 'trg_repeatable_b', on: ['UPDATE'], when: 'AFTER', scope: 'ROW')]
class RepeatableTriggerEntity
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    public ?int $id = null;
}
