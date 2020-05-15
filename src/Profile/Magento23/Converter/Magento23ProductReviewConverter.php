<?php declare(strict_types=1);
/*
 * (c) shopware AG <info@shopware.com>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Swag\MigrationMagento\Profile\Magento23\Converter;

use Swag\MigrationMagento\Profile\Magento\DataSelection\DataSet\ProductReviewDataSet;
use Swag\MigrationMagento\Profile\Magento2\Converter\Magento2ProductReviewConverter;
use Swag\MigrationMagento\Profile\Magento23\Magento23Profile;
use SwagMigrationAssistant\Migration\MigrationContextInterface;

class Magento23ProductReviewConverter extends Magento2ProductReviewConverter
{
    public function supports(MigrationContextInterface $migrationContext): bool
    {
        return $migrationContext->getProfile()->getName() === Magento23Profile::PROFILE_NAME
             && $migrationContext->getDataSet()::getEntity() === ProductReviewDataSet::getEntity();
    }
}