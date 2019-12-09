<?php declare(strict_types=1);

namespace Swag\MigrationMagento\Profile\Magento\Gateway\Local\Reader;

use Swag\MigrationMagento\Profile\Magento\Gateway\Local\Magento19LocalGateway;
use Swag\MigrationMagento\Profile\Magento\Magento19Profile;
use SwagMigrationAssistant\Migration\DataSelection\DefaultEntities;
use SwagMigrationAssistant\Migration\MigrationContextInterface;
use SwagMigrationAssistant\Migration\TotalStruct;

class PropertyGroupReader extends AbstractReader
{
    public function supports(MigrationContextInterface $migrationContext): bool
    {
        return $migrationContext->getProfile() instanceof Magento19Profile
            && $migrationContext->getGateway()->getName() === Magento19LocalGateway::GATEWAY_NAME
            && $migrationContext->getDataSet()::getEntity() === DefaultEntities::PROPERTY_GROUP;
    }

    public function supportsTotal(MigrationContextInterface $migrationContext): bool
    {
        return $migrationContext->getProfile() instanceof Magento19Profile
            && $migrationContext->getGateway()->getName() === Magento19LocalGateway::GATEWAY_NAME;
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

    public function readTotal(MigrationContextInterface $migrationContext): ?TotalStruct
    {
        $this->setConnection($migrationContext);

        $sql = <<<SQL
SELECT COUNT(*)
FROM {$this->tablePrefix}eav_attribute AS eav
INNER JOIN {$this->tablePrefix}catalog_eav_attribute AS eav_settings ON eav_settings.attribute_id = eav.attribute_id
INNER JOIN {$this->tablePrefix}eav_attribute_option AS options ON options.attribute_id = eav.attribute_id
INNER JOIN {$this->tablePrefix}eav_attribute_option_value AS option_value ON option_value.option_id = options.option_id AND option_value.store_id = 0
WHERE eav.is_user_defined = 1;
SQL;
        $total = (int) $this->connection->executeQuery($sql)->fetchColumn();

        return new TotalStruct(DefaultEntities::PROPERTY_GROUP, $total);
    }

    public function fetchPropertyGroups(MigrationContextInterface $migrationContext): array
    {
        $query = $this->connection->createQueryBuilder();

        $query->addSelect('eav.attribute_id AS identifier');
        $query->addSelect('eav.attribute_id AS groupId');
        $query->addSelect('eav.frontend_label AS groupName');
        $query->from($this->tablePrefix . 'eav_attribute', 'eav');

        $query->innerJoin('eav', $this->tablePrefix . 'catalog_eav_attribute', 'eav_settings', 'eav_settings.attribute_id = eav.attribute_id');
        $query->innerJoin('eav', $this->tablePrefix . 'eav_attribute_option', 'options', 'options.attribute_id = eav.attribute_id');

        $query->addSelect('option_value.option_id AS optionId');
        $query->addSelect('option_value.value AS optionValue');
        $query->innerJoin('eav', $this->tablePrefix . 'eav_attribute_option_value', 'option_value', 'option_value.option_id = options.option_id AND option_value.store_id = 0');

        $query->where('eav.is_user_defined = 1');

        $query->setFirstResult($migrationContext->getOffset());
        $query->setMaxResults($migrationContext->getLimit());

        return $query->execute()->fetchAll(\PDO::FETCH_GROUP | \PDO::FETCH_ASSOC);
    }
}
