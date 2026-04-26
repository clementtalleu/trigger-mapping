<?php

declare(strict_types=1);

namespace Talleu\TriggerMapping\Tests\Application\Entity;

use Doctrine\ORM\Mapping as ORM;
use Talleu\TriggerMapping\Attribute\Trigger;

/**
 * Regression fixture for audit finding F-2
 * firing on `INSERT OR UPDATE OR DELETE` was unparseable with the old
 * text-based extraction. The new bitfield-based decoding must surface the
 * three events correctly.
 */
#[ORM\Entity]
#[Trigger(
    name: 'trg_multi_events',
    function: 'fn_multi_events',
    on: ['INSERT', 'UPDATE', 'DELETE'],
    when: 'BEFORE',
    scope: 'ROW',
)]
class PostgresqlMultiEventsEntity
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    public ?int $id = null;
}
