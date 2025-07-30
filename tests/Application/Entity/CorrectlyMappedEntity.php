<?php

namespace Talleu\TriggerMapping\Tests\Application\Entity;

use Doctrine\ORM\Mapping as ORM;
use Talleu\TriggerMapping\Attribute\Trigger;

#[ORM\Entity]
#[Trigger(name: "correctly_mapped_trigger", on: ["UPDATE"], when: "BEFORE", scope: "ROW", function: "correct_func")]
class CorrectlyMappedEntity
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;
}