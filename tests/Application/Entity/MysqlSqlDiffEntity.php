<?php

declare(strict_types=1);

namespace Talleu\TriggerMapping\Tests\Application\Entity;

use Doctrine\ORM\Mapping as ORM;
use Talleu\TriggerMapping\Attribute\Trigger;

#[ORM\Entity]
#[Trigger(
    name: 'trg_sql_diff_test',
    on: ['INSERT'],
    when: 'AFTER',
    scope: 'ROW',
    storage: 'sql',
)]
class MysqlSqlDiffEntity
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    public ?int $id = null;
}
