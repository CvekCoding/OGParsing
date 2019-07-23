<?php

/**
 * Created by PhpStorm.
 * User: sergey
 * Date: 21.02.19
 * Time: 17:26
 */

namespace App\Utils\EDI\OrderGuideImportService\Entity\OrderGuideImportSetup;

abstract class AbstractOrderGuideImportSetup implements OrderGuideImportSetupInterface
{
    public const JSON_NEEDLE = <<< 'NEEDLE'
{
    "pullOrderGuideSetting": {
        "orderGuideImportSetup": {}, 
        "transportSetup": {}
    }
}
NEEDLE;

    public static function getJsonNeedle(): string
    {
        return static::JSON_NEEDLE;
    }
}
