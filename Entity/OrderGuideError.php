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

use App\Utils\EDI\Entity\EdiError;
use JMS\Serializer\Annotation as Serializer;
use Symfony\Component\Serializer\Annotation\Groups;

final class OrderGuideError extends EdiError
{
    public const ITEM_NOT_FOUND = 'ITEM_NOT_FOUND';
    public const PRICE_CHANGE_EXCEEDED = 'PRICE_CHANGE_EXCEEDED';

    /**
     * @var string
     *
     * @Serializer\SerializedName("Item No")
     * @Serializer\Type("string")
     *
     * @Groups("toReturn")
     */
    private $itemNo;

    /**
     * @return string
     */
    public function getItemNo(): ?string
    {
        return $this->itemNo;
    }

    /**
     * @param string $itemNo
     */
    public function setItemNo(string $itemNo): void
    {
        $this->itemNo = $itemNo;
    }
}
