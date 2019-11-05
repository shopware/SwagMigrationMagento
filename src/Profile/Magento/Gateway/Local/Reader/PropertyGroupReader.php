<?php declare(strict_types=1);

namespace Swag\MigrationMagento\Profile\Magento\Gateway\Local\Reader;

use Swag\MigrationMagento\Profile\Magento\Magento19Profile;
use SwagMigrationAssistant\Migration\DataSelection\DefaultEntities;
use SwagMigrationAssistant\Migration\MigrationContextInterface;

class PropertyGroupReader extends AbstractReader implements LocalReaderInterface
{
    public function supports(MigrationContextInterface $migrationContext): bool
    {
        return $migrationContext->getProfile() instanceof Magento19Profile
        && $migrationContext->getDataSet()::getEntity() === DefaultEntities::PROPERTY_GROUP;
    }

    public function read(MigrationContextInterface $migrationContext, array $params = []): array
    {
        $this->setConnection($migrationContext);

        $fetchedPropertyGroups = $this->fetchPropertyGroups($migrationContext);

        $groups = [];
        foreach ($fetchedPropertyGroups as $group) {
            foreach ($group as $option) {
                $groupId = $option['groupId'];

                if (!isset($groups[$groupId])) {
                    $groups[$groupId] = [
                        'id' => $groupId,
                        'name' => $option['groupName'],
                        'options' => [],
                    ];
                }

                $groups[$groupId]['options'][] = [
                    'id' => $option['optionId'],
                    'name' => $option['optionValue'],
                ];
            }
        }

        return $groups;
    }

    public function fetchPropertyGroups(MigrationContextInterface $migrationContext): array
    {
        $query = $this->connection->createQueryBuilder();

        $query->addSelect('eav.attribute_id as identifier');
        $query->addSelect('eav.attribute_id as groupId');
        $query->addSelect('eav.attribute_code as groupName');
        $query->from('eav_attribute', 'eav');

        $query->innerJoin('eav', 'catalog_eav_attribute', 'eav_settings', 'eav_settings.attribute_id = eav.attribute_id AND eav_settings.is_filterable = 1');

        $query->innerJoin('eav', 'eav_attribute_option', 'options', 'options.attribute_id = eav.attribute_id');

        $query->addSelect('option_value.option_id as optionId');
        $query->addSelect('option_value.value as optionValue');
        $query->innerJoin('eav', 'eav_attribute_option_value', 'option_value', 'option_value.option_id = options.option_id AND option_value.store_id = 0');

        $query->where('eav.is_user_defined = 1');

        $query->setFirstResult($migrationContext->getOffset());
        $query->setMaxResults($migrationContext->getLimit());

        return $query->execute()->fetchAll(\PDO::FETCH_GROUP | \PDO::FETCH_ASSOC);
    }
}
