<?php
declare(strict_types=1);

namespace App\Utils\EDI\OrderGuideImportService\OrderGuideProcessorService\Parser;

use App\Utils\Tools\FileParser\FileParserInterface;

interface OrderGuideParserInterface extends FileParserInterface
{
    /**
     * If parser uses serializer, we have to know its annotation key.
     *
     * @return string
     */
    public static function getAnnotationKey(): ?string;
}
