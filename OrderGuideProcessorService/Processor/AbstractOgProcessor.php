<?php
declare(strict_types=1);

namespace App\Utils\EDI\OrderGuideImportService\OrderGuideProcessorService\Processor;

use App\Utils\EDI\OrderGuideImportService\Entity\OrderGuideError;
use App\Utils\EDI\OrderGuideImportService\OrderGuideProcessorService\Parser\OrderGuideParserInterface;
use App\Utils\Tools\EDIProcessor\AbstractEdiProcessor;

abstract class AbstractOgProcessor extends AbstractEdiProcessor implements OgProcessorInterface
{
    protected $productCreationPermitted = false;

    /** @var OrderGuideParserInterface[] */
    protected $parsers = [];

    /**
     * This method is processor specific and must be implemented in each processor.
     *
     * @param array $normalizedItems
     * @param array $errors
     * @param array $locationVendors
     *
     * @return array
     */
    abstract protected function getOgFilesFromArray(array $normalizedItems, array $locationVendors, array $errors): array;

    /**
     * @inheritdoc
     */
    final public function addParser(OrderGuideParserInterface $parser): void
    {
        if (!\in_array($parser, $this->parsers, true)) {
            $this->parsers[] = $parser;
        }
    }

    final protected function processContentArray(array $contentArray, array $locationVendors, array $globalErrors): array
    {
        return $this->getOgFilesFromArray($contentArray, $locationVendors, $globalErrors);
    }

    final public function isProductCreationPermitted(): bool
    {
        return $this->productCreationPermitted;
    }

    /**
     * @param string|null $itemNo
     *
     * @return OrderGuideError
     *
     * @throws \ReflectionException
     */
    protected function createEmptyPackSizeError(string $itemNo = null): OrderGuideError
    {
        $error = new OrderGuideError();
        $error->setErrorLevel(OrderGuideError::WRONG_STRING_FORMAT);
        $error->setMessage("Pack-size field was not found or empty.");
        $error->setItemNo($itemNo);

        return $error;
    }
}
