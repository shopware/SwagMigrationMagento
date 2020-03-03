<?php declare(strict_types=1);
/*
 * (c) shopware AG <info@shopware.com>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Swag\MigrationMagento\Profile\Magento\Gateway\Local\Reader\Struct;

use Shopware\Core\Framework\Struct\Struct;

class StockConfigurationStruct extends Struct
{
    /**
     * @var int
     */
    private $minPurchase;

    /**
     * @var int
     */
    private $maxPurchase;

    public function __construct(int $minPurchase, int $maxPurchase)
    {
        $this->minPurchase = $minPurchase;
        $this->maxPurchase = $maxPurchase;
    }

    public function getMinPurchase(): int
    {
        return $this->minPurchase;
    }

    public function getMaxPurchase(): int
    {
        return $this->maxPurchase;
    }
}
