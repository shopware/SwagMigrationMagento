<?php declare(strict_types=1);
/*
 * (c) shopware AG <info@shopware.com>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Swag\MigrationMagento\Profile\Magento2\Gateway\Local\Reader;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver\ResultStatement;
use Swag\MigrationMagento\Profile\Magento\Gateway\Local\Reader\ProductReader;

abstract class Magento2ProductReader extends ProductReader
{
    /**
     * @psalm-suppress DocblockTypeContradiction
     */
    protected function fetchProductMedia(array $ids): array
    {
        $sql = <<<SQL
SELECT
    mediaGalleryValue.entity_id AS productId,
    mediaGallery.value AS image,
    mediaGalleryValue.label AS description,
    mediaGalleryValue.position,
    IF(mediaGalleryValue.position=1, 1, 0) AS main
FROM
    {$this->tablePrefix}catalog_product_entity_media_gallery mediaGallery,
    {$this->tablePrefix}catalog_product_entity_media_gallery_value mediaGalleryValue
WHERE mediaGalleryValue.entity_id IN (?)
AND mediaGalleryValue.value_id = mediaGallery.value_id
ORDER BY productId, position;
SQL;

        $query = $this->connection->executeQuery($sql, [$ids], [Connection::PARAM_STR_ARRAY]);
        if (!($query instanceof ResultStatement)) {
            return [];
        }

        return $query->fetchAll(\PDO::FETCH_GROUP | \PDO::FETCH_ASSOC);
    }

    protected function fetchConfiguratorSettings(): array
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
        $query->innerJoin('product', $this->tablePrefix . 'catalog_eav_attribute', 'eav_settings', 'eav_settings.attribute_id = eav.attribute_id AND eav.frontend_input = \'select\'');
        $query->innerJoin('product', $this->tablePrefix . 'catalog_product_super_attribute', 'super_attr', 'super_attr.attribute_id = eav.attribute_id AND super_attr.product_id = product.entity_id');
        $query->innerJoin('product', $this->tablePrefix . 'eav_attribute_option_value', 'option_value', 'option_value.option_id = entity_int.value AND option_value.store_id = 0');

        $query->where('entity_int.entity_id IN (:ids)');
        $query->setParameter('ids', $this->combinedProductIds, Connection::PARAM_STR_ARRAY);

        $query = $query->execute();
        if (!($query instanceof ResultStatement)) {
            return [];
        }

        return $query->fetchAll(\PDO::FETCH_GROUP | \PDO::FETCH_ASSOC);
    }
}
