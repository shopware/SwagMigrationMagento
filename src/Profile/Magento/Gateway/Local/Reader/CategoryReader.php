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

class CategoryReader extends AbstractReader
{
    public function supports(MigrationContextInterface $migrationContext): bool
    {
        return $migrationContext->getProfile() instanceof Magento19Profile
            && $migrationContext->getGateway()->getName() === Magento19LocalGateway::GATEWAY_NAME
            && $migrationContext->getDataSet()::getEntity() === DefaultEntities::CATEGORY;
    }

    public function supportsTotal(MigrationContextInterface $migrationContext): bool
    {
        return $migrationContext->getProfile() instanceof Magento19Profile
            && $migrationContext->getGateway()->getName() === Magento19LocalGateway::GATEWAY_NAME;
    }

    public function read(MigrationContextInterface $migrationContext, array $params = []): array
    {
        $this->setConnection($migrationContext);

        $ids = $this->fetchIdentifiers($this->tablePrefix . 'catalog_category_entity', 'entity_id', $migrationContext->getOffset(), $migrationContext->getLimit());
        $fetchedCategories = $this->fetchCategories($ids);
        $this->appendTranslations($ids, $fetchedCategories);

        foreach ($fetchedCategories as &$category) {
            $category['defaultLocale'] = str_replace('_', '-', $category['defaultLocale']);
        }

        return $this->cleanupResultSet($fetchedCategories);
    }

    public function readTotal(MigrationContextInterface $migrationContext): ?TotalStruct
    {
        $this->setConnection($migrationContext);

        $sql = <<<SQL
SELECT COUNT(*)
FROM {$this->tablePrefix}catalog_category_entity;
SQL;
        $total = (int) $this->connection->executeQuery($sql)->fetchColumn();

        return new TotalStruct(DefaultEntities::CATEGORY, $total);
    }

    public function fetchCategories(array $ids): array
    {
        $sql = <<<SQL
SELECT
    category.*,
    name.value AS name,
    description.value AS description,
    status.value AS status,
    sibling.entity_id AS previousSiblingId,
    defaultLocale.value AS defaultLocale,
    image.value AS image
FROM
    {$this->tablePrefix}catalog_category_entity category

LEFT JOIN {$this->tablePrefix}catalog_category_entity_varchar AS name
    ON category.entity_id = name.entity_id
    AND name.attribute_id = (SELECT attribute.attribute_id FROM {$this->tablePrefix}eav_attribute attribute WHERE attribute.`entity_type_id` = category.`entity_type_id` AND attribute.attribute_code = 'name')
    AND name.store_id = 0

LEFT JOIN {$this->tablePrefix}catalog_category_entity_int AS status
    ON category.entity_id = status.entity_id
    AND status.attribute_id = (SELECT attribute.attribute_id FROM {$this->tablePrefix}eav_attribute attribute WHERE attribute.`entity_type_id` = category.`entity_type_id` AND attribute.attribute_code = 'is_active')
    AND status.store_id = 0

LEFT JOIN {$this->tablePrefix}catalog_category_entity_text AS description
    ON category.entity_id = description.entity_id
    AND description.attribute_id = (SELECT attribute.attribute_id FROM {$this->tablePrefix}eav_attribute attribute WHERE attribute.`entity_type_id` = category.`entity_type_id` AND attribute.attribute_code = 'description')
    AND description.store_id = 0

LEFT JOIN {$this->tablePrefix}catalog_category_entity_varchar AS image
    ON category.entity_id = image.entity_id
    AND image.attribute_id = (SELECT attribute.attribute_id FROM {$this->tablePrefix}eav_attribute attribute WHERE attribute.`entity_type_id` = category.`entity_type_id` AND attribute.attribute_code = 'image')
    AND image.store_id = 0

LEFT JOIN {$this->tablePrefix}core_config_data AS defaultLocale
    ON defaultLocale.scope = 'default' AND defaultLocale.path = 'general/locale/code'

LEFT JOIN {$this->tablePrefix}catalog_category_entity AS sibling
    ON sibling.entity_id = (SELECT previous.entity_id
        FROM (SELECT sub_category.entity_id,
                 sub_category.parent_id,
                 IFNULL(sub_category.position,
                        IFNULL(
                          (SELECT new_position.position + sub_category.entity_id
                           FROM {$this->tablePrefix}catalog_category_entity new_position
                           WHERE sub_category.parent_id = new_position.parent_id
                           ORDER BY new_position.position DESC
                           LIMIT 1),
                          sub_category.entity_id)) position
            FROM {$this->tablePrefix}catalog_category_entity sub_category) previous
            WHERE previous.position <
                    IFNULL(category.position,
                        IFNULL(
                          (SELECT previous.position + category.entity_id
                           FROM {$this->tablePrefix}catalog_category_entity previous
                           WHERE category.parent_id = previous.parent_id
                           ORDER BY previous.position DESC
                           LIMIT 1),
                        category.entity_id)
                    )
                    AND category.parent_id = previous.parent_id
                    ORDER BY previous.position DESC
                    LIMIT 1)
WHERE category.entity_id IN (?)

ORDER BY level, position
SQL;

        return $this->connection->executeQuery(
            $sql,
            [$ids],
            [Connection::PARAM_STR_ARRAY]
        )->fetchAll(\PDO::FETCH_ASSOC);
    }

    protected function appendTranslations(array $ids, array &$fetchedCategories): void
    {
        $sql = <<<SQL
SELECT
    category.entity_id AS identifier,
    category.entity_id,
    attribute.attribute_id,
    attribute.attribute_code,
    CASE attribute.backend_type
       WHEN 'varchar' THEN category_varchar.value
       WHEN 'int' THEN category_int.value
       WHEN 'text' THEN category_text.value
       WHEN 'decimal' THEN category_decimal.value
       WHEN 'datetime' THEN category_datetime.value
       ELSE attribute.backend_type
    END AS value,
    CASE attribute.backend_type
         WHEN 'varchar' THEN category_varchar.store_id
         WHEN 'int' THEN category_int.store_id
         WHEN 'text' THEN category_text.store_id
         WHEN 'decimal' THEN category_decimal.store_id
         WHEN 'datetime' THEN category_datetime.store_id
         ELSE null
    END AS store_id
FROM
    {$this->tablePrefix}catalog_category_entity category

LEFT JOIN {$this->tablePrefix}eav_attribute AS attribute
    ON category.entity_type_id = attribute.entity_type_id

LEFT JOIN {$this->tablePrefix}catalog_category_entity_varchar AS category_varchar
    ON category.entity_id = category_varchar.entity_id
    AND attribute.attribute_id = category_varchar.attribute_id
    AND attribute.backend_type = 'varchar'
    AND category_varchar.store_id != '0'

LEFT JOIN {$this->tablePrefix}catalog_category_entity_int AS category_int
    ON category.entity_id = category_int.entity_id
    AND attribute.attribute_id = category_int.attribute_id
    AND attribute.backend_type = 'int'
    AND category_int.store_id != '0'

LEFT JOIN {$this->tablePrefix}catalog_category_entity_text AS category_text
    ON category.entity_id = category_text.entity_id
    AND attribute.attribute_id = category_text.attribute_id
    AND attribute.backend_type = 'text'
    AND category_text.store_id != '0'

LEFT JOIN {$this->tablePrefix}catalog_category_entity_decimal AS category_decimal
    ON category.entity_id = category_decimal.entity_id
    AND attribute.attribute_id = category_decimal.attribute_id
    AND attribute.backend_type = 'decimal'
    AND category_decimal.store_id != '0'

LEFT JOIN {$this->tablePrefix}catalog_category_entity_datetime AS category_datetime
    ON category.entity_id = category_datetime.entity_id
    AND attribute.attribute_id = category_datetime.attribute_id
    AND attribute.backend_type = 'datetime'
    AND category_datetime.store_id != '0'

WHERE category.entity_id IN (?)
AND CASE attribute.backend_type
    WHEN 'varchar' THEN category_varchar.value
    WHEN 'int' THEN category_int.value
    WHEN 'text' THEN category_text.value
    WHEN 'decimal' THEN category_decimal.value
    WHEN 'datetime' THEN category_datetime.value
    ELSE null
    END IS NOT NULL
GROUP BY category.entity_id, attribute_id, attribute_code, value, store_id;
SQL;

        $fetchedTranslations = $this->connection->executeQuery(
            $sql,
            [$ids],
            [Connection::PARAM_STR_ARRAY]
        )->fetchAll(\PDO::FETCH_GROUP | \PDO::FETCH_ASSOC);

        foreach ($fetchedCategories as &$fetchedCategory) {
            if (isset($fetchedTranslations[$fetchedCategory['entity_id']])) {
                $attributes = $fetchedTranslations[$fetchedCategory['entity_id']];

                foreach ($attributes as $attribute) {
                    $store_id = $attribute['store_id'];
                    $attribute_id = $attribute['attribute_id'];
                    $attribute_code = $attribute['attribute_code'];
                    $value = $attribute['value'];

                    $fetchedCategory['translations'][$store_id][$attribute_code]['value'] = $value;
                    $fetchedCategory['translations'][$store_id][$attribute_code]['attribute_id'] = $attribute_id;
                }
            }
        }
    }
}
