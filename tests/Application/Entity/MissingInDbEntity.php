<?php

namespace Talleu\TriggerMapping\Tests\Application\Entity;

use Doctrine\ORM\Mapping as ORM;
use Talleu\TriggerMapping\Attribute\Trigger;

#[ORM\Entity]
#[Trigger(name: "missing_in_db_trigger", on: ["INSERT"], when: "AFTER", function: "missing_func")]
class MissingInDbEntity
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;
}