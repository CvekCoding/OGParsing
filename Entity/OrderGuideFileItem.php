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

use App\DBAL\EnumVendorPriceType;
use App\DBAL\EnumVendorProductPackType;
use App\Entity\Main\Company;
use App\Entity\Main\LocationVendor;
use App\Entity\Main\LocationVendorItem;
use App\Utils\EDI\Entity\VendorItemInterface;
use App\Utils\Tools\Normalizer;
use JMS\Serializer\Annotation as Serializer;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Serializer\Annotation\MaxDepth;
use Webmozart\Assert\Assert;

final class OrderGuideFileItem implements VendorItemInterface
{
    /**
     * @var LocationVendorItem[]
     */
    private $locationVendorProducts = [];

    /**
     * @var OrderGuideFile
     *
     * @Serializer\Exclude()
     */
    private $orderGuideFile;

    /**
     * @var string
     *
     * @Serializer\SerializedName(
     *     "namings={csv:Item No}"
     * )
     * @Serializer\Type("trim_string")
     */
    private $itemNo;

    /**
     * @var string
     *
     * @Serializer\SerializedName(
     *     "namings={csv:Unit, x12:Unit}")
     * )
     * @Serializer\Accessor(setter="setPackType")
     * @Serializer\Type("VendorPackType")
     */
    private $packType;

    /**
     * @var string
     *
     * @Serializer\SerializedName(
     *     "namings={csv:Pack Size}"
     * )
     * @Serializer\Type("PackSize")
     */
    private $packSize;

    /**
     * @var string
     *
     * @Serializer\SerializedName("Description")
     * @Serializer\Type("trim_string")
     */
    private $description;

    /**
     * @var string
     *
     * @Serializer\SerializedName("Brand")
     * @Serializer\Type("trim_string")
     */
    private $brand;

    /**
     * @var float
     *
     * @Serializer\SerializedName("Unit Price Per Case")
     * @Serializer\Type("float")
     */
    private $pricePerCase;

    /**
     * @var float
     *
     * @Serializer\SerializedName("Unit Price Per Packsize Unit")
     * @Serializer\Type("float")
     */
    private $pricePerPound;

    /**
     * @var float
     *
     * @Serializer\SerializedName("Price Per Each")
     * @Serializer\Type("float")
     */
    private $pricePerEach;

    /**
     * @var string|null
     *
     * @Serializer\Type("trim_string")
     */
    private $barcode;

    /**
     * @var \DateTime
     */
    private $priceDate;

    /**
     * @var bool
     */
    private $discontinued = false;

    /**
     * @var OrderGuideError[]
     * @MaxDepth(2)
     */
    private $errors;

    /**
     * OrderGuideFileItem constructor.
     *
     * @param string $itemNo
     * @param string $unitType
     */
    public function __construct(string $itemNo, string $unitType = null)
    {
        $this->itemNo = $itemNo;

        if (isset($unitType)) {
            $this->packType = EnumVendorProductPackType::normalizePackType($unitType);
        }
    }

    /**
     * @return string|null
     */
    public function getVendorItemId(): string
    {
        return $this->itemNo;
    }

    /**
     * @param string $itemNo
     *
     * @return OrderGuideFileItem
     */
    public function setItemNo(string $itemNo): self
    {
        $this->itemNo = $itemNo;

        return $this;
    }

    /**
     * @return string|null
     */
    public function getPackType(): string
    {
        return $this->packType;
    }

    /**
     * @param string $packType
     *
     * @return OrderGuideFileItem
     */
    public function setPackType(string $packType): self
    {
        $this->packType = EnumVendorProductPackType::normalizePackType($packType);

        return $this;
    }

    /**
     * @return string
     */
    public function getPackSize(): ?string
    {
        // If it's an Each type - return Unit by default
        if (empty($this->packSize) && (EnumVendorProductPackType::EACH === $this->getPackType())) {
            return 'Unit';
        }

        return $this->packSize;
    }

    /**
     * @param string|null $packSize
     *
     * @return OrderGuideFileItem
     */
    public function setPackSize(?string $packSize): self
    {
        $this->packSize = $packSize;

        return $this;
    }

    /**
     * @return string|null
     */
    public function getDescription(): ?string
    {
        return $this->description;
    }

    /**
     * @param string|null $description
     *
     * @return OrderGuideFileItem
     */
    public function setDescription(?string $description): self
    {
        $this->description = $description;

        return $this;
    }

    /**
     * @return string|null
     */
    public function getBrand(): ?string
    {
        return $this->brand;
    }

    /**
     * @param string|null $brand
     *
     * @return OrderGuideFileItem
     */
    public function setBrand(?string $brand): self
    {
        $this->brand = $brand;

        return $this;
    }

    /**
     * @return float|null
     */
    public function getPricePerCase(): ?float
    {
        return $this->pricePerCase;
    }

    /**
     * @param string $pricePerCase
     *
     * @return OrderGuideFileItem
     */
    public function setPricePerCase($pricePerCase): self
    {
        $this->pricePerCase = Normalizer::anyToFloatOrNull($pricePerCase);

        return $this;
    }

    /**
     * @return float|null
     */
    public function getPricePerPound(): ?float
    {
        return $this->pricePerPound;
    }

    /**
     * @param string $pricePerPound
     *
     * @return OrderGuideFileItem
     */
    public function setPricePerPound($pricePerPound): self
    {
        $this->pricePerPound = Normalizer::anyToFloatOrNull($pricePerPound);

        return $this;
    }

    /**
     * @Groups("toReturn")
     *
     * @return OrderGuideError[]|null
     */
    public function getErrors(): ?array
    {
        return $this->errors;
    }

    /**
     * @param OrderGuideError|null $error
     *
     * @return OrderGuideFileItem
     */
    public function addError(?OrderGuideError $error): self
    {
        if (isset($error)) {
            $this->errors[] = $error;
        }

        return $this;
    }

    /**
     * @param OrderGuideFile $orderGuideFile
     *
     * @return OrderGuideFileItem
     */
    public function setOrderGuideFile(OrderGuideFile $orderGuideFile): self
    {
        $this->orderGuideFile = $orderGuideFile;

        return $this;
    }

    /**
     * @Groups("toReturn")
     *
     * @return float|null
     */
    public function getPrice(): ?float
    {
        return empty($this->pricePerCase) ? $this->pricePerPound : $this->pricePerCase;
    }

    /**
     * @Groups("toReturn")
     *
     * @return string
     */
    public function getPriceTypeStr(): string
    {
        return $this->getPriceType();
    }

    /**
     * @Groups("toReturn")
     *
     * @return string
     */
    public function getPriceType(): string
    {
        return !empty($this->pricePerCase)
            ? EnumVendorPriceType::CASE
            : EnumVendorPriceType::POUND;
    }

    /**
     * {@inheritdoc}
     */
    public function isPricePerPound(): bool
    {
        return EnumVendorPriceType::POUND === $this->getPriceType();
    }

    /**
     * @return string|null
     */
    public function getBarcode(): ?string
    {
        return $this->barcode;
    }

    /**
     * @param string|null $barcode
     *
     * @return OrderGuideFileItem
     */
    public function setBarcode(?string $barcode): self
    {
        $this->barcode = $barcode;

        return $this;
    }

    /**
     * @Groups("toReturn")
     *
     * @return \DateTime|null
     */
    public function getPriceDate(): ?\DateTime
    {
        return $this->priceDate;
    }

    /**
     * @param \DateTime|null $priceDate
     *
     * @return OrderGuideFileItem
     */
    public function setPriceDate(?\DateTime $priceDate): self
    {
        $this->priceDate = $priceDate;

        return $this;
    }

    /**
     * @return float|null
     */
    public function getPricePerEach(): ?float
    {
        return $this->pricePerEach;
    }

    /**
     * @param string|null $pricePerEach
     *
     * @return OrderGuideFileItem
     */
    public function setPricePerEach($pricePerEach): self
    {
        $this->pricePerEach = Normalizer::anyToFloatOrNull($pricePerEach);

        return $this;
    }

    /**
     * @param LocationVendor|null $locationVendor Filter by passed LocationVendor
     *
     * @return LocationVendorItem[]
     */
    public function getLocationVendorItems(LocationVendor $locationVendor = null): array
    {
        if (isset($locationVendor)) {
            $locationVendorProducts = [];
            foreach ($this->locationVendorProducts as $locationVendorProduct) {
                if ($locationVendorProduct->getLocationVendor()->getId() === $locationVendor->getId()) {
                    $locationVendorProducts[] = $locationVendorProduct;
                }
            }

            return $locationVendorProducts;
        }

        return $this->locationVendorProducts;
    }

    /**
     * @param LocationVendorItem[] $locationVendorProducts
     *
     * @return OrderGuideFileItem
     */
    public function setLocationVendorProducts(array $locationVendorProducts): self
    {
        Assert::allIsInstanceOf($locationVendorProducts, LocationVendorItem::class, 'OG File Item: Passed items must be of LocationVendorProduct class.');

        $this->locationVendorProducts = $locationVendorProducts;

        return $this;
    }

    /**
     * @param LocationVendorItem $locationVendorProduct
     *
     * @return OrderGuideFileItem
     */
    public function addLocationVendorProduct(LocationVendorItem $locationVendorProduct): self
    {
        if (!Normalizer::isEntityInArray($locationVendorProduct, $this->locationVendorProducts)) {
            $this->locationVendorProducts[] = $locationVendorProduct;
        }

        return $this;
    }

    /**
     * @return bool
     */
    public function isDiscontinued(): bool
    {
        return $this->discontinued;
    }

    /**
     * @param bool $discontinued
     *
     * @return OrderGuideFileItem
     */
    public function setDiscontinued(bool $discontinued): OrderGuideFileItem
    {
        $this->discontinued = $discontinued;

        return $this;
    }

    /** @return bool */
    public function isManual(): bool
    {
        return false;
    }

    /** @return bool */
    public function isImported(): bool
    {
        return true;
    }

    /** @return Company */
    public function getCompany(): ?Company
    {
        return null;
    }
}
