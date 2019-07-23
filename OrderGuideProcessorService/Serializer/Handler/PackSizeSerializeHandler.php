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

namespace App\Utils\EDI\OrderGuideImportService\OrderGuideProcessorService\Serializer\Handler;

use App\Utils\EntityService\MeasureService;
use JMS\Serializer\Context;
use JMS\Serializer\GraphNavigator;
use JMS\Serializer\Handler\SubscribingHandlerInterface;
use JMS\Serializer\JsonDeserializationVisitor;

final class PackSizeSerializeHandler implements SubscribingHandlerInterface
{
    private $measureManager;

    /**
     * PackSizeSerializeHandler constructor.
     *
     * @param MeasureService $measureUnitsManager
     */
    public function __construct(MeasureService $measureUnitsManager)
    {
        $this->measureManager = $measureUnitsManager;
    }

    public static function getSubscribingMethods()
    {
        return [
            [
                'direction' => GraphNavigator::DIRECTION_DESERIALIZATION,
                'format' => 'json',
                'type' => 'PackSize',
                'method' => 'normalizePackSize',
            ],
        ];
    }

    /**
     * @param JsonDeserializationVisitor $visitor
     * @param string                     $packSize
     * @param array                      $type
     * @param Context                    $context
     *
     * @return string
     *
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    public function normalizePackSize(JsonDeserializationVisitor $visitor, string $packSize, array $type, Context $context): string
    {
        return $this->measureManager->normalizeMeasureStr($packSize);
    }
}
