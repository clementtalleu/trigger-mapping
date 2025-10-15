<?php

declare(strict_types=1);

namespace Talleu\TriggerMapping\Factory;

use Symfony\Bundle\MakerBundle\Util\ClassNameDetails;
use Symfony\Component\Console\Style\StyleInterface;
use Talleu\TriggerMapping\Model\ResolvedTrigger;

interface TriggerCreatorInterface
{
    /**
     * @param ResolvedTrigger[] $resolvedTriggers
     * @return ClassNameDetails[]
     */
    public function create(
        string $namespace,
        array $resolvedTriggers,
        ?bool $createMigrations = null,
        ?StyleInterface $io = null,
        ?string $migrationsNamespace = null,
    ): array;
}
