<?php declare(strict_types=1);

namespace Swag\MigrationMagento\Profile\Magento\DataSelection;

use Swag\MigrationMagento\Profile\Magento\DataSelection\DataSet\MediaDataSet;
use Swag\MigrationMagento\Profile\Magento\DataSelection\DataSet\NotAssociatedMediaDataSet;
use Swag\MigrationMagento\Profile\Magento\Magento19Profile;
use SwagMigrationAssistant\Migration\DataSelection\DataSelectionInterface;
use SwagMigrationAssistant\Migration\DataSelection\DataSelectionStruct;
use SwagMigrationAssistant\Migration\MigrationContextInterface;

class MediaDataSelection implements DataSelectionInterface
{
    public const IDENTIFIER = 'media';

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
            'swag-migration.index.selectDataCard.dataSelection.media',
            200,
            true
        );
    }

    public function getEntityNames(): array
    {
        return [
            MediaDataSet::getEntity(),
            NotAssociatedMediaDataSet::getEntity(),
        ];
    }

    public function getEntityNamesRequiredForCount(): array
    {
        return $this->getEntityNames();
    }
}
