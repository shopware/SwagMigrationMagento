<?php declare(strict_types=1);

namespace Swag\MigrationMagento\Profile\Magento\DataSelection;

use Swag\MigrationMagento\Profile\Magento\DataSelection\DataSet\NewsletterRecipientDataSet;
use Swag\MigrationMagento\Profile\Magento\Magento19Profile;
use SwagMigrationAssistant\Migration\DataSelection\DataSelectionInterface;
use SwagMigrationAssistant\Migration\DataSelection\DataSelectionStruct;
use SwagMigrationAssistant\Migration\MigrationContextInterface;

class NewsletterRecipientDataSelection implements DataSelectionInterface
{
    public const IDENTIFIER = 'newsletterRecipient';

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
            'swag-migration.index.selectDataCard.dataSelection.newsletterRecipient',
            400,
            false
        );
    }

    public function getEntityNames(): array
    {
        return [
            NewsletterRecipientDataSet::getEntity(),
        ];
    }

    public function getEntityNamesRequiredForCount(): array
    {
        return $this->getEntityNames();
    }
}
