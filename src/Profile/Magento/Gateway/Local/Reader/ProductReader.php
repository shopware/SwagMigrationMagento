<?php declare(strict_types=1);

namespace Swag\MigrationMagento\Profile\Magento\Gateway\Local\Reader;

use Doctrine\DBAL\Connection;
use Swag\MigrationMagento\Profile\Magento\Magento19Profile;
use SwagMigrationAssistant\Migration\DataSelection\DefaultEntities;
use SwagMigrationAssistant\Migration\MigrationContextInterface;

class ProductReader extends AbstractReader implements LocalReaderInterface
{
    public static $ALLOWED_PRODUCT_TYPES = [
        'simple',
        'configurable',
        'downloadable',
    ];

    public function supports(MigrationContextInterface $migrationContext): bool
    {
        return $migrationContext->getProfile() instanceof Magento19Profile
            && $migrationContext->getDataSet()::getEntity() === DefaultEntities::PRODUCT;
    }

    public function read(MigrationContextInterface $migrationContext, array $params = []): array
    {
        $this->setConnection($migrationContext);

        $fetchedProducts = $this->fetchProducts($migrationContext);
        $ids = array_column($fetchedProducts, 'entity_id');
        $this->appendDefaultAttributes($ids, $fetchedProducts);
        $this->appendCustomAttributes($ids, $fetchedProducts);

        return $this->appendAssociatedData($fetchedProducts, $ids);
    }

    protected function fetchProducts(MigrationContextInterface $migrationContext): array
    {
        $sql = <<<SQL
SELECT
    product.*,
    stock.qty                 as instock,
    stock.min_qty             as stockmin,
    stock.min_sale_qty        as minpurchase,
    stock.max_sale_qty        as maxpurchase,
    price_includes_tax.value  as priceIncludesTax
FROM {$this->tablePrefix}catalog_product_entity product

-- join stocks
LEFT JOIN {$this->tablePrefix}cataloginventory_stock_item stock
ON stock.product_id = product.entity_id
AND stock.stock_id = 1

-- join price includes tax configuration
LEFT JOIN {$this->tablePrefix}core_config_data price_includes_tax
ON price_includes_tax.path = 'tax/calculation/price_includes_tax'
AND price_includes_tax.scope = 'default'

WHERE product.type_id IN (?)

ORDER BY 
  case 
    WHEN product.type_id = 'configurable' THEN 1
    ELSE 2
  END ASC

LIMIT ?
OFFSET ?;
SQL;

        return $this->connection->executeQuery(
            $sql,
            [
                self::$ALLOWED_PRODUCT_TYPES,
                $migrationContext->getLimit(),
                $migrationContext->getOffset(),
            ],
            [
                Connection::PARAM_STR_ARRAY,
                \PDO::PARAM_INT,
                \PDO::PARAM_INT,
            ]
        )->fetchAll(\PDO::FETCH_ASSOC);
    }

    protected function appendDefaultAttributes(array $ids, array &$fetchedProducts): void
    {
        $sql = <<<SQL
SELECT 
    product.entity_id,
    attribute.attribute_code,
    CASE attribute.backend_type
       WHEN 'varchar' THEN product_varchar.value
       WHEN 'int' THEN product_int.value
       WHEN 'text' THEN product_text.value
       WHEN 'decimal' THEN product_decimal.value
       WHEN 'datetime' THEN product_datetime.value
       ELSE attribute.backend_type
    END AS value
FROM {$this->tablePrefix}catalog_product_entity AS product
LEFT JOIN {$this->tablePrefix}eav_attribute AS attribute 
    ON product.entity_type_id = attribute.entity_type_id
LEFT JOIN {$this->tablePrefix}catalog_product_entity_varchar AS product_varchar 
    ON product.entity_id = product_varchar.entity_id 
    AND attribute.attribute_id = product_varchar.attribute_id 
    AND attribute.backend_type = 'varchar'
LEFT JOIN {$this->tablePrefix}catalog_product_entity_int AS product_int 
    ON product.entity_id = product_int.entity_id 
    AND attribute.attribute_id = product_int.attribute_id 
    AND attribute.backend_type = 'int'
LEFT JOIN {$this->tablePrefix}catalog_product_entity_text AS product_text 
    ON product.entity_id = product_text.entity_id 
    AND attribute.attribute_id = product_text.attribute_id 
    AND attribute.backend_type = 'text'
LEFT JOIN {$this->tablePrefix}catalog_product_entity_decimal AS product_decimal 
    ON product.entity_id = product_decimal.entity_id 
    AND attribute.attribute_id = product_decimal.attribute_id 
    AND attribute.backend_type = 'decimal'
LEFT JOIN {$this->tablePrefix}catalog_product_entity_datetime AS product_datetime 
    ON product.entity_id = product_datetime.entity_id 
    AND attribute.attribute_id = product_datetime.attribute_id 
    AND attribute.backend_type = 'datetime'
WHERE product.entity_id IN (?)
AND (attribute.is_user_defined = 0 OR attribute.attribute_code IN (?))
AND attribute.backend_type != 'static'
AND attribute.frontend_input IS NOT NULL
GROUP BY product.entity_id, attribute_code, value;
SQL;
        $fetchedAttributes = $this->connection->executeQuery(
            $sql,
            [$ids, ['manufacturer', 'cost']],
            [Connection::PARAM_STR_ARRAY, Connection::PARAM_STR_ARRAY]
        )->fetchAll(\PDO::FETCH_GROUP | \PDO::FETCH_ASSOC);

        foreach ($fetchedProducts as &$fetchedProduct) {
            if (isset($fetchedAttributes[$fetchedProduct['entity_id']])) {
                $attributes = $fetchedAttributes[$fetchedProduct['entity_id']];
                $preparedAttributes = array_combine(
                    array_column($attributes, 'attribute_code'),
                    array_column($attributes, 'value')
                );
                $fetchedProduct = array_merge($fetchedProduct, $preparedAttributes);
            }
        }
    }

    protected function appendCustomAttributes(array $ids, array &$fetchedProducts): void
    {
        $sql = <<<SQL
SELECT 
    product.entity_id,
    attribute.attribute_id,
    attribute.attribute_code,
       attribute.frontend_input,
    CASE attribute.backend_type
       WHEN 'varchar' THEN product_varchar.value
       WHEN 'int' THEN product_int.value
       WHEN 'text' THEN product_text.value
       WHEN 'decimal' THEN product_decimal.value
       WHEN 'datetime' THEN product_datetime.value
       ELSE attribute.backend_type
    END AS value
FROM {$this->tablePrefix}catalog_product_entity AS product
INNER JOIN {$this->tablePrefix}eav_attribute AS attribute 
    ON product.entity_type_id = attribute.entity_type_id
INNER JOIN {$this->tablePrefix}catalog_eav_attribute AS attributeSetting
    ON attribute.attribute_id = attributeSetting.attribute_id
    AND attributeSetting.is_configurable = 0
LEFT JOIN {$this->tablePrefix}catalog_product_entity_varchar AS product_varchar 
    ON product.entity_id = product_varchar.entity_id 
    AND attribute.attribute_id = product_varchar.attribute_id 
    AND attribute.backend_type = 'varchar'
LEFT JOIN {$this->tablePrefix}catalog_product_entity_int AS product_int 
    ON product.entity_id = product_int.entity_id 
    AND attribute.attribute_id = product_int.attribute_id 
    AND attribute.backend_type = 'int'
LEFT JOIN {$this->tablePrefix}catalog_product_entity_text AS product_text 
    ON product.entity_id = product_text.entity_id 
    AND attribute.attribute_id = product_text.attribute_id 
    AND attribute.backend_type = 'text'
LEFT JOIN {$this->tablePrefix}catalog_product_entity_decimal AS product_decimal 
    ON product.entity_id = product_decimal.entity_id 
    AND attribute.attribute_id = product_decimal.attribute_id 
    AND attribute.backend_type = 'decimal'
LEFT JOIN {$this->tablePrefix}catalog_product_entity_datetime AS product_datetime 
    ON product.entity_id = product_datetime.entity_id 
    AND attribute.attribute_id = product_datetime.attribute_id 
    AND attribute.backend_type = 'datetime'
WHERE product.entity_id IN (?)
AND attribute.is_user_defined = 1
AND attribute.frontend_input IS NOT NULL
GROUP BY product.entity_id, attribute.attribute_id, attribute_code, value;
SQL;
        $fetchedAttributes = $this->connection->executeQuery(
            $sql,
            [$ids],
            [Connection::PARAM_STR_ARRAY, Connection::PARAM_STR_ARRAY]
        )->fetchAll(\PDO::FETCH_GROUP | \PDO::FETCH_ASSOC);

        foreach ($fetchedProducts as &$fetchedProduct) {
            if (isset($fetchedAttributes[$fetchedProduct['entity_id']])) {
                $attributes = $fetchedAttributes[$fetchedProduct['entity_id']];
                foreach ($attributes as $attribute) {
                    if (empty($attribute['value'])) {
                        continue;
                    }

                    $fetchedProduct['attributes'][] = $attribute;
                }
            }
        }
    }

    protected function appendAssociatedData(array $fetchedProducts, array $ids)
    {
        $resultSet = [];

        $categories = $this->fetchProductCategories($ids);
        $media = $this->fetchProductMedia($ids);
        $prices = $this->fetchProductPrices($ids);
        $properties = $this->fetchProperties($ids);
        $configuratorSettings = $this->fetchConfiguratorSettings($ids);
        $options = $this->fetchOptions($ids);
        $visibility = $this->fetchVisibility($ids);
        $parents = $this->fetchParents($ids);

        foreach ($fetchedProducts as &$product) {
            $productId = $product['entity_id'];

            if (isset($categories[$productId])) {
                $product['categories'] = $categories[$productId];
            }
            if (isset($media[$productId])) {
                $product['media'] = $media[$productId];
            }
            if (isset($prices[$productId])) {
                $product['prices'] = $prices[$productId];
            }
            if (isset($properties[$productId])) {
                $product['properties'] = $properties[$productId];
            }
            if (isset($configuratorSettings[$productId])) {
                $product['configuratorSettings'] = $configuratorSettings[$productId];
            }
            if (isset($options[$productId])) {
                $product['options'] = $options[$productId];
            }
            if (isset($visibility[$productId])) {
                $product['visibility'] = $visibility[$productId];
            }
            if (isset($parents[$productId])) {
                $product['parentId'] = $parents[$productId];
            }

            $resultSet[] = $product;
        }

        $resultSet = $this->utf8ize($resultSet);

        return $resultSet;
    }

    protected function fetchProductCategories(array $ids): array
    {
        $sql = <<<SQL
SELECT 
    productCategory.product_id,
    productCategory.product_id as productId,
    productCategory.category_id as categoryId
FROM {$this->tablePrefix}catalog_category_product productCategory
WHERE productCategory.product_id IN (?)
ORDER BY productCategory.position
SQL;

        return $this->connection->executeQuery($sql, [$ids], [Connection::PARAM_STR_ARRAY])->fetchAll(\PDO::FETCH_GROUP | \PDO::FETCH_ASSOC);
    }

    protected function fetchProductMedia(array $ids)
    {
        $sql = <<<SQL
SELECT
    mediaGallery.entity_id as productId,
    mediaGallery.value as image,
    mediaGalleryValue.label as description,
    mediaGalleryValue.position,
    IF(mediaGalleryValue.position=1, 1, 0) as main
FROM 
    {$this->tablePrefix}catalog_product_entity_media_gallery mediaGallery,
    {$this->tablePrefix}catalog_product_entity_media_gallery_value mediaGalleryValue
WHERE mediaGallery.entity_id IN (?)
AND mediaGalleryValue.value_id = mediaGallery.value_id
ORDER BY productId, position;
SQL;

        return $this->connection->executeQuery($sql, [$ids], [Connection::PARAM_STR_ARRAY])->fetchAll(\PDO::FETCH_GROUP | \PDO::FETCH_ASSOC);
    }

    protected function fetchProductPrices(array $ids): array
    {
        $sql = <<<SQL
SELECT
    price.entity_id, 
    price.*,
    customerGroup.customer_group_code as customerGroupCode
FROM {$this->tablePrefix}catalog_product_entity_tier_price price
LEFT JOIN {$this->tablePrefix}customer_group customerGroup ON customerGroup.customer_group_id = price.customer_group_id
WHERE price.entity_id IN (?)
ORDER BY price.entity_id, price.all_groups DESC, price.customer_group_id, price.qty;
SQL;

        return $this->connection->executeQuery($sql, [$ids], [Connection::PARAM_STR_ARRAY])->fetchAll(\PDO::FETCH_GROUP | \PDO::FETCH_ASSOC);
    }

    protected function fetchProperties(array $ids): array
    {
        $query = $this->connection->createQueryBuilder();

        $query->select('DISTINCT product.entity_id identifier');
        $query->addSelect('product.entity_id parentId');
        $query->addSelect('eav.attribute_id as groupId');
        $query->addSelect('eav.attribute_code groupName');
        $query->addSelect('option_value.option_id as optionId');
        $query->addSelect('option_value.value optionValue');

        $query->from($this->tablePrefix . 'catalog_product_entity', 'product');

        $query->innerJoin('product', $this->tablePrefix . 'catalog_product_relation', 'relation', 'relation.parent_id = product.entity_id');
        $query->innerJoin('product', $this->tablePrefix . 'catalog_product_entity_int', 'entity_int', 'entity_int.entity_id = relation.child_id');
        $query->innerJoin('product', $this->tablePrefix . 'eav_attribute', 'eav', 'eav.attribute_id = entity_int.attribute_id AND eav.is_user_defined = 1');
        $query->innerJoin('product', $this->tablePrefix . 'catalog_eav_attribute', 'eav_settings', 'eav_settings.attribute_id = eav.attribute_id AND eav_settings.is_filterable = 1');
        $query->innerJoin('product', $this->tablePrefix . 'eav_attribute_option_value', 'option_value', 'option_value.option_id = entity_int.value AND option_value.store_id = 0');

        $query->where('product.entity_type_id = (SELECT entity_type_id FROM ' . $this->tablePrefix . 'eav_entity_type WHERE entity_type_code = \'catalog_product\') and product.entity_id in (:ids)');
        $query->setParameter('ids', $ids, Connection::PARAM_STR_ARRAY);

        return $query->execute()->fetchAll(\PDO::FETCH_GROUP | \PDO::FETCH_ASSOC);
    }

    protected function fetchConfiguratorSettings(array $ids): array
    {
        $query = $this->connection->createQueryBuilder();

        $query->select('DISTINCT product.entity_id identifier');
        $query->addSelect('product.entity_id parentId');
        $query->addSelect('eav.attribute_id as groupId');
        $query->addSelect('eav.attribute_code groupName');
        $query->addSelect('option_value.option_id as optionId');
        $query->addSelect('option_value.value optionValue');

        $query->from($this->tablePrefix . 'catalog_product_entity', 'product');

        $query->innerJoin('product', $this->tablePrefix . 'catalog_product_relation', 'relation', 'relation.parent_id = product.entity_id');
        $query->innerJoin('product', $this->tablePrefix . 'catalog_product_entity_int', 'entity_int', 'entity_int.entity_id = relation.child_id');
        $query->innerJoin('product', $this->tablePrefix . 'eav_attribute', 'eav', 'eav.attribute_id = entity_int.attribute_id AND eav.is_user_defined = 1');
        $query->innerJoin('product', $this->tablePrefix . 'catalog_eav_attribute', 'eav_settings', 'eav_settings.attribute_id = eav.attribute_id AND eav_settings.is_configurable = 1');
        $query->innerJoin('product', $this->tablePrefix . 'catalog_product_super_attribute', 'super_attr', 'super_attr.attribute_id = eav.attribute_id AND super_attr.product_id = product.entity_id');
        $query->innerJoin('product', $this->tablePrefix . 'eav_attribute_option_value', 'option_value', 'option_value.option_id = entity_int.value AND option_value.store_id = 0');

        $query->where('product.entity_type_id = (SELECT entity_type_id FROM ' . $this->tablePrefix . 'eav_entity_type WHERE entity_type_code = \'catalog_product\') and product.entity_id in (:ids)');
        $query->setParameter('ids', $ids, Connection::PARAM_STR_ARRAY);

        return $query->execute()->fetchAll(\PDO::FETCH_GROUP | \PDO::FETCH_ASSOC);
    }

    protected function fetchOptions(array $ids): array
    {
        $query = $this->connection->createQueryBuilder();

        $query->select('DISTINCT product.entity_id identifier');
        $query->addSelect('product.entity_id productId');
        $query->addSelect('eav.attribute_id as groupId');
        $query->addSelect('eav.attribute_code groupName');
        $query->addSelect('option_value.option_id as optionId');
        $query->addSelect('option_value.value optionValue');

        $query->from($this->tablePrefix . 'catalog_product_entity', 'product');

        $query->innerJoin('product', $this->tablePrefix . 'catalog_product_relation', 'relation', 'relation.child_id = product.entity_id');
        $query->innerJoin('product', $this->tablePrefix . 'catalog_product_entity_int', 'entity_int', 'entity_int.entity_id = product.entity_id');
        $query->innerJoin('product', $this->tablePrefix . 'eav_attribute', 'eav', 'eav.attribute_id = entity_int.attribute_id AND eav.is_user_defined = 1');
        $query->innerJoin('product', $this->tablePrefix . 'catalog_eav_attribute', 'eav_settings', 'eav_settings.attribute_id = eav.attribute_id AND eav_settings.is_configurable = 1');
        $query->innerJoin('product', $this->tablePrefix . 'catalog_product_super_attribute', 'super_attr', 'super_attr.attribute_id = eav.attribute_id AND super_attr.product_id = relation.parent_id');
        $query->innerJoin('product', $this->tablePrefix . 'eav_attribute_option_value', 'option_value', 'option_value.option_id = entity_int.value AND option_value.store_id = 0');

        $query->where('product.entity_type_id = (SELECT entity_type_id FROM ' . $this->tablePrefix . 'eav_entity_type WHERE entity_type_code = \'catalog_product\') and product.entity_id in (:ids)');
        $query->setParameter('ids', $ids, Connection::PARAM_STR_ARRAY);

        return $query->execute()->fetchAll(\PDO::FETCH_GROUP | \PDO::FETCH_ASSOC);
    }

    protected function fetchVisibility(array $ids): array
    {
        $query = $this->connection->createQueryBuilder();

        $query->select('product_int.entity_id, product_int.store_id, product_int.value');
        $query->from($this->tablePrefix . 'catalog_product_entity_int', 'product_int');

        $query->innerJoin('product_int', $this->tablePrefix . 'eav_attribute', 'attribute', 'product_int.attribute_id = attribute.attribute_id');

        $query->where('attribute.attribute_code =  \'status\'');
        $query->andWhere('product_int.entity_id IN (:ids)');
        $query->orderBy('product_int.value');
        $query->setParameter('ids', $ids, Connection::PARAM_STR_ARRAY);

        return $query->execute()->fetchAll(\PDO::FETCH_GROUP | \PDO::FETCH_ASSOC);
    }

    protected function fetchParents(array $ids): array
    {
        $query = $this->connection->createQueryBuilder();

        $query->select('relation.child_id productId');
        $query->addSelect('relation.parent_id parentId');

        $query->from($this->tablePrefix . 'catalog_product_relation', 'relation');
        $query->innerJoin('relation', $this->tablePrefix . 'catalog_product_entity', 'product', 'product.entity_id = relation.parent_id');

        $query->where('relation.child_id IN (:ids)');
        $query->andWhere('product.type_id IN (:types)');
        $query->orderBy('product.created_at', 'DESC');
        $query->setParameter('ids', $ids, Connection::PARAM_STR_ARRAY);
        $query->setParameter('types', self::$ALLOWED_PRODUCT_TYPES, Connection::PARAM_STR_ARRAY);

        $fetchedParents = $query->execute()->fetchAll(\PDO::FETCH_ASSOC);

        $parents = [];
        foreach ($fetchedParents as $parent) {
            $productId = $parent['productId'];

            if (isset($parents[$productId])) {
                continue;
            }

            $parents[$productId] = $parent['parentId'];
        }

        return $parents;
    }
}
