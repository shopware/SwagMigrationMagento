<?php declare(strict_types=1);

namespace Swag\MigrationMagento\Profile\Magento\Gateway\Local\Reader;

use Doctrine\DBAL\Connection;
use Swag\MigrationMagento\Profile\Magento\Gateway\Local\StatementStruct;
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

        return $this->appendAssociatedData($fetchedProducts, $ids);
    }

    protected function fetchProducts(array $ids): array
    {
        $requiredAttributes = [
            'manufacturer',
            'cost'
        ];
        $statements = $this->generateDefaultAttributeStatements($requiredAttributes);

        $sql = <<<SQL
SELECT 
    product.*,
    stock.qty          as instock,
    stock.min_qty      as stockmin,
    stock.min_sale_qty as minpurchase,
    stock.max_sale_qty as maxpurchase
{$statements->getSelectStatement()}
FROM catalog_product_entity product
{$statements->getJoinStatement()}
-- join stocks
LEFT JOIN cataloginventory_stock_item stock
ON stock.product_id = product.entity_id
AND stock.stock_id = 1

LEFT JOIN tax_class tax
ON tax.class_id = tax_class_id.value

-- join parent for sorting
LEFT JOIN catalog_product_relation relation
ON product.entity_id = relation.child_id

WHERE product.entity_id IN (?)
ORDER BY relation.parent_id ASC;
SQL;
        return $this->connection->executeQuery($sql, [$ids], [Connection::PARAM_STR_ARRAY])->fetchAll(\PDO::FETCH_ASSOC);

    }

    private function generateDefaultAttributeStatements(array $requiredAttributes): StatementStruct
    {
        $defaultAttributeFields = $this->getDefaultProductAttributes();
        $attributes = array_merge(array_keys($defaultAttributeFields), $requiredAttributes);

        $selectStatement = $this->generateSelectStatement($defaultAttributeFields, $attributes);
        $joinStatement = $this->generateJoinStatement($defaultAttributeFields, $attributes);

        return new StatementStruct($selectStatement, $joinStatement);
    }

    private function getDefaultProductAttributes(): array
    {
        $sql = <<<SQL
SELECT 
    attribute.attribute_code,
    attribute.attribute_id as id,
    attribute.attribute_code as name,
    attribute.backend_type as type
FROM eav_attribute attribute, eav_entity_type entityType
WHERE attribute.entity_type_id = entityType.entity_type_id
AND entityType.entity_type_code = 'catalog_product'
AND attribute.is_user_defined = 0
AND attribute.backend_type != 'static'
AND attribute.frontend_input IS NOT NULL
ORDER BY name;
SQL;
        return $this->connection->executeQuery($sql)->fetchAll(\PDO::FETCH_GROUP|\PDO::FETCH_ASSOC|\PDO::FETCH_UNIQUE);
    }

    private function generateSelectStatement(array $defaultAttributeFields, array $attributes): string
    {
        $selectStatement = '';
        foreach ($attributes as $attribute) {
            if (!array_key_exists($attribute, $defaultAttributeFields)) {
                $selectStatement .= <<<SQL
, NULL as {$attribute}
SQL;
                continue;
            }
            $selectStatement .= <<<SQL
, {$attribute}.value AS {$attribute}
SQL;
        }

        return $selectStatement;
    }

    private function generateJoinStatement(array $defaultAttributeFields, array $attributes): string
    {
        $joinStatement = '';
        foreach ($attributes as $attribute) {
            if (!isset($defaultAttributeFields[$attribute])) {
                continue;
            }
            $attributeField = $defaultAttributeFields[$attribute];
            $tableName = 'catalog_product_entity_' . $attributeField['type'];
            $joinStatement .= <<<SQL
 LEFT JOIN {$tableName} {$attribute} 
 ON {$attribute}.attribute_id = {$attributeField['id']} 
 AND {$attribute}.entity_id = product.entity_id
SQL;
        }

        return $joinStatement;
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
        return $this->connection->executeQuery($sql, [$ids], [Connection::PARAM_STR_ARRAY])->fetchAll(\PDO::FETCH_GROUP|\PDO::FETCH_ASSOC);
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
        return $this->connection->executeQuery($sql, [$ids], [Connection::PARAM_STR_ARRAY])->fetchAll(\PDO::FETCH_GROUP|\PDO::FETCH_ASSOC);
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
        return $this->connection->executeQuery($sql, [$ids], [Connection::PARAM_STR_ARRAY])->fetchAll(\PDO::FETCH_GROUP|\PDO::FETCH_ASSOC);
    }

}
