<?php declare(strict_types=1);
/*
 * (c) shopware AG <info@shopware.com>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Swag\MigrationMagento\Profile\Magento\Gateway\Local\Reader;

use Doctrine\DBAL\Driver\ResultStatement;
use SwagMigrationAssistant\Migration\DataSelection\DefaultEntities;
use SwagMigrationAssistant\Migration\MigrationContextInterface;
use SwagMigrationAssistant\Migration\TotalStruct;

abstract class ProductOptionRelationReader extends AbstractReader
{
    public function read(MigrationContextInterface $migrationContext): array
    {
        $this->setConnection($migrationContext);
        $optionRelations = $this->fetchOptions($migrationContext);

        return $this->cleanupResultSet($optionRelations);
    }

    public function readTotal(MigrationContextInterface $migrationContext): ?TotalStruct
    {
        $this->setConnection($migrationContext);

        $query = $this->connection->createQueryBuilder();

        $query->select('COUNT(DISTINCT CONCAT(product.entity_id, \'_\', option_value.option_id))');

        $query->from($this->tablePrefix . 'catalog_product_entity', 'product');

        $query->innerJoin('product', $this->tablePrefix . 'catalog_product_relation', 'relation', 'relation.child_id = product.entity_id');
        $query->innerJoin('product', $this->tablePrefix . 'catalog_product_entity_int', 'entity_int', 'entity_int.entity_id = product.entity_id');
        $query->innerJoin('product', $this->tablePrefix . 'eav_attribute', 'eav', 'eav.attribute_id = entity_int.attribute_id AND eav.is_user_defined = 1');
        $query->innerJoin('product', $this->tablePrefix . 'catalog_eav_attribute', 'eav_settings', 'eav_settings.attribute_id = eav.attribute_id AND eav_settings.is_configurable = 1');
        $query->innerJoin('product', $this->tablePrefix . 'catalog_product_super_attribute', 'super_attr', 'super_attr.attribute_id = eav.attribute_id AND super_attr.product_id = relation.parent_id');
        $query->innerJoin('product', $this->tablePrefix . 'eav_attribute_option', 'option_value', 'option_value.option_id = entity_int.value AND option_value.attribute_id = eav.attribute_id');

        $query = $query->execute();
        if (!($query instanceof ResultStatement)) {
            $total = 0;
        } else {
            $total = (int) $query->fetchColumn();
        }

        return new TotalStruct(DefaultEntities::PRODUCT_OPTION_RELATION, $total);
    }

    protected function fetchOptions(MigrationContextInterface $migrationContext): array
    {
        $query = $this->connection->createQueryBuilder();

        $query->addSelect('DISTINCT product.entity_id');
        $query->addSelect('option_value.option_id');

        $query->from($this->tablePrefix . 'catalog_product_entity', 'product');

        $query->innerJoin('product', $this->tablePrefix . 'catalog_product_relation', 'relation', 'relation.child_id = product.entity_id');
        $query->innerJoin('product', $this->tablePrefix . 'catalog_product_entity_int', 'entity_int', 'entity_int.entity_id = product.entity_id');
        $query->innerJoin('product', $this->tablePrefix . 'eav_attribute', 'eav', 'eav.attribute_id = entity_int.attribute_id AND eav.is_user_defined = 1');
        $query->innerJoin('product', $this->tablePrefix . 'catalog_eav_attribute', 'eav_settings', 'eav_settings.attribute_id = eav.attribute_id AND eav_settings.is_configurable = 1');
        $query->innerJoin('product', $this->tablePrefix . 'catalog_product_super_attribute', 'super_attr', 'super_attr.attribute_id = eav.attribute_id AND super_attr.product_id = relation.parent_id');
        $query->innerJoin('product', $this->tablePrefix . 'eav_attribute_option', 'option_value', 'option_value.option_id = entity_int.value AND option_value.attribute_id = eav.attribute_id');

        $query->setFirstResult($migrationContext->getOffset());
        $query->setMaxResults($migrationContext->getLimit());

        $query = $query->execute();
        if (!($query instanceof ResultStatement)) {
            return [];
        }

        return $query->fetchAll(\PDO::FETCH_ASSOC);
    }
}
