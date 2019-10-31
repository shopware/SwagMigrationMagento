<?php declare(strict_types=1);

namespace Swag\MigrationMagento\Profile\Magento\Gateway\Local\Reader;

use Swag\MigrationMagento\Profile\Magento\Magento19Profile;
use SwagMigrationAssistant\Migration\DataSelection\DefaultEntities;
use SwagMigrationAssistant\Migration\MigrationContextInterface;

class SeoUrlReader extends AbstractReader implements LocalReaderInterface
{
    public function supports(MigrationContextInterface $migrationContext): bool
    {
        return $migrationContext->getProfile() instanceof Magento19Profile
            && $migrationContext->getDataSet()::getEntity() === DefaultEntities::SEO_URL;
    }

    public function read(MigrationContextInterface $migrationContext, array $params = []): array
    {
        $this->setConnection($migrationContext);

        $fetchedSeoUrls = $this->mapData($this->fetchSeoUrls($migrationContext), [], ['seo']);
        $defaultLocale = $this->fetchDefaultLocale();

        foreach ($fetchedSeoUrls as &$seoUrl) {
            if (isset($seoUrl['locale'])) {
                $seoUrl['locale'] = str_replace('_', '-', $seoUrl['locale']);
            } else {
                $seoUrl['locale'] = $defaultLocale;
            }
        }

        return $fetchedSeoUrls;
    }

    protected function fetchSeoUrls(MigrationContextInterface $migrationContext): array
    {
        $query = $this->connection->createQueryBuilder();

        $query->from('core_url_rewrite', 'seo');
        $this->addTableSelection($query, 'core_url_rewrite', 'seo');

        $query->leftJoin('seo', 'core_config_data', 'locale', 'locale.scope_id = seo.store_id AND locale.scope = \'stores\' AND locale.path = \'general/locale/code\'');
        $query->addSelect('locale.value AS `seo.locale`');

        $query->setFirstResult($migrationContext->getOffset());
        $query->setMaxResults($migrationContext->getLimit());

        return $query->execute()->fetchAll(\PDO::FETCH_ASSOC);
    }
}
