<?php

declare(strict_types=1);

namespace App\Utils\EDI\OrderGuideImportService\OrderGuideProcessorService\Serializer\Handler;

use App\DBAL\EnumVendorProductPackType;
use JMS\Serializer\Context;
use JMS\Serializer\GraphNavigator;
use JMS\Serializer\Handler\SubscribingHandlerInterface;
use JMS\Serializer\JsonDeserializationVisitor;

final class VendorPackTypeSerializeHandler implements SubscribingHandlerInterface
{
    public static function getSubscribingMethods(): array
    {
        return [
            [
                'direction' => GraphNavigator::DIRECTION_DESERIALIZATION,
                'format' => 'json',
                'type' => 'VendorPackType',
                'method' => 'normalizeVendorPackType',
            ],
        ];
    }

    /**
     * @param JsonDeserializationVisitor $visitor
     * @param string                     $vendorPackType
     * @param array                      $type
     * @param Context                    $context
     *
     * @return EnumVendorProductPackType
     */
    public function normalizeVendorPackType(JsonDeserializationVisitor $visitor, string $vendorPackType, array $type, Context $context): string
    {
        return EnumVendorProductPackType::normalizePackType($vendorPackType);
    }
}
