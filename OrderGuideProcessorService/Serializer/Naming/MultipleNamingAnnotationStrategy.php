<?php
declare(strict_types=1);

namespace App\Utils\EDI\OrderGuideImportService\OrderGuideProcessorService\Serializer\Naming;

use JMS\Serializer\Metadata\PropertyMetadata;
use JMS\Serializer\Naming\IdenticalPropertyNamingStrategy;
use JMS\Serializer\Naming\PropertyNamingStrategyInterface;

/**
 * Allows to use different names of serialization field.
 * Example of property annotation:
 * SerializedName(
 *     "namingsArray={csv:Item No, edi:ItemNo, xls:VendorGoodId}"
 * ).
 *
 * To start use this naming strategy, instantiate it and do the following:
 *      $multipleNamingStrategy->setNamingKey('csv');
 *      $jmsSerializerBuilder->setPropertyNamingStrategy($multipleNamingStrategy);
 */
final class MultipleNamingAnnotationStrategy implements PropertyNamingStrategyInterface
{
    private $namingKey;
    private $delegate;

    public function __construct()
    {
        $this->delegate = new IdenticalPropertyNamingStrategy();
    }

    public function setNamingKey(?string $namingKey): void
    {
        if (isset($namingKey)) {
            $this->namingKey = $namingKey;
        }
    }

    public function translateName(PropertyMetadata $property): string
    {
        if (null === $serializedName = $property->serializedName) {
            return $this->delegate->translateName($property);
        }

        if (isset($this->namingKey)) {
            preg_match("/\{.*{$this->namingKey}:([^,]*).*\}/", $serializedName, $matches);

            if (2 === count($matches)) {
                return trim($matches[1]);
            }
        }

        return $serializedName;
    }
}
