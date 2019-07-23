<?php

declare(strict_types=1);

namespace App\Utils\EDI\OrderGuideImportService\OrderGuideProcessorService\Serializer\Handler;

use App\Utils\Tools\Normalizer;
use JMS\Serializer\Context;
use JMS\Serializer\GraphNavigator;
use JMS\Serializer\Handler\SubscribingHandlerInterface;
use JMS\Serializer\JsonDeserializationVisitor;

final class TrimmedStringSerializeHandler implements SubscribingHandlerInterface
{
    public static function getSubscribingMethods(): array
    {
        return [
            [
                'direction' => GraphNavigator::DIRECTION_DESERIALIZATION,
                'format' => 'json',
                'type' => 'trim_string',
                'method' => 'normalizeString',
            ],
        ];
    }

    /**
     * @param JsonDeserializationVisitor $visitor
     * @param string                     $string
     * @param array                      $type
     * @param Context                    $context
     *
     * @return string
     */
    public function normalizeString(JsonDeserializationVisitor $visitor, string $string, array $type, Context $context): string
    {
        return Normalizer::normalizeString($string);
    }
}
