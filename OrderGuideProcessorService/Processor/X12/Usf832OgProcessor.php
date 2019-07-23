<?php
declare(strict_types=1);

namespace App\Utils\EDI\OrderGuideImportService\OrderGuideProcessorService\Processor\X12;

use App\Utils\EDI\Entity\EdiProcessorSetupInterface;
use App\Utils\EDI\Entity\SettingsEntity\PullOrderGuideEdiSetting;
use App\Utils\EDI\OrderGuideImportService\Entity\OrderGuideError;
use App\Utils\EDI\OrderGuideImportService\Entity\OrderGuideFile;
use App\Utils\EDI\OrderGuideImportService\Entity\OrderGuideFileItem;
use App\Utils\EDI\OrderGuideImportService\Entity\OrderGuideImportSetup\UsfX12OrderGuideImportSetup;
use App\Utils\Tools\FileParser\SchemaParser;
use App\Utils\Tools\Normalizer;

class Usf832OgProcessor extends Abstract832OgProcessor
{
    const PROCESSOR_NAME = 'USF';
    const DATE_FORMAT    = 'Ymd';

    const CUSTOMER_NUMBER_PATH = ["LOOP_N1_2", 0, "N1", 3];
    const OG_DATE_PATH         = ["DTM", 0, 1];
    const ITEM_NO_PATH         = ["LIN", 2];
    const BRAND_PATH           = ["LIN", 8];
    const BARCODE_PATH         = ["LIN", 10];
    const UNIT_TYPE_PATH       = ["PO1", 2];
    const DISCONTINUED_PATH    = [["REF"], ['ACC' => 0, 'DSC' => 1], 2];
    const DESCRIPTION_PATH     = ["PID", 0, 4];
    const PACK_SIZE_PATH       = ["PKG", 0, 4];
    const PRICE_PATH           = ["LOOP_CTP", 0, "CTP", 2];
    const PRICE_TYPE_PATH      = ["LOOP_CTP", 0, "CTP", 4];
    const PRICE_DATE_PATH      = ["LOOP_CTP", 0, "DTM", 0, 1];

    protected $fileStructure = [
        self::FILE_HEADING => [
            SchemaParser::SEGMENTS_FIELD => [
                'ISA' => [SchemaParser::REQUIRED_FIELD => true, SchemaParser::MULTIPLE_FIELD => false, SchemaParser::DESC_FIELD => 'Interchange Control Header', SchemaParser::COUNT_FIELD => 16],
                'GS'  => [SchemaParser::REQUIRED_FIELD => true, SchemaParser::MULTIPLE_FIELD => false, SchemaParser::DESC_FIELD => 'Functional Group Header'],
            ],
        ],
        self::HEADING      => [
            SchemaParser::LOOP_FIELD     => true,
            SchemaParser::SEGMENTS_FIELD => [
                'ST'          => [SchemaParser::REQUIRED_FIELD => true, SchemaParser::MULTIPLE_FIELD => false, SchemaParser::DESC_FIELD => 'Transaction Set Header'],
                'BCT'         => [SchemaParser::REQUIRED_FIELD => true, SchemaParser::MULTIPLE_FIELD => false, SchemaParser::DESC_FIELD => 'Beginning Segment for Price/Sales Catalog'],
                'REF'         => [SchemaParser::REQUIRED_FIELD => false, SchemaParser::MULTIPLE_FIELD => true, SchemaParser::DESC_FIELD => 'Reference Identification'],
                'DTM'         => [SchemaParser::REQUIRED_FIELD => false, SchemaParser::MULTIPLE_FIELD => true, SchemaParser::DESC_FIELD => 'Date/Time Reference'],
                'LOOP_N1_1'   => [
                    SchemaParser::LOOP_FIELD     => true,
                    SchemaParser::SEGMENTS_FIELD => [
                        'N1' => [SchemaParser::REQUIRED_FIELD => false, SchemaParser::MULTIPLE_FIELD => false, SchemaParser::SUBFIELD_FIELD => 'SE', SchemaParser::DESC_FIELD => 'Name of seller'],
                    ],
                ],
                'LOOP_N1_2'   => [
                    SchemaParser::LOOP_FIELD     => true,
                    SchemaParser::SEGMENTS_FIELD => [
                        'N1' => [SchemaParser::REQUIRED_FIELD => false, SchemaParser::MULTIPLE_FIELD => false, SchemaParser::SUBFIELD_FIELD => 'BY', SchemaParser::DESC_FIELD => 'Name of buyer'],
                        'N3' => [SchemaParser::REQUIRED_FIELD => false, SchemaParser::MULTIPLE_FIELD => true, SchemaParser::DESC_FIELD => 'Address Information'],
                        'N4' => [SchemaParser::REQUIRED_FIELD => false, SchemaParser::MULTIPLE_FIELD => true, SchemaParser::DESC_FIELD => 'Geographic Location'],
                    ],
                ],
                self::DETAIL  => [
                    SchemaParser::SEGMENTS_FIELD => [
                        'LOOP_LIN' => [
                            SchemaParser::LOOP_FIELD     => true,
                            SchemaParser::SEGMENTS_FIELD => [
                                'LIN'      => [SchemaParser::REQUIRED_FIELD => false, SchemaParser::MULTIPLE_FIELD => false, SchemaParser::DESC_FIELD => 'Item Identification'],
                                'PO1'      => [SchemaParser::REQUIRED_FIELD => false, SchemaParser::MULTIPLE_FIELD => false, SchemaParser::DESC_FIELD => 'Baseline Item Data'],
                                'REF'      => [SchemaParser::REQUIRED_FIELD => false, SchemaParser::MULTIPLE_FIELD => true, SchemaParser::DESC_FIELD => 'Reference Identification'],
                                'YNQ'      => [SchemaParser::REQUIRED_FIELD => false, SchemaParser::MULTIPLE_FIELD => true, SchemaParser::DESC_FIELD => 'Yes/No Question'],
                                'PID'      => [SchemaParser::REQUIRED_FIELD => false, SchemaParser::MULTIPLE_FIELD => true, SchemaParser::DESC_FIELD => 'Product/Item Description'],
                                'PKG'      => [SchemaParser::REQUIRED_FIELD => false, SchemaParser::MULTIPLE_FIELD => true, SchemaParser::DESC_FIELD => 'Marking, Packaging, Loading'],
                                'PO4'      => [SchemaParser::REQUIRED_FIELD => false, SchemaParser::MULTIPLE_FIELD => false, SchemaParser::DESC_FIELD => 'Item Physical Details'],
                                'LOOP_CTP' => [
                                    SchemaParser::LOOP_FIELD     => true,
                                    SchemaParser::SEGMENTS_FIELD => [
                                        'CTP' => [SchemaParser::REQUIRED_FIELD => false, SchemaParser::MULTIPLE_FIELD => false, SchemaParser::DESC_FIELD => 'Pricing Information'],
                                        'DTM' => [SchemaParser::REQUIRED_FIELD => false, SchemaParser::MULTIPLE_FIELD => true, SchemaParser::DESC_FIELD => 'Date/Time Reference'],
                                    ],
                                ],
                                'LOOP_N1'  => [
                                    SchemaParser::LOOP_FIELD     => true,
                                    SchemaParser::SEGMENTS_FIELD => [
                                        'REF' => [SchemaParser::REQUIRED_FIELD => false, SchemaParser::MULTIPLE_FIELD => true, SchemaParser::DESC_FIELD => 'Reference Identification'],
                                    ],
                                ],
                                'LOOP_G39' => [
                                    SchemaParser::LOOP_FIELD     => true,
                                    SchemaParser::SEGMENTS_FIELD => [
                                        'G39' => [SchemaParser::REQUIRED_FIELD => false, SchemaParser::MULTIPLE_FIELD => false, SchemaParser::DESC_FIELD => 'Item Characteristics Vendor\'s Selling Unit'],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
                self::SUMMARY => [
                    SchemaParser::SEGMENTS_FIELD => [
                        'CTT' => [SchemaParser::REQUIRED_FIELD => false, SchemaParser::MULTIPLE_FIELD => false, SchemaParser::DESC_FIELD => 'Transaction Totals'],
                        'SE'  => [SchemaParser::REQUIRED_FIELD => true, SchemaParser::MULTIPLE_FIELD => false, SchemaParser::DESC_FIELD => 'Transaction Set Trailer'],
                    ],
                ],
            ],
        ],
        self::FILE_FOOTER  => [
            SchemaParser::SEGMENTS_FIELD => [
                'GE'  => [SchemaParser::REQUIRED_FIELD => true, SchemaParser::MULTIPLE_FIELD => false, SchemaParser::DESC_FIELD => 'Functional Group Trailer'],
                'IEA' => [SchemaParser::REQUIRED_FIELD => true, SchemaParser::MULTIPLE_FIELD => false, SchemaParser::DESC_FIELD => 'Interchange Control Trailer'],
            ],
        ],
    ];

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
        $ogFile->setItems($this->processOgItems($ogItemsArray));

        return $ogFile;
    }

    /**
     * Fill Og File by items.
     *
     * @param array $ogItemsArray
     *
     * @return OrderGuideFileItem[]
     * @throws \ReflectionException
     * @throws \Exception
     */
    private function processOgItems(array $ogItemsArray): array
    {
        $ogItems = [];
        foreach ($ogItemsArray as $ogItemArray) {
            $itemNo = Normalizer::getValueAtPath($ogItemArray, self::ITEM_NO_PATH);
            $unitType = Normalizer::getValueAtPath($ogItemArray, self::UNIT_TYPE_PATH);

            $ogItem = (new OrderGuideFileItem($itemNo, $unitType))
                ->setBarcode(Normalizer::getValueAtPath($ogItemArray, self::BARCODE_PATH))
                ->setBrand(Normalizer::getValueAtPath($ogItemArray, self::BRAND_PATH))
                ->setDescription(Normalizer::getValueAtPath($ogItemArray, self::DESCRIPTION_PATH))
                ->setDiscontinued((bool) $this->getSubfieldValue($ogItemArray, self::DISCONTINUED_PATH[0], self::DISCONTINUED_PATH[1], self::DISCONTINUED_PATH[2]));

            if (empty($packSize = Normalizer::getValueAtPath($ogItemArray, self::PACK_SIZE_PATH))) {
                $error = $this->createEmptyPackSizeError($itemNo);
                $ogItem->addError($error);
            }
            $ogItem->setPackSize($packSize);

            if ("LB" === Normalizer::getValueAtPath($ogItemArray, self::PRICE_TYPE_PATH)) {
                $ogItem->setPricePerPound(Normalizer::getValueAtPath($ogItemArray, self::PRICE_PATH));
            } else {
                $ogItem->setPricePerCase(Normalizer::getValueAtPath($ogItemArray, self::PRICE_PATH));
            }

            $dateStr = Normalizer::getValueAtPath($ogItemArray, self::PRICE_DATE_PATH);
            $ogItem->setPriceDate(Normalizer::getDateTime($dateStr, self::DATE_FORMAT));

            $ogItems[] = $ogItem;
        }

        return $ogItems;
    }

    /**
     * {@inheritDoc}
     */
    public function supports(?EdiProcessorSetupInterface $processorSetup): bool
    {
        return $processorSetup instanceof UsfX12OrderGuideImportSetup;
    }
}