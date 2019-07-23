<?php
/**
 * This file is part of the Diningedge package.
 *
 * (c) Sergey Logachev <svlogachev@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace App\Utils\EDI\OrderGuideImportService\OrderGuideProcessorService\Processor\TableProcessor;

use App\DBAL\EnumVendorProductPackType;
use App\Entity\Main\LocationVendorItem;
use App\Utils\EDI\OrderGuideImportService\Entity\OrderGuideError;
use App\Utils\EDI\OrderGuideImportService\Entity\OrderGuideFileItem;
use App\Utils\EntityService\LocationVendorItemService;
use Symfony\Component\HttpFoundation\File\File;

class PuOgProcessor extends AbstractTableOgProcessor
{
    public const  PROCESSOR_NAME = 'PU';
    private const MIN_CSV_COLUMNS = 2;

    // Ethalon header for PU file
    protected $fileStructure = ['Item No', 'Unit Price Per Case', 'Reserved', 'Price Per Each'];
    protected $creationPermitted = false;
    protected $fileIsHeaderless = true;
    private $priceChangeThreshold = .25;

    /** @var LocationVendorItemService */
    private $lvpService;

    /**
     * {@inheritdoc}
     *
     * @throws \ReflectionException
     * @throws \Doctrine\ORM\NonUniqueResultException
     * @throws \Exception
     */
    public function getItemsFromArray(array $normalizedItems, array $locationVendors): array
    {
        $items = [];
        foreach ($normalizedItems as $index => $normalizedItem) {
            foreach ($this->getOgItemsFromArray($normalizedItem) as $ogItem) {
                foreach ($locationVendors as $locationVendor) {
                    $locationVendorProduct = $this->lvpService->findOneByItemNoAndPackType($locationVendor, $ogItem->getVendorItemId(), $ogItem->getPackType());

                    if (!isset($locationVendorProduct)) {
                        $error = $this->deserializeError($normalizedItem, OrderGuideError::ITEM_NOT_FOUND, 'Item was not found.');
                        $error->setLocationVendor($locationVendor);
                        $ogItem->addError($error);

                        continue;
                    }

                    $ogItem->addLocationVendorProduct($locationVendorProduct);

                    $vendorProduct = $locationVendorProduct->getVendorItem();
                    $ogItem->setPackSize((string) $vendorProduct->getMeasure());
                    $ogItem->setDescription($vendorProduct->getName());
                    $ogItem->setBrand($vendorProduct->getBrand());
                    $ogItem->setBarcode($vendorProduct->getBarCode());

                    if (null !== $error = $this->getPriceDeviationError($locationVendorProduct, $ogItem->getPrice())) {
                        $error->setLocationVendor($locationVendor);
                        $ogItem->addError($error);
                    }
                }

                $ogItem->setPriceDate(new \DateTime());

                $items[] = $ogItem;
            }
        }

        return $items;
    }

    /**
     * One row may contain 2 different prices simultaneously - per case and per each.
     * In this case we have to consider this row like two different items, because they will be mapped to two different vendor products.
     *
     * @param array $normalizedItem
     *
     * @return OrderGuideFileItem[]
     */
    private function getOgItemsFromArray(array $normalizedItem): array
    {
        $combinedItem = $this->deserializeOgItemFromArray($normalizedItem);

        $ogItems = [];

        if (!empty($combinedItem->getPricePerCase())) {
            $csItem = clone $combinedItem;
            $csItem->setPricePerEach(null);
            $csItem->setPackType(EnumVendorProductPackType::CASE);
            $ogItems[] = $csItem;
        }

        if (!empty($combinedItem->getPricePerEach())) {
            $eaItem = clone $combinedItem;
            $eaItem->setPricePerCase($combinedItem->getPricePerEach());
            $eaItem->setPackType(EnumVendorProductPackType::EACH);
            $ogItems[] = $eaItem;
        }

        return $ogItems;
    }

    /**
     * Check the price deviation. If limit exceeded - generate error.
     *
     * @param LocationVendorItem $locationVendorProduct
     * @param float              $newPrice
     *
     * @return OrderGuideError|null
     *
     * @throws \ReflectionException
     */
    private function getPriceDeviationError(LocationVendorItem $locationVendorProduct, ?float $newPrice): ?OrderGuideError
    {
        $currentPrice = (null !== $lastPriceObject = $locationVendorProduct->getLastPriceObject())
            ? $lastPriceObject->getPrice()
            : null;

        if ($this->isPriceDeviated($currentPrice, $newPrice)) {
            $error = new OrderGuideError();
            $error->setItemNo($locationVendorProduct->getCompanyVendorItem()->getVendorItem()->getVendorItemId());
            $error->setErrorLevel($error::PRICE_CHANGE_EXCEEDED);
            $error->setMessage('Item price was changed more than '.$this->priceChangeThreshold * 100 .'%. '
                ."Old price: \$$currentPrice per {$lastPriceObject->getPriceType()}, new price: \$$newPrice per {$lastPriceObject->getPriceType()}"
            );

            return $error;
        }

        return null;
    }

    /**
     * Checks that new price was changed significantly.
     * If old price is null - recognize like not deviated.
     * If at least one of prices (old or new) is zero consider this like deviation.
     *
     * @param float|null $oldPrice
     * @param float|null $newPrice
     *
     * @return bool
     */
    private function isPriceDeviated(?float $oldPrice, ?float $newPrice): bool
    {
        if (!isset($oldPrice)) {
            return false;
        }

        if (empty($oldPrice) || empty($newPrice)) {
            return true;
        }

        return \abs($newPrice / $oldPrice - 1) > $this->priceChangeThreshold;
    }

    /**
     * {@inheritdoc}
     */
    final public function isFileProcessable(File $invoiceFile, array $locationVendors): bool
    {
        foreach ($this->parsers as $parser) {
            if (empty($fileStructure = $this->getFileHeader($invoiceFile, $parser))) {
                continue;
            }

            if ($this->isStructureValid($fileStructure)) {
                $this->parser = $parser;

                return true;
            }
        }

        return false;
    }

    /**
     * If current file structure has ethalon structure (this->fileStructure) or simply 2 columns.
     *
     * @param array $fileStructure
     *
     * @return bool
     */
    private function isStructureValid(array $fileStructure): bool
    {
        return (\count($fileStructure) === \count($this->fileStructure)) || (self::MIN_CSV_COLUMNS === \count($fileStructure));
    }

    /**
     * @return float
     */
    public function getPriceChangeThreshold(): float
    {
        return $this->priceChangeThreshold;
    }

    /**
     * @param float $threshold
     */
    final public function setPriceChangeThreshold(float $threshold): void
    {
        $this->priceChangeThreshold = $threshold;
    }

    /**
     * @required
     *
     * @param LocationVendorItemService $locationVendorProductService
     */
    public function setLvpService(LocationVendorItemService $locationVendorProductService): void
    {
        $this->lvpService = $locationVendorProductService;
    }
}
