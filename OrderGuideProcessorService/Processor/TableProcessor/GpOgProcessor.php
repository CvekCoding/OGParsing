<?php

declare(strict_types=1);

namespace App\Utils\EDI\OrderGuideImportService\OrderGuideProcessorService\Processor\TableProcessor;

final class GpOgProcessor extends AbstractTableOgProcessor
{
    public const PROCESSOR_NAME = 'GP';

    protected $productCreationPermitted = true;

    // Ethalon header for GP file
    protected $fileStructure = [
        'Sr No',
        'Item No',
        'Unit',
        'Pack Size',
        'Description',
        'Brand',
        'Unit Price Per Case',
        'Unit Price Per Packsize Unit',
        'Category',
        'Max Quantity',
        'Market Price',
        'Market Price Unit',
    ];

    /**
     * {@inheritdoc}
     */
    protected function getItemsFromArray(array $normalizedItems, array $locationVendors): array
    {
        $items = [];

        foreach ($normalizedItems as $normalizedItem) {
            $item = $this->deserializeOgItemFromArray($normalizedItem);
            $item->setPriceDate(new \DateTime());
            $items[] = $item;
        }

        return $items;
    }
}
