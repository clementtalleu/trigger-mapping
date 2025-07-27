<?php

declare(strict_types=1);

namespace Talleu\TriggerMapping\Bundle;

use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\HttpKernel\Bundle\Bundle;
use Talleu\TriggerMapping\Bundle\DependencyInjection\TriggerMappingExtension;

final class TriggerMappingBundle extends Bundle
{
    /**
     * {@inheritdoc}
     */
    public function boot(): void
    {
    }

    public function getContainerExtension(): ?ExtensionInterface
    {
        if (!$this->extension) {
            $this->extension = new TriggerMappingExtension();
        }

        return $this->extension;
    }
}
