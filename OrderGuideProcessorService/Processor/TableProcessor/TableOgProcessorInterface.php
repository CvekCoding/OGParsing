<?php
declare(strict_types=1);

namespace App\Utils\EDI\OrderGuideImportService\OrderGuideProcessorService\Processor\TableProcessor;

use App\Utils\EDI\OrderGuideImportService\OrderGuideProcessorService\Processor\OgProcessorInterface;
use JMS\Serializer\Handler\SubscribingHandlerInterface;

/**
 * Extension of AbstractProcessor, useful for processors where $fileStructure is one-dimensional array (header of table).
 * The main difference is that here we use JMS serializer.
 */
interface TableOgProcessorInterface extends OgProcessorInterface
{
    /**
     * This method is using by DI to inject handlers.
     *
     * @param SubscribingHandlerInterface $handler
     */
    public function addSerializerHandler(SubscribingHandlerInterface $handler): void;
}
