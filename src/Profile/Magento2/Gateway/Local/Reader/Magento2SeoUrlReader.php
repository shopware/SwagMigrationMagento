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
use Swag\MigrationMagento\Profile\Magento\Gateway\Local\Reader\SeoUrlReader;
use SwagMigrationAssistant\Migration\DataSelection\DefaultEntities;
use SwagMigrationAssistant\Migration\MigrationContextInterface;
use SwagMigrationAssistant\Migration\TotalStruct;

abstract class Magento2SeoUrlReader extends SeoUrlReader
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

            if (isset($seoUrl['entity_type'], $seoUrl['entity_id'])) {
                $this->setCategoryId($seoUrl);
                $this->setProductData($seoUrl);
            }
        }

        return $fetchedSeoUrls;
    }

    public function readTotal(MigrationContextInterface $migrationContext): ?TotalStruct
    {
        $this->setConnection($migrationContext);

        $sql = <<<SQL
SELECT COUNT(*)
FROM {$this->tablePrefix}url_rewrite seo
LEFT JOIN {$this->tablePrefix}catalog_product_entity AS product ON seo.entity_id = product.entity_id AND entity_type = 'product'
WHERE (product.type_id IN (?) AND entity_type = 'product') OR entity_type != 'product';
SQL;
        $total = (int) $this->connection->executeQuery($sql, [ProductReader::$ALLOWED_PRODUCT_TYPES], [Connection::PARAM_STR_ARRAY])->fetchColumn();

        return new TotalStruct(DefaultEntities::SEO_URL, $total);
    }

    protected function fetchSeoUrls(MigrationContextInterface $migrationContext): array
    {
        $query = $this->connection->createQueryBuilder();

        $query->from($this->tablePrefix . 'url_rewrite', 'seo');
        $this->addTableSelection($query, $this->tablePrefix . 'url_rewrite', 'seo');

        $query->leftJoin('seo', $this->tablePrefix . 'catalog_product_entity', 'product', 'seo.entity_id = product.entity_id AND entity_type = \'product\'');

        $query->andWhere('(product.type_id IN (:types) AND entity_type = \'product\') OR entity_type != \'product\'');
        $query->setParameter('types', ProductReader::$ALLOWED_PRODUCT_TYPES, Connection::PARAM_STR_ARRAY);

        $query->setFirstResult($migrationContext->getOffset());
        $query->setMaxResults($migrationContext->getLimit());
        $query = $query->execute();

        if (!($query instanceof ResultStatement)) {
            return [];
        }

        return $query->fetchAll(\PDO::FETCH_ASSOC);
    }

    protected function setCategoryId(array &$seoUrl): void
    {
        if ($seoUrl['entity_type'] !== 'category') {
            return;
        }

        $seoUrl['category_id'] = $seoUrl['entity_id'];
    }

    protected function setProductData(array &$seoUrl): void
    {
        if ($seoUrl['entity_type'] !== 'product') {
            return;
        }

        $seoUrl['product_id'] = $seoUrl['entity_id'];

        if (empty($seoUrl['metadata'])) {
            return;
        }

        try {
            $json = \json_decode($seoUrl['metadata'], true);
        } catch (\Error $error) {
            return;
        }

        if (isset($json['category_id'])) {
            $seoUrl['category_id'] = $json['category_id'];
        }
    }
}
