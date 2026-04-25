<?php

declare(strict_types=1);

namespace Talleu\TriggerMapping\Tests\Unit\Fixtures;

/**
 * Plain class without #[Entity] — used to assert that TriggersMapping rejects it.
 */
class PlainNonEntity
{
}
