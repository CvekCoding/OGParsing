<?php

declare(strict_types=1);

namespace App\Utils\EDI\OrderGuideImportService\OrderGuideProcessorService\Processor\X12;

use App\DBAL\EnumVendorProductPackType;
use App\Utils\EDI\Entity\EdiProcessorSetupInterface;
use App\Utils\EDI\Entity\SettingsEntity\PullOrderGuideEdiSetting;
use App\Utils\EDI\OrderGuideImportService\Entity\OrderGuideError;
use App\Utils\EDI\OrderGuideImportService\Entity\OrderGuideFile;
use App\Utils\EDI\OrderGuideImportService\Entity\OrderGuideFileItem;
use App\Utils\EDI\OrderGuideImportService\Entity\OrderGuideImportSetup\SyscoX12OrderGuideImportSetup;
use App\Utils\Tools\FileParser\SchemaParser;
use App\Utils\Tools\Normalizer;

class Sysco832OgProcessor extends Abstract832OgProcessor
{
    public const PROCESSOR_NAME = 'Sysco';
    public const DATE_FORMAT = 'Ymd';

    public const OG_DATE_PATH = ['DTM', 0, 1];
    public const CUSTOMER_NUMBER_PATH = ['N1', 3];
    public const ITEM_NO_PATH = ['LIN', 2];
    public const BARCODE_PATH = ['LIN', 8];
    public const BRAND_PATH = ['LIN', 10];
    public const PRICE_DATE_PATH = ['DTM', 1];
    public const PRICE_TYPE_PATH = ['REF', 0, 1];
    public const DESCRIPTION_PATH = ['PID', 0, 4];
    public const PACK_SIZE_PATH = ['PKG', 0, 4];
    public const PRICE_PATH = ['CTP', 2];

    protected $fileStructure = [
        self::FILE_HEADING => [
            SchemaParser::SEGMENTS_FIELD => [
                'ISA' => [SchemaParser::REQUIRED_FIELD => true, SchemaParser::MULTIPLE_FIELD => false, SchemaParser::DESC_FIELD => 'Interchange Control Header', SchemaParser::COUNT_FIELD => 16],
                'GS' => [SchemaParser::REQUIRED_FIELD => true, SchemaParser::MULTIPLE_FIELD => false, SchemaParser::DESC_FIELD => 'Functional Group Header'],
            ],
        ],

        self::HEADING => [
            SchemaParser::LOOP_FIELD => true,
            SchemaParser::SEGMENTS_FIELD => [
                'ST' => [SchemaParser::REQUIRED_FIELD => true, SchemaParser::MULTIPLE_FIELD => false, SchemaParser::DESC_FIELD => 'Transaction Set Header'],
                'BCT' => [SchemaParser::REQUIRED_FIELD => true, SchemaParser::MULTIPLE_FIELD => false, SchemaParser::DESC_FIELD => 'Beginning Segment for Price/Sales Catalog'],
                'REF' => [SchemaParser::REQUIRED_FIELD => false, SchemaParser::MULTIPLE_FIELD => true, SchemaParser::DESC_FIELD => 'Reference Identification'],
                'DTM' => [SchemaParser::REQUIRED_FIELD => false, SchemaParser::MULTIPLE_FIELD => true, SchemaParser::DESC_FIELD => 'Date/Time Reference'],
                'N1' => [SchemaParser::REQUIRED_FIELD => true, SchemaParser::MULTIPLE_FIELD => false, SchemaParser::DESC_FIELD => 'Name of buyer'],

                self::DETAIL => [
                    SchemaParser::SEGMENTS_FIELD => [
                        'LOOP_LIN' => [
                            SchemaParser::LOOP_FIELD => true,
                            SchemaParser::SEGMENTS_FIELD => [
                                'LIN' => [SchemaParser::REQUIRED_FIELD => false, SchemaParser::MULTIPLE_FIELD => false, SchemaParser::DESC_FIELD => 'Item Identification'],
                                'DTM' => [SchemaParser::REQUIRED_FIELD => false, SchemaParser::MULTIPLE_FIELD => false, SchemaParser::DESC_FIELD => 'Date/Time Reference'],
                                'REF' => [SchemaParser::REQUIRED_FIELD => false, SchemaParser::MULTIPLE_FIELD => true, SchemaParser::DESC_FIELD => 'Reference Identification'],
                                'PID' => [SchemaParser::REQUIRED_FIELD => false, SchemaParser::MULTIPLE_FIELD => true, SchemaParser::DESC_FIELD => 'Product/Item Description'],
                                'PKG' => [SchemaParser::REQUIRED_FIELD => false, SchemaParser::MULTIPLE_FIELD => true, SchemaParser::DESC_FIELD => 'Marking, Packaging, Loading'],
                                'PO4' => [SchemaParser::REQUIRED_FIELD => false, SchemaParser::MULTIPLE_FIELD => false, SchemaParser::DESC_FIELD => 'Item Physical Details'],
                                'CTP' => [SchemaParser::REQUIRED_FIELD => false, SchemaParser::MULTIPLE_FIELD => false, SchemaParser::DESC_FIELD => 'Pricing Information'],
                            ],
                        ],
                    ],
                ],

                self::SUMMARY => [
                    SchemaParser::SEGMENTS_FIELD => [
                        'CTT' => [SchemaParser::REQUIRED_FIELD => false, SchemaParser::MULTIPLE_FIELD => false, SchemaParser::DESC_FIELD => 'Transaction Totals'],
                        'SE' => [SchemaParser::REQUIRED_FIELD => true, SchemaParser::MULTIPLE_FIELD => false, SchemaParser::DESC_FIELD => 'Transaction Set Trailer'],
                    ],
                ],
            ],
        ],

        self::FILE_FOOTER => [
            SchemaParser::SEGMENTS_FIELD => [
                'GE' => [SchemaParser::REQUIRED_FIELD => true, SchemaParser::MULTIPLE_FIELD => false, SchemaParser::DESC_FIELD => 'Functional Group Trailer'],
                'IEA' => [SchemaParser::REQUIRED_FIELD => true, SchemaParser::MULTIPLE_FIELD => false, SchemaParser::DESC_FIELD => 'Interchange Control Trailer'],
            ],
        ],
    ];

    /**
     * {@inheritdoc}
     */
    public function supports(?EdiProcessorSetupInterface $processorSetup): bool
    {
        return $processorSetup instanceof SyscoX12OrderGuideImportSetup;
    }

    /**
     * Create Og File object.
     *
     * @param array $ogArray
     *
     * @return OrderGuideFile
     *
     * @throws \ReflectionException
     * @throws \Exception
     */
    protected function createOgFileObject(array $ogArray): OrderGuideFile
    {
        $ogFile = new OrderGuideFile();
        $ogFile->setOgProcessor($this);

        $customerNumber = Normalizer::getValueAtPath($ogArray, self::CUSTOMER_NUMBER_PATH);

        if (isset($customerNumber)) {
            $locationVendor = $this->findLocationVendorByCustomerNumber(new PullOrderGuideEdiSetting(), $customerNumber);
        }

        if (!isset($locationVendor)) {
            $error = $this->createError(OrderGuideError::LOCATION_VENDOR_NOT_FOUND, "Customer number $customerNumber was not found.");
            $ogFile->addError($error);

            return $ogFile;
        }

        $ogFile->setLocationVendors([$locationVendor]);

        if (!empty($dateStr = Normalizer::getValueAtPath($ogArray, self::OG_DATE_PATH))) {
            $ogFile->setDate(Normalizer::getDateTime($dateStr, self::DATE_FORMAT));
        }

        $ogItemsArray = Normalizer::getValueAtPath($ogArray, self::ITEMS_PATH);
        $ogFile->setItems($this->createOgItems($ogItemsArray));

        return $ogFile;
    }

    /**
     * Fill Og File by items.
     *
     * @param array $ogItemsArray
     *
     * @return OrderGuideFileItem[]
     *
     * @throws \ReflectionException
     */
    private function createOgItems(array $ogItemsArray): array
    {
        $ogItems = [];
        foreach ($ogItemsArray as $ogItemArray) {
            $ogItems[] = $this->createOgItemFromArray($ogItemArray);
        }

        return $ogItems;
    }

    /**
     * @param $ogItemArray
     *
     * @return OrderGuideFileItem
     *
     * @throws \ReflectionException
     * @throws \Exception
     */
    private function createOgItemFromArray(array $ogItemArray): OrderGuideFileItem
    {
        $itemNo = Normalizer::getValueAtPath($ogItemArray, self::ITEM_NO_PATH);
        $ogItem = new OrderGuideFileItem($itemNo);

        if ('1' === Normalizer::getValueAtPath($ogItemArray, self::PRICE_TYPE_PATH)) {
            $ogItem->setPackType(EnumVendorProductPackType::EACH);
            $ogItem->setPricePerPound(Normalizer::getValueAtPath($ogItemArray, self::PRICE_PATH));
        } else {
            $ogItem->setPackType(EnumVendorProductPackType::CASE);
            $ogItem->setPricePerCase(Normalizer::getValueAtPath($ogItemArray, self::PRICE_PATH));
        }

        $ogItem->setBarcode(Normalizer::getValueAtPath($ogItemArray, self::BARCODE_PATH));
        $ogItem->setBrand(Normalizer::getValueAtPath($ogItemArray, self::BRAND_PATH));
        $ogItem->setDescription(Normalizer::getValueAtPath($ogItemArray, self::DESCRIPTION_PATH));

        $dateStr = Normalizer::getValueAtPath($ogItemArray, self::PRICE_DATE_PATH);
        $ogItem->setPriceDate(Normalizer::getDateTime($dateStr, self::DATE_FORMAT));

        if (empty($packSize = Normalizer::getValueAtPath($ogItemArray, self::PACK_SIZE_PATH))) {
            $error = $this->createEmptyPackSizeError($itemNo);
            $ogItem->addError($error);
        }

        $ogItem->setPackSize($packSize);

        return $ogItem;
    }
}
