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

class SeoUrlReader extends AbstractReader
{
    public function supports(MigrationContextInterface $migrationContext): bool
    {
        return $migrationContext->getProfile() instanceof Magento19Profile
            && $migrationContext->getGateway()->getName() === Magento19LocalGateway::GATEWAY_NAME
            && $migrationContext->getDataSet()::getEntity() === DefaultEntities::SEO_URL;
    }

    public function supportsTotal(MigrationContextInterface $migrationContext): bool
    {
        return $migrationContext->getProfile() instanceof Magento19Profile
            && $migrationContext->getGateway()->getName() === Magento19LocalGateway::GATEWAY_NAME;
    }

    public function read(MigrationContextInterface $migrationContext, array $params = []): array
    {
        $this->setConnection($migrationContext);

        $fetchedSeoUrls = $this->mapData($this->fetchSeoUrls($migrationContext), [], ['seo']);
        $defaultLocale = str_replace('_', '-', $this->fetchDefaultLocale());

        foreach ($fetchedSeoUrls as &$seoUrl) {
            if (isset($seoUrl['locale'])) {
                $seoUrl['locale'] = str_replace('_', '-', $seoUrl['locale']);
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
WHERE seo.options IS NULL AND product.type_id IN (?);
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
        $query->andWhere('product.type_id IN (:types)');
        $query->setParameter('types', ProductReader::$ALLOWED_PRODUCT_TYPES, Connection::PARAM_STR_ARRAY);

        $query->setFirstResult($migrationContext->getOffset());
        $query->setMaxResults($migrationContext->getLimit());

        return $query->execute()->fetchAll(\PDO::FETCH_ASSOC);
    }
}
