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

class ProductReader extends AbstractReader
{
    public static $ALLOWED_PRODUCT_TYPES = [
        'simple',
        'configurable',
        'downloadable',
    ];

    public function supports(MigrationContextInterface $migrationContext): bool
    {
        return $migrationContext->getProfile() instanceof Magento19Profile
            && $migrationContext->getGateway()->getName() === Magento19LocalGateway::GATEWAY_NAME
            && $migrationContext->getDataSet()::getEntity() === DefaultEntities::PRODUCT;
    }

    public function supportsTotal(MigrationContextInterface $migrationContext): bool
    {
        return $migrationContext->getProfile() instanceof Magento19Profile
            && $migrationContext->getGateway()->getName() === Magento19LocalGateway::GATEWAY_NAME;
    }

    public function read(MigrationContextInterface $migrationContext, array $params = []): array
    {
        $this->setConnection($migrationContext);

        $fetchedProducts = $this->fetchProducts($migrationContext);
        $ids = array_column($fetchedProducts, 'entity_id');

        return $this->appendAssociatedData($fetchedProducts, $ids);
    }

    public function readTotal(MigrationContextInterface $migrationContext): ?TotalStruct
    {
        $this->setConnection($migrationContext);

        $sql = <<<SQL
SELECT COUNT(*)
FROM {$this->tablePrefix}catalog_product_entity
WHERE type_id IN (?);
SQL;
        $total = (int) $this->connection->executeQuery(
            $sql,
            [self::$ALLOWED_PRODUCT_TYPES],
            [Connection::PARAM_STR_ARRAY]
        )->fetchColumn();

        return new TotalStruct(DefaultEntities::PRODUCT, $total);
    }

    protected function fetchProducts(MigrationContextInterface $migrationContext): array
    {
        $sql = <<<SQL
SELECT
    product.*,
    stock.qty                 AS instock,
    stock.min_qty             AS stockmin,
    stock.min_sale_qty        AS minpurchase,
    stock.max_sale_qty        AS maxpurchase,
    relation.parent_id        AS parentId,
    parent.type_id            AS parentType
FROM {$this->tablePrefix}catalog_product_entity product

-- join stocks
LEFT JOIN {$this->tablePrefix}cataloginventory_stock_item AS stock
ON stock.product_id = product.entity_id
AND stock.stock_id = 1

LEFT JOIN {$this->tablePrefix}catalog_product_relation AS relation
ON relation.child_id = product.entity_id

LEFT JOIN {$this->tablePrefix}catalog_product_entity AS parent
ON relation.parent_id = parent.entity_id

WHERE product.type_id IN (?)

ORDER BY relation.parent_id, product.entity_id

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

    protected function fetchProductAttributes(array $ids): array
    {
        $sql = <<<SQL
SELECT
    product.entity_id,
    attribute.attribute_id,
    attribute.attribute_code,
    attribute.frontend_input,
    attribute.backend_type,
    attribute.is_user_defined,
    CASE attribute.backend_type
       WHEN 'varchar' THEN product_varchar.value
       WHEN 'int' THEN product_int.value
       WHEN 'text' THEN product_text.value
       WHEN 'decimal' THEN product_decimal.value
       WHEN 'datetime' THEN product_datetime.value
    END AS value,
    CASE attribute.backend_type
         WHEN 'varchar' THEN product_varchar.store_id
         WHEN 'int' THEN product_int.store_id
         WHEN 'text' THEN product_text.store_id
         WHEN 'decimal' THEN product_decimal.store_id
         WHEN 'datetime' THEN product_datetime.store_id
         ELSE null
    END AS store_id
FROM {$this->tablePrefix}catalog_product_entity product
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
AND attribute.frontend_input IS NOT NULL
AND CASE attribute.backend_type
    WHEN 'varchar' THEN product_varchar.value
    WHEN 'int' THEN product_int.value
    WHEN 'text' THEN product_text.value
    WHEN 'decimal' THEN product_decimal.value
    WHEN 'datetime' THEN product_datetime.value
    ELSE null
    END IS NOT NULL
ORDER BY store_id, attribute_id;   
SQL;
        $fetchedAttributes = $this->connection->executeQuery(
            $sql,
            [$ids],
            [Connection::PARAM_STR_ARRAY]
        )->fetchAll(\PDO::FETCH_GROUP | \PDO::FETCH_ASSOC);

        return $fetchedAttributes;
    }

    protected function appendDefaultAttributes(array $attributes, array &$fetchedProduct): void
    {
        $defaultAttributes = [];
        foreach ($attributes as $attribute) {
            $storeId = $attribute['store_id'];
            $backendType = $attribute['backend_type'];
            $userDefined = $attribute['is_user_defined'];
            $attributeCode = $attribute['attribute_code'];
            if ($storeId !== '0' || $backendType === 'static') {
                continue;
            }
            if ($userDefined === '0' || in_array($attributeCode, ['manufacturer', 'cost'], true)) {
                $value = $attribute['value'];
                $defaultAttributes[$attributeCode] = $value;
            }
        }
        $fetchedProduct = array_merge($fetchedProduct, $defaultAttributes);
        unset($defaultAttributes);
    }

    protected function appendCustomAttributes(array $attributes, array &$fetchedProduct): void
    {
        foreach ($attributes as $attribute) {
            $storeId = $attribute['store_id'];
            $userDefined = $attribute['is_user_defined'];
            if ($storeId !== '0' || $userDefined !== '1') {
                continue;
            }
            $fetchedProduct['attributes'][] = $attribute;
        }
    }

    protected function appendTranslations(array $attributes, array $locales, array &$fetchedProduct): void
    {
        foreach ($attributes as $attribute) {
            $storeId = $attribute['store_id'];
            $attributeId = $attribute['attribute_id'];
            $frontendInput = $attribute['frontend_input'];
            $attributeCode = $attribute['attribute_code'];
            $value = $attribute['value'];
            if ($storeId === '0') {
                foreach ($locales as $localeStoreId => $locale) {
                    $fetchedProduct['translations'][$localeStoreId][$attributeCode]['value'] = $value;
                    $fetchedProduct['translations'][$localeStoreId][$attributeCode]['attribute_id'] = $attributeId;
                    $fetchedProduct['translations'][$localeStoreId][$attributeCode]['frontend_input'] = $frontendInput;
                }
                continue;
            }
            $fetchedProduct['translations'][$storeId][$attributeCode]['value'] = $value;
            $fetchedProduct['translations'][$storeId][$attributeCode]['attribute_id'] = $attributeId;
            $fetchedProduct['translations'][$storeId][$attributeCode]['frontend_input'] = $frontendInput;
        }
    }

    protected function appendAssociatedData(array $fetchedProducts, array $ids)
    {
        $resultSet = [];

        $attributes = $this->fetchProductAttributes($ids);
        $categories = $this->fetchProductCategories($ids);
        $media = $this->fetchProductMedia($ids);
        $prices = $this->fetchProductPrices($ids);
        $properties = $this->fetchProperties($ids);
        $configuratorSettings = $this->fetchConfiguratorSettings($ids);
        $options = $this->fetchOptions($ids);
        $visibility = $this->fetchVisibility($ids);
        $locales = $this->fetchLocales();

        foreach ($fetchedProducts as &$product) {
            $productId = $product['entity_id'];

            if (isset($attributes[$productId])) {
                $productAttributes = $attributes[$productId];
                $this->appendDefaultAttributes($productAttributes, $product);
                $this->appendCustomAttributes($productAttributes, $product);
                $this->appendTranslations($productAttributes, $locales, $product);
            }
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
            // if parent is unsupported type which will not be migrated
            // we remove relation and migrate as single product
            // example: part of bundle, which is not supported yet
            if (isset($product['parentType'])
                && !in_array($product['parentType'], self::$ALLOWED_PRODUCT_TYPES, true)
            ) {
                $product['parentId'] = null;
            }

            $resultSet[] = $product;
        }
        unset(
            $fetchedProducts,
            $attributes,
            $categories,
            $media,
            $prices,
            $properties,
            $configuratorSettings,
            $options,
            $visibility
        );

        $resultSet = $this->utf8ize($resultSet);

        return $resultSet;
    }

    protected function fetchProductCategories(array $ids): array
    {
        $sql = <<<SQL
SELECT
    productCategory.product_id,
    productCategory.product_id AS productId,
    productCategory.category_id AS categoryId
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
    mediaGallery.entity_id AS productId,
    mediaGallery.value AS image,
    mediaGalleryValue.label AS description,
    mediaGalleryValue.position,
    IF(mediaGalleryValue.position=1, 1, 0) AS main
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
    customerGroup.customer_group_code AS customerGroupCode
FROM {$this->tablePrefix}catalog_product_entity_tier_price price
LEFT JOIN {$this->tablePrefix}customer_group AS customerGroup ON customerGroup.customer_group_id = price.customer_group_id
WHERE price.entity_id IN (?)
ORDER BY price.entity_id, price.all_groups DESC, price.customer_group_id, price.qty;
SQL;

        return $this->connection->executeQuery($sql, [$ids], [Connection::PARAM_STR_ARRAY])->fetchAll(\PDO::FETCH_GROUP | \PDO::FETCH_ASSOC);
    }

    protected function fetchProperties(array $ids): array
    {
        $query = $this->connection->createQueryBuilder();

        $query->select('DISTINCT product.entity_id AS identifier');
        $query->addSelect('product.entity_id AS parentId');
        $query->addSelect('eav.attribute_id AS groupId');
        $query->addSelect('eav.attribute_code AS groupName');
        $query->addSelect('option_value.option_id AS optionId');
        $query->addSelect('option_value.value AS optionValue');

        $query->from($this->tablePrefix . 'catalog_product_entity', 'product');

        $query->innerJoin('product', $this->tablePrefix . 'catalog_product_relation', 'relation', 'relation.parent_id = product.entity_id');
        $query->innerJoin('product', $this->tablePrefix . 'catalog_product_entity_int', 'entity_int', 'entity_int.entity_id = relation.child_id');
        $query->innerJoin('product', $this->tablePrefix . 'eav_attribute', 'eav', 'eav.attribute_id = entity_int.attribute_id AND eav.is_user_defined = 1');
        $query->innerJoin('product', $this->tablePrefix . 'catalog_eav_attribute', 'eav_settings', 'eav_settings.attribute_id = eav.attribute_id AND eav_settings.is_filterable = 1');
        $query->innerJoin('product', $this->tablePrefix . 'eav_attribute_option_value', 'option_value', 'option_value.option_id = entity_int.value AND option_value.store_id = 0');

        $query->where('product.entity_type_id = (SELECT entity_type_id FROM ' . $this->tablePrefix . 'eav_entity_type WHERE entity_type_code = \'catalog_product\') and product.entity_id IN (:ids)');
        $query->setParameter('ids', $ids, Connection::PARAM_STR_ARRAY);

        return $query->execute()->fetchAll(\PDO::FETCH_GROUP | \PDO::FETCH_ASSOC);
    }

    protected function fetchConfiguratorSettings(array $ids): array
    {
        $query = $this->connection->createQueryBuilder();

        $query->select('DISTINCT product.entity_id AS identifier');
        $query->addSelect('product.entity_id AS parentId');
        $query->addSelect('eav.attribute_id AS groupId');
        $query->addSelect('eav.attribute_code AS groupName');
        $query->addSelect('option_value.option_id AS optionId');
        $query->addSelect('option_value.value AS optionValue');

        $query->from($this->tablePrefix . 'catalog_product_entity', 'product');

        $query->innerJoin('product', $this->tablePrefix . 'catalog_product_relation', 'relation', 'relation.parent_id = product.entity_id');
        $query->innerJoin('product', $this->tablePrefix . 'catalog_product_entity_int', 'entity_int', 'entity_int.entity_id = relation.child_id');
        $query->innerJoin('product', $this->tablePrefix . 'eav_attribute', 'eav', 'eav.attribute_id = entity_int.attribute_id AND eav.is_user_defined = 1');
        $query->innerJoin('product', $this->tablePrefix . 'catalog_eav_attribute', 'eav_settings', 'eav_settings.attribute_id = eav.attribute_id AND eav_settings.is_configurable = 1');
        $query->innerJoin('product', $this->tablePrefix . 'catalog_product_super_attribute', 'super_attr', 'super_attr.attribute_id = eav.attribute_id AND super_attr.product_id = product.entity_id');
        $query->innerJoin('product', $this->tablePrefix . 'eav_attribute_option_value', 'option_value', 'option_value.option_id = entity_int.value AND option_value.store_id = 0');

        $query->where('product.entity_type_id = (SELECT entity_type_id FROM ' . $this->tablePrefix . 'eav_entity_type WHERE entity_type_code = \'catalog_product\') and product.entity_id IN (:ids)');
        $query->setParameter('ids', $ids, Connection::PARAM_STR_ARRAY);

        return $query->execute()->fetchAll(\PDO::FETCH_GROUP | \PDO::FETCH_ASSOC);
    }

    protected function fetchOptions(array $ids): array
    {
        $query = $this->connection->createQueryBuilder();

        $query->select('DISTINCT product.entity_id AS identifier');
        $query->addSelect('product.entity_id AS productId');
        $query->addSelect('eav.attribute_id AS groupId');
        $query->addSelect('eav.attribute_code AS groupName');
        $query->addSelect('option_value.option_id AS optionId');
        $query->addSelect('option_value.value AS optionValue');

        $query->from($this->tablePrefix . 'catalog_product_entity', 'product');

        $query->innerJoin('product', $this->tablePrefix . 'catalog_product_relation', 'relation', 'relation.child_id = product.entity_id');
        $query->innerJoin('product', $this->tablePrefix . 'catalog_product_entity_int', 'entity_int', 'entity_int.entity_id = product.entity_id');
        $query->innerJoin('product', $this->tablePrefix . 'eav_attribute', 'eav', 'eav.attribute_id = entity_int.attribute_id AND eav.is_user_defined = 1');
        $query->innerJoin('product', $this->tablePrefix . 'catalog_eav_attribute', 'eav_settings', 'eav_settings.attribute_id = eav.attribute_id AND eav_settings.is_configurable = 1');
        $query->innerJoin('product', $this->tablePrefix . 'catalog_product_super_attribute', 'super_attr', 'super_attr.attribute_id = eav.attribute_id AND super_attr.product_id = relation.parent_id');
        $query->innerJoin('product', $this->tablePrefix . 'eav_attribute_option_value', 'option_value', 'option_value.option_id = entity_int.value AND option_value.store_id = 0');

        $query->where('product.entity_type_id = (SELECT entity_type_id FROM ' . $this->tablePrefix . 'eav_entity_type WHERE entity_type_code = \'catalog_product\') and product.entity_id IN (:ids)');
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

    private function fetchLocales(): array
    {
        $query = $this->connection->createQueryBuilder();

        $query->addSelect('scope_id AS store_id');
        $query->addSelect('value AS locale');
        $query->from($this->tablePrefix . 'core_config_data', 'locales');

        $query->orWhere('scope = \'stores\' AND path = \'general/locale/code\'');

        $locales = $query->execute()->fetchAll(\PDO::FETCH_KEY_PAIR);
        foreach ($locales as $storeId => &$locale) {
            $locale = str_replace('_', '-', $locale);
        }

        return $locales;
    }
}
