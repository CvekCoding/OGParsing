<?php

declare(strict_types=1);

namespace App\Utils\EDI\OrderGuideImportService\OrderGuideProcessorService\DependencyInjection;

use App\Utils\EDI\OrderGuideImportService\OrderGuideProcessorService\OrderGuideProcessorLocator;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

final class OgProcessorPass implements CompilerPassInterface
{
    /**
     * Inject all known OG processors into OrderGuide processor service.
     *
     * @param ContainerBuilder $container
     */
    public function process(ContainerBuilder $container): void
    {
        // always first check if the primary service is defined
        if (!$container->has(OrderGuideProcessorLocator::class)) {
            return;
        }

        $definition = $container->findDefinition(OrderGuideProcessorLocator::class);

        $processors = $container->findTaggedServiceIds('app.orderguide.processor');

        foreach ($processors as $id => $tags) {
            $definition->addMethodCall('addProcessor', [new Reference($id)]);
        }
    }
}
