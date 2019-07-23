<?php

declare(strict_types=1);

namespace App\Utils\EDI\OrderGuideImportService\OrderGuideProcessorService\DependencyInjection;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

final class OgParserPass implements CompilerPassInterface
{
    /**
     * Injects all known parsers into each processor.
     * Injects serializer handlers into each table processor to be able to use JMS serializer.
     *
     * @param ContainerBuilder $container
     */
    public function process(ContainerBuilder $container)
    {
        $processors = $container->findTaggedServiceIds('app.orderguide.processor');

        foreach ($processors as $processorClass => $processorTags) {
            // always first check if the primary service is defined
            if (!$container->has($processorClass)) {
                continue;
            }

            $definition = $container->findDefinition($processorClass);

            $parsers = $container->findTaggedServiceIds('app.orderguide.parser');
            foreach ($parsers as $id => $tags) {
                $definition->addMethodCall('addParser', [new Reference($id)]);
            }
        }

        $tableProcessors = $container->findTaggedServiceIds('app.orderguide.processor.table');

        foreach ($tableProcessors as $tableProcessorClass => $tableProcessorTags) {
            if (!$container->has($tableProcessorClass)) {
                continue;
            }

            $definition = $container->findDefinition($tableProcessorClass);

            $serializerHandlers = $container->findTaggedServiceIds('app.orderguide.serializer.handler');
            foreach ($serializerHandlers as $id => $tags) {
                $definition->addMethodCall('addSerializerHandler', [new Reference($id)]);
            }
        }
    }
}