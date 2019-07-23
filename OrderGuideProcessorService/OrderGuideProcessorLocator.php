<?php

declare(strict_types=1);

namespace App\Utils\EDI\OrderGuideImportService\OrderGuideProcessorService;

use App\Entity\Main\LocationVendor;
use App\Utils\EDI\OrderGuideImportService\OrderGuideProcessorService\Processor\OgProcessorInterface;
use Symfony\Component\HttpFoundation\File\File;

/**
 * Helper to find the proper OG processor.
 */
final class OrderGuideProcessorLocator
{
    /** @var OgProcessorInterface[] */
    private $processors = [];

    /**
     * Using by DI.
     *
     * @param OgProcessorInterface $processor
     */
    public function addProcessor(OgProcessorInterface $processor): void
    {
        if (!\in_array($processor, $this->processors, true)) {
            $this->processors[] = $processor;
        }
    }

    /**
     * Search for the proper file processing service.
     *
     * @param File             $ogFile
     * @param LocationVendor[] $locationVendors
     *
     * @return OgProcessorInterface|null
     */
    public function locateFileProcessor(File $ogFile, array $locationVendors): ?OgProcessorInterface
    {
        foreach ($this->processors as $processor) {
            if ($processor->isFileProcessable($ogFile, $locationVendors)) {
                return $processor;
            }
        }

        return null;
    }
}
