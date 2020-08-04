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

abstract class SeoUrlReader extends AbstractReader
{
    public function read(MigrationContextInterface $migrationContext, array $params = []): array
    {
        $this->setConnection($migrationContext);

        $fetchedSeoUrls = $this->mapData($this->fetchSeoUrls($migrationContext), [], ['seo']);
        $defaultLocale = \str_replace('_', '-', $this->fetchDefaultLocale());

        foreach ($fetchedSeoUrls as &$seoUrl) {
            if (isset($seoUrl['locale'])) {
                $seoUrl['locale'] = \str_replace('_', '-', $seoUrl['locale']);
            } else {
                $seoUrl['locale'] = $defaultLocale;
            }
        }

        return $fetchedSeoUrls;
    }

    public function readTotal(MigrationContextInterface $migrationContext): ?TotalStruct
    {
        $this->setConnection($migrationContext);

        $sql = <<<SQL
SELECT COUNT(*)
FROM {$this->tablePrefix}core_url_rewrite seo
LEFT JOIN {$this->tablePrefix}catalog_product_entity AS product ON product.entity_id = seo.product_id
WHERE seo.options IS NULL
AND (product.type_id IN (?) OR (seo.product_id IS NULL AND seo.category_id IS NOT NULL));
SQL;
        $total = (int) $this->connection->executeQuery($sql, [ProductReader::$ALLOWED_PRODUCT_TYPES], [Connection::PARAM_STR_ARRAY])->fetchColumn();

        return new TotalStruct(DefaultEntities::SEO_URL, $total);
    }

    protected function fetchSeoUrls(MigrationContextInterface $migrationContext): array
    {
        $query = $this->connection->createQueryBuilder();

        $query->from($this->tablePrefix . 'core_url_rewrite', 'seo');
        $this->addTableSelection($query, $this->tablePrefix . 'core_url_rewrite', 'seo');

        $query->leftJoin('seo', $this->tablePrefix . 'catalog_product_entity', 'product', 'seo.product_id = product.entity_id');

        $query->where('seo.options IS NULL');
        $query->andWhere('product.type_id IN (:types) OR (seo.product_id IS NULL AND seo.category_id IS NOT NULL)');
        $query->setParameter('types', ProductReader::$ALLOWED_PRODUCT_TYPES, Connection::PARAM_STR_ARRAY);
        $query->addOrderBy('seo.url_rewrite_id');

        $query->setFirstResult($migrationContext->getOffset());
        $query->setMaxResults($migrationContext->getLimit());

        $query = $query->execute();
        if (!($query instanceof ResultStatement)) {
            return [];
        }

        return $query->fetchAll(\PDO::FETCH_ASSOC);
    }
}
