<?php declare(strict_types=1);

namespace Swag\MigrationMagento\Profile\Magento\Gateway\Local\Reader;

use Swag\MigrationMagento\Profile\Magento\Magento19Profile;
use SwagMigrationAssistant\Migration\DataSelection\DefaultEntities;
use SwagMigrationAssistant\Migration\MigrationContextInterface;

class CurrencyReader extends AbstractReader implements LocalReaderInterface
{
    public function supports(MigrationContextInterface $migrationContext): bool
    {
        return $migrationContext->getProfile() instanceof Magento19Profile
            && $migrationContext->getDataSet()::getEntity() === DefaultEntities::CURRENCY;
    }

    public function read(MigrationContextInterface $migrationContext, array $params = []): array
    {
        $this->setConnection($migrationContext);
        $fetchedCurrencies = $this->fetchCurrencies();

        $currencies = [];
        foreach ($fetchedCurrencies as $currency) {
            $currencies[]['isoCode'] = $currency;
        }

        return $currencies;
    }

    private function fetchCurrencies(): array
    {
        $query = $this->connection->createQueryBuilder();

        $query->from('core_config_data', 'currency');
        $query->addSelect('scope_id as store_id');
        $query->addSelect('value');
        $query->andwhere('path = \'currency/options/allow\'');

        $configurations = $query->execute()->fetchAll(\PDO::FETCH_GROUP | \PDO::FETCH_UNIQUE);

        $currencyConfig = [];
        foreach ($configurations as $key => $config) {
            if (isset($config['value'])) {
                $currencyConfig = array_merge($currencyConfig, explode(',', $config['value']));
            }
        }

        return array_values(array_unique($currencyConfig));
    }
}
