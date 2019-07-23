<?php

declare(strict_types=1);

namespace App\Utils\EDI\OrderGuideImportService\Entity\OrderGuideImportSetup;

abstract class AbstractX12OrderGuideImportSetup extends AbstractOrderGuideImportSetup
{
    /** @var string */
    private $customerNumber;

    /**
     * @return string|null
     */
    public function getCustomerNumber(): ?string
    {
        return $this->customerNumber;
    }

    /**
     * @param string $customerNumber
     */
    public function setCustomerNumber(string $customerNumber): void
    {
        $this->customerNumber = $customerNumber;
    }
}