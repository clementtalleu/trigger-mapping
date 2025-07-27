<?php

namespace Talleu\TriggerMapping\Tests\Application\Entity;

use Doctrine\ORM\Mapping as ORM;
use Talleu\TriggerMapping\Attribute\Trigger;

#[ORM\Entity]
class UpdateMappingTestEntity
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $name = null;
}