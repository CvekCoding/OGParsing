<?php

declare(strict_types=1);

namespace App\Utils\EDI\OrderGuideImportService\OrderGuideProcessorService\Parser;

use App\Utils\Tools\FileParser\CsvParser as BaseCsvParser;

/**
 * Implementation of basic file preparation/parsing methods before it can be processed by particular Processor.
 */
final class CsvOgParser extends BaseCsvParser implements OrderGuideParserInterface
{
    public const MULTIPLE_NAMING_ANNOTATION_KEY = 'csv';

    // Default Processor name
    protected $parserName = 'csv';

    public static function getAnnotationKey(): ?string
    {
        return self::MULTIPLE_NAMING_ANNOTATION_KEY;
    }
}
