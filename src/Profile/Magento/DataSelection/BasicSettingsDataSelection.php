<?php declare(strict_types=1);

namespace Swag\MigrationMagento\Profile\Magento\DataSelection;

use Swag\MigrationMagento\Profile\Magento\DataSelection\DataSet\CategoryDataSet;
use Swag\MigrationMagento\Profile\Magento\Magento19Profile;
use SwagMigrationAssistant\Migration\DataSelection\DataSelectionInterface;
use SwagMigrationAssistant\Migration\DataSelection\DataSelectionStruct;
use SwagMigrationAssistant\Migration\MigrationContextInterface;
use SwagMigrationAssistant\Profile\Shopware\DataSelection\DataSet\SalesChannelDataSet;

class BasicSettingsDataSelection implements DataSelectionInterface
{
    public const IDENTIFIER = 'basicSettings';

    public function supports(MigrationContextInterface $migrationContext): bool
    {
        return $migrationContext->getProfile() instanceof Magento19Profile;
    }

    public function getData(): DataSelectionStruct
    {
        return new DataSelectionStruct(
            self::IDENTIFIER,
            $this->getEntityNames(),
            $this->getEntityNamesRequiredForCount(),
            'swag-migration.index.selectDataCard.dataSelection.basicSettings',
            -100,
            true,
            DataSelectionStruct::BASIC_DATA_TYPE,
            true
        );
    }

    public function getEntityNames(): array
    {
        return [
            CategoryDataSet::getEntity(),
            SalesChannelDataSet::getEntity(),
        ];
    }

    public function getEntityNamesRequiredForCount(): array
    {
        return $this->getEntityNames();
    }
}
