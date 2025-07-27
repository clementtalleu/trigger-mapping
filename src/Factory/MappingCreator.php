<?php

declare(strict_types=1);

namespace Talleu\TriggerMapping\Factory;

use Symfony\Bundle\MakerBundle\FileManager;
use Symfony\Bundle\MakerBundle\Util\ClassDetails;
use Talleu\TriggerMapping\Attribute\Trigger;
use Talleu\TriggerMapping\Model\ResolvedTrigger;
use Symfony\Bundle\MakerBundle\Util\ClassSourceManipulator;

final readonly class MappingCreator implements MappingCreatorInterface
{
    public function __construct(private FileManager $fileManager)
    {
    }

    /**
     * @inheritdoc
     */
    public function createMapping(
        ResolvedTrigger $resolvedTrigger,
        string          $entityFqcn,
        ?string         $triggerClassFqcn = null,
        ?string         $onTable = null
    ): void {
        $entityPath = $this->getPathOfClass($entityFqcn);
        $manipulator = $this->createClassManipulator($entityPath);

        $attributeArguments = [
            'name' => $resolvedTrigger->name,
            'on' => $resolvedTrigger->events,
            'timing' => $resolvedTrigger->timing,
            'scope' => $resolvedTrigger->scope,
        ];

        if (null !== $onTable) {
            $attributeArguments['onTable'] = $onTable;
        }

        if ($resolvedTrigger->function !== null) {
            $attributeArguments['function'] = $resolvedTrigger->function;
        }

        if ($triggerClassFqcn !== null) {
            $attributeArguments['className'] = $triggerClassFqcn;
        }

        $manipulator->addAttributeToClass(Trigger::class, $attributeArguments);

        $this->fileManager->dumpFile($entityPath, $manipulator->getSourceCode());
    }

    private function getPathOfClass(string $class): string
    {
        return (new ClassDetails($class))->getPath();
    }

    private function createClassManipulator(string $path): ClassSourceManipulator
    {
        return new ClassSourceManipulator(
            sourceCode: $this->fileManager->getFileContents($path),
            overwrite: true,
        );
    }
}
