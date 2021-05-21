<?php declare(strict_types=1);
/*
 * (c) shopware AG <info@shopware.com>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Swag\MigrationMagento\Profile\Magento\DataSelection;

use Swag\MigrationMagento\Profile\Magento\DataSelection\DataSet\CrossSellingDataSet;
use Swag\MigrationMagento\Profile\Magento\DataSelection\DataSet\ManufacturerDataSet;
use Swag\MigrationMagento\Profile\Magento\DataSelection\DataSet\ProductChildMultiSelectPropertyRelationDataSet;
use Swag\MigrationMagento\Profile\Magento\DataSelection\DataSet\ProductChildMultiSelectTextPropertyRelationDataSet;
use Swag\MigrationMagento\Profile\Magento\DataSelection\DataSet\ProductChildPropertyRelationDataSet;
use Swag\MigrationMagento\Profile\Magento\DataSelection\DataSet\ProductCustomFieldDataSet;
use Swag\MigrationMagento\Profile\Magento\DataSelection\DataSet\ProductDataSet;
use Swag\MigrationMagento\Profile\Magento\DataSelection\DataSet\ProductMultiSelectPropertyRelationDataSet;
use Swag\MigrationMagento\Profile\Magento\DataSelection\DataSet\ProductMultiSelectTextPropertyRelationDataSet;
use Swag\MigrationMagento\Profile\Magento\DataSelection\DataSet\ProductOptionRelationDataSet;
use Swag\MigrationMagento\Profile\Magento\DataSelection\DataSet\ProductPropertyRelationDataSet;
use Swag\MigrationMagento\Profile\Magento\DataSelection\DataSet\PropertyGroupDataSet;
use Swag\MigrationMagento\Profile\Magento\MagentoProfileInterface;
use SwagMigrationAssistant\Migration\DataSelection\DataSelectionInterface;
use SwagMigrationAssistant\Migration\DataSelection\DataSelectionStruct;
use SwagMigrationAssistant\Migration\MigrationContextInterface;

class ProductDataSelection implements DataSelectionInterface
{
    public const IDENTIFIER = 'products';

    public function supports(MigrationContextInterface $migrationContext): bool
    {
        return $migrationContext->getProfile() instanceof MagentoProfileInterface;
    }

    public function getData(): DataSelectionStruct
    {
        return new DataSelectionStruct(
            self::IDENTIFIER,
            $this->getDataSets(),
            $this->getDataSetsRequiredForCount(),
            'swag-migration.index.selectDataCard.dataSelection.products',
            100,
            true
        );
    }

    public function getDataSets(): array
    {
        return [
            new ManufacturerDataSet(),
            new PropertyGroupDataSet(),
            new ProductCustomFieldDataSet(),
            new ProductDataSet(),
            new ProductPropertyRelationDataSet(),
            new ProductChildPropertyRelationDataSet(),
            new ProductMultiSelectPropertyRelationDataSet(),
            new ProductMultiSelectTextPropertyRelationDataSet(),
            new ProductChildMultiSelectPropertyRelationDataSet(),
            new ProductChildMultiSelectTextPropertyRelationDataSet(),
            new ProductOptionRelationDataSet(),
            new CrossSellingDataSet(),
        ];
    }

    public function getDataSetsRequiredForCount(): array
    {
        return [
            new ProductDataSet(),
        ];
    }
}
