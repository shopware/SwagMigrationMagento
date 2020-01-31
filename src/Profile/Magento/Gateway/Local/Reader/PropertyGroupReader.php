<?php declare(strict_types=1);
/*
 * (c) shopware AG <info@shopware.com>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Swag\MigrationMagento\Profile\Magento\Gateway\Local\Reader;

use Doctrine\DBAL\Connection;
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
        $optionIds = [];
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

                $optionIds[] = $option['optionId'];
                $groups[$groupId]['options'][] = [
                    'id' => $option['optionId'],
                    'name' => $option['optionValue'],
                ];
            }
        }

        $groupIds = array_column($groups, 'id');
        $optionTranslations = $this->fetchOptionTranslations($optionIds);
        $groupTranslations = $this->fetchGroupTranslations($groupIds);

        foreach ($groups as &$group) {
            $groupId = $group['id'];

            if (isset($groupTranslations[$groupId])) {
                foreach ($groupTranslations[$groupId] as $translation) {
                    $store_id = $translation['store_id'];
                    $attribute_id = $translation['attribute_id'];
                    $value = $translation['value'];

                    $group['translations'][$store_id]['name']['value'] = $value;
                    $group['translations'][$store_id]['name']['attribute_id'] = $attribute_id;
                }
            }

            foreach ($group['options'] as &$option) {
                $optionId = $option['id'];

                if (isset($optionTranslations[$optionId])) {
                    foreach ($optionTranslations[$optionId] as $translation) {
                        $store_id = $translation['store_id'];
                        $attribute_id = $translation['attribute_id'];
                        $value = $translation['value'];

                        $option['translations'][$store_id]['name']['value'] = $value;
                        $option['translations'][$store_id]['name']['attribute_id'] = $attribute_id;
                    }
                }
            }
        }

        return $this->utf8ize($groups);
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
        $query->andWhere('eav.attribute_code NOT IN (\'manufacturer\', \'cost\')');

        $query->setFirstResult($migrationContext->getOffset());
        $query->setMaxResults($migrationContext->getLimit());

        return $query->execute()->fetchAll(\PDO::FETCH_GROUP | \PDO::FETCH_ASSOC);
    }

    protected function fetchOptionTranslations(array $ids): array
    {
        $query = $this->connection->createQueryBuilder();

        $query->addSelect('optionValue.option_id');
        $query->addSelect('optionValue.store_id');
        $query->addSelect('attributeOption.attribute_id');
        $query->addSelect('optionValue.value');
        $query->from($this->tablePrefix . 'eav_attribute_option', 'attributeOption');

        $query->innerJoin('attributeOption', $this->tablePrefix . 'eav_attribute_option_value', 'optionValue', 'optionValue.option_id = attributeOption.option_id AND optionValue.store_id != 0');

        $query->where('attributeOption.option_id IN (:ids)');
        $query->setParameter('ids', $ids, Connection::PARAM_INT_ARRAY);

        return $query->execute()->fetchAll(\PDO::FETCH_GROUP | \PDO::FETCH_ASSOC);
    }

    protected function fetchGroupTranslations(array $ids): array
    {
        $query = $this->connection->createQueryBuilder();

        $query->addSelect('attributeLabel.attribute_id AS identifier');
        $query->addSelect('attributeLabel.attribute_id');
        $query->addSelect('attributeLabel.store_id');
        $query->addSelect('attributeLabel.value');
        $query->from($this->tablePrefix . 'eav_attribute_label', 'attributeLabel');

        $query->where('attributeLabel.attribute_id IN (:ids)');
        $query->setParameter('ids', $ids, Connection::PARAM_INT_ARRAY);

        return $query->execute()->fetchAll(\PDO::FETCH_GROUP | \PDO::FETCH_ASSOC);
    }
}
