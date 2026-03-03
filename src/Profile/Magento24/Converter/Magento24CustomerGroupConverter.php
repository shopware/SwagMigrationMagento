<?php declare(strict_types=1);
/*
 * (c) shopware AG <info@shopware.com>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Swag\MigrationMagento\Profile\Magento24\Converter;

use Shopware\Core\Framework\Log\Package;
use Swag\MigrationMagento\Profile\Magento\Converter\CustomerGroupConverter;
use Swag\MigrationMagento\Profile\Magento\DataSelection\DataSet\CustomerGroupDataSet;
use Swag\MigrationMagento\Profile\Magento24\Magento24Profile;
use SwagMigrationAssistant\Migration\MigrationContextInterface;

#[Package('fundamentals@after-sales')]
class Magento24CustomerGroupConverter extends CustomerGroupConverter
{
    public function supports(MigrationContextInterface $migrationContext): bool
    {
        return $migrationContext->getProfile() instanceof Magento24Profile
            && $this->getDataSetEntity($migrationContext) === CustomerGroupDataSet::getEntity();
    }
}
