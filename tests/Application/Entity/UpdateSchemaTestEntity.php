<?php

declare(strict_types=1);

namespace Talleu\TriggerMapping\Tests\Application\Entity;

use Doctrine\ORM\Mapping as ORM;
use Talleu\TriggerMapping\Attribute\Trigger;
use Talleu\TriggerMapping\Tests\Application\Triggers\PostgresqlTriggerClass;

#[ORM\Entity]
#[Trigger(
    name: "trg_update_schema_test",
    function: "fn_update_schema_test",
    on: ["UPDATE"],
    timing: "BEFORE",
    scope: "ROW",
    className: PostgresqlTriggerClass::class
)]
class UpdateSchemaTestEntity
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $name = null;
}
