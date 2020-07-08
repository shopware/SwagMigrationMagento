<?php declare(strict_types=1);
/*
 * (c) shopware AG <info@shopware.com>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Swag\MigrationMagento\Profile\Magento20\Converter;

use Swag\MigrationMagento\Profile\Magento\Converter\NewsletterRecipientConverter;
use Swag\MigrationMagento\Profile\Magento\DataSelection\DataSet\NewsletterRecipientDataSet;
use Swag\MigrationMagento\Profile\Magento20\Magento20Profile;
use SwagMigrationAssistant\Migration\MigrationContextInterface;

class Magento20NewsletterRecipientConverter extends NewsletterRecipientConverter
{
    public function supports(MigrationContextInterface $migrationContext): bool
    {
        return $migrationContext->getProfile()->getName() === Magento20Profile::PROFILE_NAME
             && $migrationContext->getDataSet()::getEntity() === NewsletterRecipientDataSet::getEntity();
    }
}
