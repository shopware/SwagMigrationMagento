<?php declare(strict_types=1);

namespace Swag\MigrationMagento\Profile\Magento\DataSelection\DataSet;

use Swag\MigrationMagento\Profile\Magento\DataSelection\DefaultEntities;
use Swag\MigrationMagento\Profile\Magento\Magento19Profile;
use SwagMigrationAssistant\Migration\DataSelection\DataSet\DataSet;
use SwagMigrationAssistant\Migration\MigrationContextInterface;

class NotAssociatedMediaDataSet extends DataSet
{
    public static function getEntity(): string
    {
        return DefaultEntities::NOT_ASSOCIATED_MEDIA;
    }

    public function supports(MigrationContextInterface $migrationContext): bool
    {
        return $migrationContext->getProfile() instanceof Magento19Profile;
    }

    public function getMediaUuids(array $converted): ?array
    {
        $mediaUuids = [];
        foreach ($converted as $data) {
            $mediaUuids[] = $data['id'];
        }

        return $mediaUuids;
    }
}
