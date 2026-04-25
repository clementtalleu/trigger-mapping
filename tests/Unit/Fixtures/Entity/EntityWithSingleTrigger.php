<?php

declare(strict_types=1);

namespace Talleu\TriggerMapping\Tests\Unit\Fixtures\Entity;

use Doctrine\ORM\Mapping as ORM;
use Talleu\TriggerMapping\Attribute\Trigger;

#[ORM\Entity]
#[Trigger(
    name: 'trg_single',
    function: 'fn_single',
    on: ['INSERT'],
    when: 'AFTER',
    scope: 'ROW',
)]
class EntityWithSingleTrigger
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    public ?int $id = null;
}
