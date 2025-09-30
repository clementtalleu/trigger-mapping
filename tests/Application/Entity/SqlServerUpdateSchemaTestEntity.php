<?php

declare(strict_types=1);

namespace Talleu\TriggerMapping\Tests\Application\Entity;

use Doctrine\ORM\Mapping as ORM;
use Talleu\TriggerMapping\Attribute\Trigger;
use Talleu\TriggerMapping\Tests\Application\Triggers\SqlServerTriggerClass;

#[ORM\Entity]
#[Trigger(
    name: "trg_update_schema_test",
    on: ["UPDATE"],
    when: "AFTER",
    scope: "ROW",
    className: SqlServerTriggerClass::class
)]
class SqlServerUpdateSchemaTestEntity
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $name = null;
}
