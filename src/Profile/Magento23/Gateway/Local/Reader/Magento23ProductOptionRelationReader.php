<?php declare(strict_types=1);
/*
 * (c) shopware AG <info@shopware.com>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Swag\MigrationMagento\Profile\Magento23\Gateway\Local\Reader;

use Swag\MigrationMagento\Profile\Magento\DataSelection\DataSet\ProductOptionRelationDataSet;
use Swag\MigrationMagento\Profile\Magento2\Gateway\Local\Reader\Magento2ProductOptionRelationReader;
use Swag\MigrationMagento\Profile\Magento23\Gateway\Local\Magento23LocalGateway;
use Swag\MigrationMagento\Profile\Magento23\Magento23Profile;
use SwagMigrationAssistant\Migration\MigrationContextInterface;

class Magento23ProductOptionRelationReader extends Magento2ProductOptionRelationReader
{
    public function supports(MigrationContextInterface $migrationContext): bool
    {
        return $migrationContext->getProfile() instanceof Magento23Profile
            && $migrationContext->getGateway()->getName() === Magento23LocalGateway::GATEWAY_NAME
            && $this->getDataSetEntity($migrationContext) === ProductOptionRelationDataSet::getEntity();
    }

    public function supportsTotal(MigrationContextInterface $migrationContext): bool
    {
        return $migrationContext->getProfile() instanceof Magento23Profile
            && $migrationContext->getGateway()->getName() === Magento23LocalGateway::GATEWAY_NAME;
    }
}
