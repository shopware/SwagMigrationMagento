<?php declare(strict_types=1);

namespace Swag\MigrationMagento\Profile\Magento\DataSelection\DataSet;

use Swag\MigrationMagento\Profile\Magento\Magento19Profile;
use SwagMigrationAssistant\Migration\DataSelection\DataSet\CountingInformationStruct;
use SwagMigrationAssistant\Migration\DataSelection\DataSet\CountingQueryStruct;
use SwagMigrationAssistant\Migration\DataSelection\DataSet\DataSet;
use SwagMigrationAssistant\Migration\DataSelection\DefaultEntities;
use SwagMigrationAssistant\Migration\MigrationContextInterface;

class CategoryDataSet extends DataSet
{
    public static function getEntity(): string
    {
        return DefaultEntities::CATEGORY;
    }

    public function supports(MigrationContextInterface $migrationContext): bool
    {
        return $migrationContext->getProfile() instanceof Magento19Profile;
    }

    public function getCountingInformation(): ?CountingInformationStruct
    {
        $information = new CountingInformationStruct(self::getEntity());
        $information->addQueryStruct(new CountingQueryStruct('catalog_category_entity'));

        return $information;
    }

    public function getMediaUuids(array $converted): ?array
    {
        $mediaUuids = [];
        foreach ($converted as $data) {
            if (!isset($data['media']['id'])) {
                continue;
            }

            $mediaUuids[] = $data['media']['id'];
        }

        return $mediaUuids;
    }
}
