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

namespace App\Utils\EDI\OrderGuideImportService\Entity;

use App\Entity\Main\Location;
use App\Entity\Main\LocationVendor;
use App\Entity\Main\Vendor;
use App\Utils\EDI\Entity\EdiObject;
use App\Utils\EDI\OrderGuideImportService\OrderGuideProcessorService\Processor\OgProcessorInterface;
use App\Utils\Exception\InconsistencyException;
use Symfony\Component\Serializer\Annotation\Groups;
use Webmozart\Assert\Assert;

final class OrderGuideFile extends EdiObject
{
    /**
     * @var OgProcessorInterface
     *
     * @Groups("toReturn")
     */
    private $ogProcessor;
    private $locationVendors;

    /**
     * OrderGuideFile constructor.
     *
     * @param OgProcessorInterface $processingService
     * @param LocationVendor[]     $locationVendors
     */
    public function __construct(OgProcessorInterface $processingService = null, array $locationVendors = [])
    {
        $this->ogProcessor = $processingService;
        $this->locationVendors = $locationVendors;
    }

    /**
     * @return array
     *
     * @Groups("toReturn")
     *
     * @throws InconsistencyException
     */
    public function getSerializedVendor(): array
    {
        if ($vendor = $this->getVendor()) {
            return $vendor->toArray();
        }

        return [];
    }

    /**
     * @return Vendor|null
     *
     * @throws InconsistencyException
     */
    private function getVendor(): ?Vendor
    {
        $vendor = null;
        foreach ($this->locationVendors as $locationVendor) {
            $vendor = $vendor ?? $locationVendor->getVendor();

            if ($vendor !== $locationVendor->getVendor()) {
                throw new InconsistencyException('Passed location vendors related to different vendors.');
            }
        }

        return $vendor;
    }

    /**
     * @return array
     * @Groups("toReturn")
     */
    public function getSerializedLocations(): array
    {
        $locations = [];
        foreach ($this->getLocations() as $location) {
            $locations[] = $location->toArray();
        }

        return $locations;
    }

    /**
     * @return Location[]
     */
    private function getLocations(): array
    {
        $locations = [];
        foreach ($this->locationVendors as $locationVendor) {
            $locations[] = $locationVendor->getLocation();
        }

        return $locations;
    }

    /**
     * @param OrderGuideFileItem $item
     *
     * @return bool
     */
    public function addItem(OrderGuideFileItem $item): bool
    {
        Assert::notEmpty($item->getVendorItemId(), 'Item No is empty for product name: '.$item->getDescription());
        Assert::notEmpty($item->getPackType(), 'Unit type is empty for product item no: '.$item->getVendorItemId());

        if (!\in_array($item, $this->items, true)) {
            $this->items[] = $item;

            return true;
        }

        return false;
    }

    /**
     * @return OgProcessorInterface
     */
    public function getOgProcessor(): OgProcessorInterface
    {
        return $this->ogProcessor;
    }

    /**
     * @param LocationVendor[] $locationVendors
     */
    public function setLocationVendors(array $locationVendors): void
    {
        $this->locationVendors = $locationVendors;
    }

    /**
     * @param OgProcessorInterface $ogProcessor
     */
    public function setOgProcessor(OgProcessorInterface $ogProcessor): void
    {
        $this->ogProcessor = $ogProcessor;
    }

    /**
     * @return LocationVendor[]|array
     */
    public function getLocationVendors(): array
    {
        return $this->locationVendors;
    }
}
