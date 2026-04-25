<?php

declare(strict_types=1);

namespace Talleu\TriggerMapping\Tests\Unit\Fixtures\Entity;

use Doctrine\ORM\Mapping as ORM;
use Talleu\TriggerMapping\Attribute\Trigger;

#[ORM\Entity]
#[Trigger(name: 'trg_multi_a', on: ['INSERT'], when: 'BEFORE', scope: 'ROW')]
#[Trigger(name: 'trg_multi_b', on: ['UPDATE'], when: 'AFTER', scope: 'ROW')]
class EntityWithMultipleTriggers
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    public ?int $id = null;
}
