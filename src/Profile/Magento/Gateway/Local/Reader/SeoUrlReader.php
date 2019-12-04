<?php declare(strict_types=1);

namespace Swag\MigrationMagento\Profile\Magento\Gateway\Local\Reader;

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
FROM {$this->tablePrefix}core_url_rewrite
WHERE options IS NULL;
SQL;
        $total = (int) $this->connection->executeQuery($sql)->fetchColumn();

        return new TotalStruct(DefaultEntities::SEO_URL, $total);
    }

    protected function fetchSeoUrls(MigrationContextInterface $migrationContext): array
    {
        $query = $this->connection->createQueryBuilder();

        $query->from($this->tablePrefix . 'core_url_rewrite', 'seo');
        $this->addTableSelection($query, $this->tablePrefix . 'core_url_rewrite', 'seo');

        $query->leftJoin('seo', $this->tablePrefix . 'core_config_data', 'locale', 'locale.scope_id = seo.store_id AND locale.scope = \'stores\' AND locale.path = \'general/locale/code\'');
        $query->addSelect('locale.value AS `seo.locale`');

        $query->where('seo.options IS NULL');

        $query->setFirstResult($migrationContext->getOffset());
        $query->setMaxResults($migrationContext->getLimit());

        return $query->execute()->fetchAll(\PDO::FETCH_ASSOC);
    }
}
