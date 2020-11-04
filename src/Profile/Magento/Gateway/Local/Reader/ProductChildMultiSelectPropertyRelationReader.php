<?php declare(strict_types=1);
/*
 * (c) shopware AG <info@shopware.com>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Swag\MigrationMagento\Profile\Magento\Gateway\Local\Reader;

use Doctrine\DBAL\Connection as ConnectionAlias;
use Swag\MigrationMagento\Profile\Magento\DataSelection\DefaultEntities as DefaultEntitiesAlias;
use SwagMigrationAssistant\Migration\MigrationContextInterface;
use SwagMigrationAssistant\Migration\TotalStruct;

abstract class ProductChildMultiSelectPropertyRelationReader extends AbstractReader
{
    /**
     * @var int
     */
    protected $productEntityTypeId;

    public function readTotal(MigrationContextInterface $migrationContext): ?TotalStruct
    {
        $this->setConnection($migrationContext);
        $this->productEntityTypeId = $this->readProductEntityTypeId();

        $query = <<<SQL
SELECT COUNT(entity_varchar.entity_id)
FROM {$this->tablePrefix}catalog_product_entity_varchar AS entity_varchar
WHERE entity_varchar.value IS NOT NULL
AND entity_varchar.entity_type_id = {$this->productEntityTypeId}
AND entity_varchar.store_id = 0
AND entity_varchar.attribute_id IN (
	SELECT eav.attribute_id
	FROM {$this->tablePrefix}eav_attribute AS eav
	LEFT JOIN {$this->tablePrefix}catalog_eav_attribute AS eav_settings ON eav_settings.attribute_id = eav.attribute_id
	WHERE eav.is_user_defined = 1
	AND eav_settings.is_filterable = 1

	UNION

	SELECT eav.attribute_id
    FROM {$this->tablePrefix}eav_attribute AS eav
    LEFT JOIN {$this->tablePrefix}catalog_eav_attribute AS eav_settings ON eav_settings.attribute_id = eav.attribute_id
    WHERE eav.is_user_defined = 1
    AND eav_settings.is_configurable = 1
);
SQL;
        $total = (int) $this->connection->executeQuery($query)->fetchColumn();

        return new TotalStruct(DefaultEntitiesAlias::PRODUCT_CHILD_MULTI_SELECT_PROPERTY, $total);
    }

    public function read(MigrationContextInterface $migrationContext): array
    {
        $this->setConnection($migrationContext);
        $this->productEntityTypeId = $this->readProductEntityTypeId();
        $multiSelectProperties = $this->fetchMultiSelectProperties($migrationContext);

        $properties = [];
        foreach ($multiSelectProperties as $property) {
            if (!isset($property['option_value'])) {
                continue;
            }

            $multiProperties = \explode(',', $property['option_value']);

            foreach ($multiProperties as $propertyId) {
                $property['option_id'] = $propertyId;
                unset($property['option_value']);
                $properties[] = $property;
            }
        }

        return $this->cleanupResultSet($properties);
    }

    protected function fetchMultiSelectProperties(MigrationContextInterface $migrationContext): array
    {
        $sql = <<<SQL
SELECT DISTINCT product.entity_id, entity_varchar.value AS option_value
FROM {$this->tablePrefix}catalog_product_entity AS product
LEFT JOIN {$this->tablePrefix}catalog_product_relation AS relation ON relation.parent_id = product.entity_id
INNER JOIN {$this->tablePrefix}catalog_product_entity_varchar AS entity_varchar ON  entity_varchar.entity_id = relation.child_id
INNER JOIN {$this->tablePrefix}eav_attribute AS eav ON eav.attribute_id = entity_varchar.attribute_id AND eav.is_user_defined = 1
INNER JOIN {$this->tablePrefix}catalog_eav_attribute AS eav_settings ON eav_settings.attribute_id = eav.attribute_id AND (eav_settings.is_filterable = 1 OR eav_settings.is_configurable = 1)
WHERE product.type_id IN (:validTypes)
AND entity_varchar.store_id = 0
AND entity_varchar.entity_type_id = {$this->productEntityTypeId}
AND NOT EXISTS(
    SELECT *
    FROM {$this->tablePrefix}catalog_product_relation AS child_rel
    INNER JOIN {$this->tablePrefix}catalog_product_entity AS child_parent ON child_parent.entity_id = child_rel.parent_id AND child_parent.type_id IN (:validTypes)
    WHERE child_rel.child_id = product.entity_id
) LIMIT :limit OFFSET :offset;
SQL;
        $query = $this->connection->executeQuery(
            $sql,
            [
                'validTypes' => ProductReader::$ALLOWED_PRODUCT_TYPES,
                'limit' => $migrationContext->getLimit(),
                'offset' => $migrationContext->getOffset(),
            ],
            [
                'validTypes' => ConnectionAlias::PARAM_STR_ARRAY,
                'limit' => \PDO::PARAM_INT,
                'offset' => \PDO::PARAM_INT,
            ]
        );

        return $query->fetchAll(\PDO::FETCH_ASSOC);
    }

    private function readProductEntityTypeId(): int
    {
        $sql = <<<SQL
SELECT entity_type_id FROM {$this->tablePrefix}eav_entity_type WHERE entity_type_code = 'catalog_product';
SQL;

        return (int) $this->connection->executeQuery($sql)->fetchColumn();
    }
}
