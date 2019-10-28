<?php declare(strict_types=1);

namespace Swag\MigrationMagento\Profile\Magento\Gateway\Local\Reader;

use Swag\MigrationMagento\Profile\Magento\Magento19Profile;
use SwagMigrationAssistant\Migration\DataSelection\DefaultEntities;
use SwagMigrationAssistant\Migration\MigrationContextInterface;

class LanguageReader extends AbstractReader implements LocalReaderInterface
{
    public function supports(MigrationContextInterface $migrationContext): bool
    {
        return $migrationContext->getProfile() instanceof Magento19Profile
            && $migrationContext->getDataSet()::getEntity() === DefaultEntities::LANGUAGE;
    }

    public function read(MigrationContextInterface $migrationContext, array $params = []): array
    {
        $this->setConnection($migrationContext);

        return $this->fetchLocales();
    }

    protected function fetchLocales(): array
    {
        $query = $this->connection->createQueryBuilder();

        $query->from('core_config_data', 'locales');
        $query->addSelect('scope_id as store_id');
        $query->addSelect('value as locale');

        $query->orWhere('scope = \'stores\' AND path = \'general/locale/code\'');

        $configurations = $query->execute()->fetchAll(\PDO::FETCH_GROUP | \PDO::FETCH_UNIQUE);

        $storeConfigs = [];
        foreach ($configurations as $key => $storeConfig) {
            $storeConfigs[$key]['locale'] = str_replace('_', '-', $storeConfig['locale']);
        }

        return $storeConfigs;
    }
}
