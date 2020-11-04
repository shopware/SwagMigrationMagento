<?php declare(strict_types=1);
/*
 * (c) shopware AG <info@shopware.com>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Swag\MigrationMagento\Profile\Magento\Gateway\Local\Reader;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver\ResultStatement;
use SwagMigrationAssistant\Migration\DataSelection\DefaultEntities;
use SwagMigrationAssistant\Migration\MigrationContextInterface;
use SwagMigrationAssistant\Migration\TotalStruct;

abstract class CrossSellingReader extends AbstractReader
{
    public function read(MigrationContextInterface $migrationContext, array $params = []): array
    {
        $this->setConnection($migrationContext);
        $crossSelling = $this->fetchCrossSelling($migrationContext);
        $crossSelling = $this->mapData($crossSelling, [], ['link', 'type', 'sourceProductId']);

        $this->enrichWithPositionData($crossSelling, $migrationContext->getOffset());

        return $this->cleanupResultSet($crossSelling);
    }

    public function readTotal(MigrationContextInterface $migrationContext): ?TotalStruct
    {
        $this->setConnection($migrationContext);

        $sql = <<<SQL
SELECT COUNT(*)
FROM {$this->tablePrefix}catalog_product_link AS link
INNER JOIN {$this->tablePrefix}catalog_product_link_type AS type ON type.link_type_id = link.link_type_id
LEFT JOIN {$this->tablePrefix}catalog_product_relation AS sourceRelation ON link.product_id = sourceRelation.child_id
LEFT JOIN {$this->tablePrefix}catalog_product_entity AS product ON product.entity_id = IFNULL(sourceRelation.parent_id, link.product_id)
WHERE type.code IN ('up_sell', 'cross_sell', 'relation')
  AND IFNULL(sourceRelation.parent_id, link.product_id) != link.linked_product_id
  AND product.type_id IN (?);
SQL;
        $total = (int) $this->connection->executeQuery(
            $sql,
            [ProductReader::$ALLOWED_PRODUCT_TYPES],
            [Connection::PARAM_STR_ARRAY]
        )->fetchColumn();

        return new TotalStruct(DefaultEntities::CROSS_SELLING, $total);
    }

    protected function fetchCrossSelling(MigrationContextInterface $migrationContext): array
    {
        $query = $this->connection->createQueryBuilder();

        $query->from($this->tablePrefix . 'catalog_product_link', 'link');
        $this->addTableSelection($query, $this->tablePrefix . 'catalog_product_link', 'link');

        $query->innerJoin('link', $this->tablePrefix . 'catalog_product_link_type', 'type', 'type.link_type_id = link.link_type_id');
        $query->addSelect('type.code AS type');

        $query->leftJoin('link', $this->tablePrefix . 'catalog_product_relation', 'sourceRelation', 'link.product_id = sourceRelation.child_id');
        $query->addSelect('IFNULL(sourceRelation.parent_id, link.product_id) AS sourceProductId');

        $query->leftJoin('sourceRelation', $this->tablePrefix . 'catalog_product_entity', 'product', 'product.entity_id = IFNULL(sourceRelation.parent_id, link.product_id)');

        $query->where('type.code IN (\'up_sell\', \'cross_sell\', \'relation\')');
        $query->andWhere('IFNULL(sourceRelation.parent_id, link.product_id) != link.linked_product_id');
        $query->andWhere('product.type_id IN (:productType)');

        $query->orderBy('IFNULL(sourceRelation.parent_id, link.product_id), type.code');

        $query->setFirstResult($migrationContext->getOffset());
        $query->setMaxResults($migrationContext->getLimit());
        $query->setParameter('productType', ProductReader::$ALLOWED_PRODUCT_TYPES, Connection::PARAM_STR_ARRAY);

        $query = $query->execute();
        if (!($query instanceof ResultStatement)) {
            return [];
        }

        return $query->fetchAll(\PDO::FETCH_ASSOC);
    }

    private function enrichWithPositionData(array &$fetchedCrossSelling, int $offset): void
    {
        foreach ($fetchedCrossSelling as &$item) {
            $item['position'] = $offset++;
        }
    }
}
