<?php
declare(strict_types=1);

namespace App\Utils\EDI\OrderGuideImportService\OrderGuideProcessorService\Parser;

use App\Utils\Tools\FileParser\X12Parser as BaseX12Parser;

final class X12OgParser extends BaseX12Parser implements OrderGuideParserInterface
{
    /**
     * @return string
     */
    public static function getAnnotationKey(): ?string
    {
        return null;
    }
}
