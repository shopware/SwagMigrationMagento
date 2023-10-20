<?php declare(strict_types=1);
/*
 * (c) shopware AG <info@shopware.com>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Swag\MigrationMagento\Migration\Mapping;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use SwagMigrationAssistant\Migration\Mapping\MappingServiceInterface;

#[Package('services-settings')]
interface MagentoMappingServiceInterface extends MappingServiceInterface
{
    public function getMagentoCountryUuid(string $iso, string $connectionId, Context $context): ?string;

    public function getTransactionStateUuid(string $state, Context $context): ?string;

    public function getTaxRate(string $uuid, Context $context): ?float;
}
