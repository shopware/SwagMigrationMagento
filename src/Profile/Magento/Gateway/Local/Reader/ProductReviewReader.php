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

abstract class ProductReviewReader extends AbstractReader
{
    public function read(MigrationContextInterface $migrationContext, array $params = []): array
    {
        $this->setConnection($migrationContext);

        $fetchedProductReviews = $this->mapData($this->fetchProductReviews($migrationContext), [], ['detail']);
        $ids = \array_column($fetchedProductReviews, 'review_id');
        $fetchedRatings = $this->mapData($this->fetchRatings($ids), [], ['opt']);
        $defaultLocale = \str_replace('_', '-', $this->fetchDefaultLocale());

        foreach ($fetchedProductReviews as &$productReview) {
            $review_id = $productReview['review_id'];

            if (isset($fetchedRatings[$review_id])) {
                $productReview['ratings'] = $fetchedRatings[$review_id];
            }

            if (isset($productReview['locale'])) {
                $productReview['locale'] = \str_replace('_', '-', $productReview['locale']);
            } else {
                $productReview['locale'] = $defaultLocale;
            }
        }

        return $this->utf8ize($fetchedProductReviews);
    }

    public function readTotal(MigrationContextInterface $migrationContext): ?TotalStruct
    {
        $this->setConnection($migrationContext);

        $sql = <<<SQL
SELECT COUNT(*)
FROM {$this->tablePrefix}review AS review
INNER JOIN {$this->tablePrefix}review_entity AS re ON re.entity_id = review.entity_id AND re.entity_code = 'product'
LEFT JOIN {$this->tablePrefix}catalog_product_entity AS product ON review.entity_pk_value = product.entity_id
WHERE product.type_id IN (?)
SQL;
        $total = (int) $this->connection->executeQuery($sql, [ProductReader::$ALLOWED_PRODUCT_TYPES], [Connection::PARAM_STR_ARRAY])->fetchColumn();

        return new TotalStruct(DefaultEntities::PRODUCT_REVIEW, $total);
    }

    protected function fetchProductReviews(MigrationContextInterface $migrationContext): array
    {
        $query = $this->connection->createQueryBuilder();

        $query->select('review.created_at AS `detail.created_at`');
        $query->from($this->tablePrefix . 'review', 'review');

        $query->innerJoin('review', $this->tablePrefix . 'review_entity', 'entity', 'review.entity_id = entity.entity_id AND entity.entity_code = \'product\'');
        $query->addSelect('review.entity_pk_value AS `detail.productId`');

        $query->innerJoin('review', $this->tablePrefix . 'review_status', 'status', 'status.status_id = review.status_id');
        $query->addSelect('status.status_code AS `detail.status`');

        $query->innerJoin('review', $this->tablePrefix . 'review_detail', 'detail', 'detail.review_id = review.review_id');
        $this->addTableSelection($query, $this->tablePrefix . 'review_detail', 'detail');

        $query->leftJoin('review', $this->tablePrefix . 'catalog_product_entity', 'product', 'review.entity_pk_value = product.entity_id');

        $query->andWhere('product.type_id IN (:types)');
        $query->setParameter('types', ProductReader::$ALLOWED_PRODUCT_TYPES, Connection::PARAM_STR_ARRAY);
        $query->addOrderBy('review.entity_id');

        $query->setFirstResult($migrationContext->getOffset());
        $query->setMaxResults($migrationContext->getLimit());

        $query = $query->execute();
        if (!($query instanceof ResultStatement)) {
            return [];
        }

        return $query->fetchAll(\PDO::FETCH_ASSOC);
    }

    protected function fetchRatings(array $ids): array
    {
        $query = $this->connection->createQueryBuilder();

        $query->from($this->tablePrefix . 'rating_option_vote', 'opt');
        $query->addSelect('opt.review_id AS identifier');
        $this->addTableSelection($query, $this->tablePrefix . 'rating_option_vote', 'opt');

        $query->innerJoin('opt', $this->tablePrefix . 'rating', 'rating', 'rating.rating_id = opt.rating_id');
        $query->addSelect('rating.rating_code AS `opt.rating_code`');

        $query->andWhere('opt.review_id IN (:ids)');
        $query->setParameter('ids', $ids, Connection::PARAM_STR_ARRAY);

        $query = $query->execute();
        if (!($query instanceof ResultStatement)) {
            return [];
        }

        return $query->fetchAll(\PDO::FETCH_GROUP | \PDO::FETCH_ASSOC);
    }
}
