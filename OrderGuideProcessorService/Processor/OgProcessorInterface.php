<?php
declare(strict_types=1);

/**
 * Created by PhpStorm.
 * User: Logachev Sergey
 * Date: 5/11/2018
 * Time: 6:44 PM.
 */

namespace App\Utils\EDI\OrderGuideImportService\OrderGuideProcessorService\Processor;

use App\Utils\EDI\OrderGuideImportService\OrderGuideProcessorService\Parser\OrderGuideParserInterface;
use App\Utils\Tools\EDIProcessor\EdiProcessorInterface;

interface OgProcessorInterface extends EdiProcessorInterface
{
    /**
     * For DI - inject all known parsers during initialization.
     *
     * @param OrderGuideParserInterface $parser
     */
    public function addParser(OrderGuideParserInterface $parser): void;

    /**
     * Each processor has to know about itself - does it available to create new items or no.
     *
     * @return bool
     */
    public function isProductCreationPermitted(): bool;
}
