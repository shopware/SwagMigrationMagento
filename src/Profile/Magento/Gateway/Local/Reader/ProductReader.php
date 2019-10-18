<?php declare(strict_types=1);

namespace Swag\MigrationMagento\Profile\Magento\Gateway\Local\Reader;

use Doctrine\DBAL\Connection;
use Swag\MigrationMagento\Profile\Magento\Magento19Profile;
use SwagMigrationAssistant\Migration\DataSelection\DefaultEntities;
use SwagMigrationAssistant\Migration\MigrationContextInterface;

class ProductReader extends AbstractReader implements LocalReaderInterface
{
    public function supports(MigrationContextInterface $migrationContext): bool
    {
        return $migrationContext->getProfile() instanceof Magento19Profile
            && $migrationContext->getDataSet()::getEntity() === DefaultEntities::PRODUCT;
    }

    public function read(MigrationContextInterface $migrationContext, array $params = []): array
    {
        $this->setConnection($migrationContext);

        $ids = $this->fetchIdentifiers('catalog_product_entity', 'entity_id', $migrationContext->getOffset(), $migrationContext->getLimit());
        $fetchedProducts = $this->fetchProducts($ids);
        $this->appendDefaultAttributes($ids, $fetchedProducts);

        return $this->appendAssociatedData($fetchedProducts, $ids);
    }

    protected function fetchProducts(array $ids): array
    {
        $sql = <<<SQL
SELECT
    product.*,
    stock.qty          as instock,
    stock.min_qty      as stockmin,
    stock.min_sale_qty as minpurchase,
    stock.max_sale_qty as maxpurchase
    
FROM catalog_product_entity product

-- join stocks
LEFT JOIN cataloginventory_stock_item stock
ON stock.product_id = product.entity_id
AND stock.stock_id = 1

-- join parent for sorting
LEFT JOIN catalog_product_relation relation
ON product.entity_id = relation.child_id

WHERE product.entity_id IN (?)
ORDER BY relation.parent_id ASC;
SQL;

        return $this->connection->executeQuery($sql, [$ids], [Connection::PARAM_STR_ARRAY])->fetchAll(\PDO::FETCH_ASSOC);
    }

    private function appendDefaultAttributes(array $ids, array &$fetchedProducts): void
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
FROM catalog_product_entity AS product
LEFT JOIN eav_attribute AS attribute 
    ON product.entity_type_id = attribute.entity_type_id
LEFT JOIN catalog_product_entity_varchar AS product_varchar 
    ON product.entity_id = product_varchar.entity_id 
    AND attribute.attribute_id = product_varchar.attribute_id 
    AND attribute.backend_type = 'varchar'
LEFT JOIN catalog_product_entity_int AS product_int 
    ON product.entity_id = product_int.entity_id 
    AND attribute.attribute_id = product_int.attribute_id 
    AND attribute.backend_type = 'int'
LEFT JOIN catalog_product_entity_text AS product_text 
    ON product.entity_id = product_text.entity_id 
    AND attribute.attribute_id = product_text.attribute_id 
    AND attribute.backend_type = 'text'
LEFT JOIN catalog_product_entity_decimal AS product_decimal 
    ON product.entity_id = product_decimal.entity_id 
    AND attribute.attribute_id = product_decimal.attribute_id 
    AND attribute.backend_type = 'decimal'
LEFT JOIN catalog_product_entity_datetime AS product_datetime 
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

        error_log(print_r($fetchedAttributes, true) . "\n", 3, '/Users/h.kassner/Development/log/debug.log');

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

    private function appendAssociatedData(array $fetchedProducts, array $ids)
    {
        $resultSet = [];

        $categories = $this->fetchProductCategories($ids);
        $media = $this->fetchProductMedia($ids);
        $prices = $this->fetchProductPrices($ids);

        foreach ($fetchedProducts as &$product) {
            if (isset($categories[$product['entity_id']])) {
                $product['categories'] = $categories[$product['entity_id']];
            }
            if (isset($media[$product['entity_id']])) {
                $product['media'] = $media[$product['entity_id']];
            }
            if (isset($prices[$product['entity_id']])) {
                $product['prices'] = $prices[$product['entity_id']];
            }

            $resultSet[] = $product;
        }

        return $resultSet;
    }

    private function fetchProductCategories(array $ids): array
    {
        $sql = <<<SQL
SELECT 
    productCategory.product_id,
    productCategory.product_id as productId,
    productCategory.category_id as categoryId
FROM catalog_category_product productCategory
WHERE productCategory.product_id IN (?)
ORDER BY productCategory.position
SQL;

        return $this->connection->executeQuery($sql, [$ids], [Connection::PARAM_STR_ARRAY])->fetchAll(\PDO::FETCH_GROUP | \PDO::FETCH_ASSOC);
    }

    private function fetchProductMedia(array $ids)
    {
        $sql = <<<SQL
SELECT
    mediaGallery.entity_id as productId,
    mediaGallery.value as image,
    mediaGalleryValue.label as description,
    mediaGalleryValue.position,
    IF(mediaGalleryValue.position=1, 1, 0) as main
FROM 
    catalog_product_entity_media_gallery mediaGallery,
    catalog_product_entity_media_gallery_value mediaGalleryValue
WHERE mediaGallery.entity_id IN (?)
AND mediaGalleryValue.value_id = mediaGallery.value_id
ORDER BY productId, position;
SQL;

        return $this->connection->executeQuery($sql, [$ids], [Connection::PARAM_STR_ARRAY])->fetchAll(\PDO::FETCH_GROUP | \PDO::FETCH_ASSOC);
    }

    private function fetchProductPrices(array $ids): array
    {
        $sql = <<<SQL
SELECT
    price.entity_id as productId,
    price.qty as fromQty,
    price.value as price,
    price.customer_group_id as customerGroup
FROM catalog_product_entity_tier_price price
WHERE price.entity_id IN (?)
ORDER BY productId, customerGroup, fromQty
SQL;

        return $this->connection->executeQuery($sql, [$ids], [Connection::PARAM_STR_ARRAY])->fetchAll(\PDO::FETCH_GROUP | \PDO::FETCH_ASSOC);
    }
}
