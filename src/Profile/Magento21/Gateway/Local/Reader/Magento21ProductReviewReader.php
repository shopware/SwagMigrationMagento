<?php declare(strict_types=1);
/*
 * (c) shopware AG <info@shopware.com>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Swag\MigrationMagento\Profile\Magento21\Gateway\Local\Reader;

use Swag\MigrationMagento\Profile\Magento2\Gateway\Local\Reader\Magento2ProductReviewReader;
use Swag\MigrationMagento\Profile\Magento21\Gateway\Local\Magento21LocalGateway;
use Swag\MigrationMagento\Profile\Magento21\Magento21Profile;
use SwagMigrationAssistant\Migration\DataSelection\DefaultEntities;
use SwagMigrationAssistant\Migration\MigrationContextInterface;

class Magento21ProductReviewReader extends Magento2ProductReviewReader
{
    public function supports(MigrationContextInterface $migrationContext): bool
    {
        return $migrationContext->getProfile() instanceof Magento21Profile
            && $migrationContext->getGateway()->getName() === Magento21LocalGateway::GATEWAY_NAME
            && $migrationContext->getDataSet()::getEntity() === DefaultEntities::PRODUCT_REVIEW;
    }

    public function supportsTotal(MigrationContextInterface $migrationContext): bool
    {
        return $migrationContext->getProfile() instanceof Magento21Profile
            && $migrationContext->getGateway()->getName() === Magento21LocalGateway::GATEWAY_NAME;
    }
}
