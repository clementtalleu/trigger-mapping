<?php

declare(strict_types=1);

namespace Talleu\TriggerMapping\Tests\Unit\Fixtures\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Plain entity used by unit tests to feed Symfony MakerBundle's ClassDetails reflection.
 * The actual file content read by tests is overridden through mocked FileManager calls,
 * so this file's body must stay simple but should remain a valid Doctrine entity.
 */
#[ORM\Entity]
class SimpleUnmappedEntity
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    public ?int $id = null;
}
