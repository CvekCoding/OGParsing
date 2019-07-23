<?php

declare(strict_types=1);

namespace App\Utils\EDI\OrderGuideImportService\OrderGuideProcessorService\Processor\X12;

use App\Entity\Main\LocationVendor;
use App\Utils\EDI\OrderGuideImportService\Entity\OrderGuideFile;
use App\Utils\EDI\OrderGuideImportService\OrderGuideProcessorService\Parser\OrderGuideParserInterface;
use App\Utils\EDI\OrderGuideImportService\OrderGuideProcessorService\Parser\X12OgParser;
use App\Utils\EDI\OrderGuideImportService\OrderGuideProcessorService\Processor\AbstractOgProcessor;
use App\Utils\Tools\FileParser\SchemaParser as Schema;
use App\Utils\Tools\Normalizer;
use Symfony\Component\HttpFoundation\File\File;
use Webmozart\Assert\Assert;

/**
 * Basic class to process X12 832 files.
 */
abstract class Abstract832OgProcessor extends AbstractOgProcessor
{
    public const FILE_HEADING = 'file_heading';
    public const FILE_FOOTER = 'file_footer';

    public const HEADING = 'heading';
    public const DETAIL = 'detail';
    public const SUMMARY = 'summary';

    public const ITEMS_PATH = [self::DETAIL, 'LOOP_LIN'];

    protected $normalizedArray = [];
    protected $productCreationPermitted = true;

    /**
     * Basic X12 832 structure. Can be overwritten in particular X12 832 implementation
     * depending on vendor rules. Sequence of fields is important!
     */
    protected $fileStructure = [
        self::FILE_HEADING => [
            Schema::SEGMENTS_FIELD => [
                'ISA' => [Schema::REQUIRED_FIELD => true, Schema::MULTIPLE_FIELD => false, Schema::DESC_FIELD => 'Interchange Control Header', Schema::COUNT_FIELD => 16],
                'GS' => [Schema::REQUIRED_FIELD => true, Schema::MULTIPLE_FIELD => false, Schema::DESC_FIELD => 'Functional Group Header'],
            ],
        ],
        self::HEADING => [
            Schema::LOOP_FIELD => true,
            Schema::SEGMENTS_FIELD => [
                'ST' => [Schema::REQUIRED_FIELD => true, Schema::MULTIPLE_FIELD => false, Schema::DESC_FIELD => 'Transaction Set Header'],
                'BCT' => [Schema::REQUIRED_FIELD => true, Schema::MULTIPLE_FIELD => false, Schema::DESC_FIELD => 'Beginning Segment for Price/Sales Catalog'],
                'REF' => [Schema::REQUIRED_FIELD => false, Schema::MULTIPLE_FIELD => true, Schema::DESC_FIELD => 'Reference Identification'],
                'DTM' => [Schema::REQUIRED_FIELD => false, Schema::MULTIPLE_FIELD => true, Schema::DESC_FIELD => 'Date/Time Reference'],
                'LOOP_N1_1' => [
                    Schema::LOOP_FIELD => true,
                    Schema::SEGMENTS_FIELD => [
                        'N1' => [Schema::REQUIRED_FIELD => false, Schema::MULTIPLE_FIELD => false, Schema::SUBFIELD_FIELD => 'SE', Schema::DESC_FIELD => 'Name of seller'],
                    ],
                ],
                'LOOP_N1_2' => [
                    Schema::LOOP_FIELD => true,
                    Schema::SEGMENTS_FIELD => [
                        'N1' => [Schema::REQUIRED_FIELD => false, Schema::MULTIPLE_FIELD => false, Schema::SUBFIELD_FIELD => 'BY', Schema::DESC_FIELD => 'Name of buyer'],
                        'N3' => [Schema::REQUIRED_FIELD => false, Schema::MULTIPLE_FIELD => true, Schema::DESC_FIELD => 'Address Information'],
                        'N4' => [Schema::REQUIRED_FIELD => false, Schema::MULTIPLE_FIELD => true, Schema::DESC_FIELD => 'Geographic Location'],
                    ],
                ],
                self::DETAIL => [
                    Schema::SEGMENTS_FIELD => [
                        'LOOP_LIN' => [
                            Schema::LOOP_FIELD => true,
                            Schema::SEGMENTS_FIELD => [
                                'LIN' => [Schema::REQUIRED_FIELD => false, Schema::MULTIPLE_FIELD => false, Schema::DESC_FIELD => 'Item Identification'],
                                'PO1' => [Schema::REQUIRED_FIELD => false, Schema::MULTIPLE_FIELD => false, Schema::DESC_FIELD => 'Baseline Item Data'],
                                'REF' => [Schema::REQUIRED_FIELD => false, Schema::MULTIPLE_FIELD => true, Schema::DESC_FIELD => 'Reference Identification'],
                                'YNQ' => [Schema::REQUIRED_FIELD => false, Schema::MULTIPLE_FIELD => true, Schema::DESC_FIELD => 'Yes/No Question'],
                                'PID' => [Schema::REQUIRED_FIELD => false, Schema::MULTIPLE_FIELD => true, Schema::DESC_FIELD => 'Product/Item Description'],
                                'PKG' => [Schema::REQUIRED_FIELD => false, Schema::MULTIPLE_FIELD => true, Schema::DESC_FIELD => 'Marking, Packaging, Loading'],
                                'PO4' => [Schema::REQUIRED_FIELD => false, Schema::MULTIPLE_FIELD => false, Schema::DESC_FIELD => 'Item Physical Details'],
                                'LOOP_CTP' => [
                                    Schema::LOOP_FIELD => true,
                                    Schema::SEGMENTS_FIELD => [
                                        'CTP' => [Schema::REQUIRED_FIELD => false, Schema::MULTIPLE_FIELD => false, Schema::DESC_FIELD => 'Pricing Information'],
                                        'DTM' => [Schema::REQUIRED_FIELD => false, Schema::MULTIPLE_FIELD => true, Schema::DESC_FIELD => 'Date/Time Reference'],
                                    ],
                                ],
                                'LOOP_N1' => [
                                    Schema::LOOP_FIELD => true,
                                    Schema::SEGMENTS_FIELD => [
                                        'REF' => [Schema::REQUIRED_FIELD => false, Schema::MULTIPLE_FIELD => true, Schema::DESC_FIELD => 'Reference Identification'],
                                    ],
                                ],
                                'LOOP_G39' => [
                                    Schema::LOOP_FIELD => true,
                                    Schema::SEGMENTS_FIELD => [
                                        'G39' => [Schema::REQUIRED_FIELD => false, Schema::MULTIPLE_FIELD => false, Schema::DESC_FIELD => 'Item Characteristics Vendor\'s Selling Unit'],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
                self::SUMMARY => [
                    Schema::SEGMENTS_FIELD => [
                        'CTT' => [Schema::REQUIRED_FIELD => false, Schema::MULTIPLE_FIELD => false, Schema::DESC_FIELD => 'Transaction Totals'],
                        'SE' => [Schema::REQUIRED_FIELD => true, Schema::MULTIPLE_FIELD => false, Schema::DESC_FIELD => 'Transaction Set Trailer'],
                    ],
                ],
            ],
        ],
        self::FILE_FOOTER => [
            Schema::SEGMENTS_FIELD => [
                'GE' => [Schema::REQUIRED_FIELD => true, Schema::MULTIPLE_FIELD => false, Schema::DESC_FIELD => 'Functional Group Trailer'],
                'IEA' => [Schema::REQUIRED_FIELD => true, Schema::MULTIPLE_FIELD => false, Schema::DESC_FIELD => 'Interchange Control Trailer'],
            ],
        ],
    ];

    abstract protected function createOgFileObject(array $ogArray): OrderGuideFile;

    /**
     * @param array            $normalizedItems
     * @param array            $errors
     * @param LocationVendor[] $locationVendors
     *
     * @return OrderGuideFile[]
     */
    protected function getOgFilesFromArray(array $normalizedItems, array $locationVendors, array $errors): array
    {
        $ogFiles = [];

        foreach ($normalizedItems[self::HEADING] as $og) {
            $ogFiles[] = $this->createOgFileObject($og);
        }

        return $ogFiles;
    }

    /**
     * Check that file contains all required fields.
     *
     * @param File                  $invoiceFile
     * @param LocationVendor[]|null $locationVendors
     *
     * @return bool
     */
    public function isFileProcessable(File $invoiceFile, array $locationVendors): bool
    {
        Assert::greaterThan(count($locationVendors), 0, 'Pass at least one location vendor!');
        Assert::allIsInstanceOf($locationVendors, LocationVendor::class, 'At least one of passed Location Vendors is of wrong type.');

        $vendor = ($locationVendor = $locationVendors[0])->getVendor();
        $this->om->refresh($vendor); //refresh it for sure
        foreach ($locationVendors as $locationVendor) {
            Assert::eq($vendor->getId(), $locationVendor->getVendor()->getId(), 'You passed different vendors!');
        }

        if (!$this->supports($locationVendor->getOrderGuideImportSetup())) {
            return false;
        }

        return null !== $this->parser = $this->determineParser($invoiceFile);
    }

    private function determineParser(File $invoiceFile): ?OrderGuideParserInterface
    {
        foreach ($this->parsers as $parser) {
            if (!$parser instanceof X12OgParser) {
                continue;
            }

            if (empty($fileStructure = $this->getFileHeader($invoiceFile, $parser))) {
                continue;
            }

            $mandatorySegments = Schema::getRequiredSegments($this->fileStructure, Schema::REQUIRED_FIELD);
            $iterator = new \RecursiveArrayIterator($mandatorySegments);
            $recursive = new \RecursiveIteratorIterator($iterator, \RecursiveIteratorIterator::SELF_FIRST);

            foreach ($recursive as $segment => $value) {
                if ($this->isRequiredSegmentEmpty($fileStructure, $segment, $value)) {
                    continue 2;
                }
            }

            return $parser;
        }

        return null;
    }

    private function isRequiredSegmentEmpty(array $fileStructure, $segmentName, $segmentValue): bool
    {
        return isset($segmentValue[Schema::REQUIRED_FIELD]) && empty(Normalizer::findKeyRecursive($fileStructure, $segmentName));
    }

    protected function preContentNormalization(): void
    {
        return;
    }

    /**
     * We don't need normalize content because schemaParser generates array strictly according to passed schema.
     *
     * @param array $rawContentArray
     *
     * @return array of the following structure [normalizedItems, errors]
     */
    protected function contentNormalization(array $rawContentArray): array
    {
        return [$rawContentArray, []];
    }
}
