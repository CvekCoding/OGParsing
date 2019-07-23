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

namespace App\Utils\EDI\OrderGuideImportService\MessageHandler;

use App\Utils\EDI\MessageHandler\AbstractNewVendorEdiDocumentHandler;
use App\Utils\EDI\OrderGuideImportService\Entity\NewOrderGuide;
use App\Utils\EntityService\OrderGuideService;
use App\Utils\Security\Security;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

final class NewOrderGuideHandler extends AbstractNewVendorEdiDocumentHandler
{
    public function __construct(OrderGuideService $orderGuideService,
                                EntityManagerInterface $objectManager,
                                LoggerInterface $logger,
                                Security $security)
    {
        parent::__construct($orderGuideService, $objectManager, $logger, $security);
    }

    public function __invoke(NewOrderGuide $newEdiObject)
    {
        try {
            $this->createVendorEdiDocument($newEdiObject);

        } catch (\Throwable $e) {
            $this->logger->alert('Order Guide for location vendor ids '
                .\implode(', ', $newEdiObject->getLocationVendorsIds())
                ." was not imported. Error: {$e->getMessage()}");
        }
    }
}
