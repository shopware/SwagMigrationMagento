<?php declare(strict_types=1);
/*
 * (c) shopware AG <info@shopware.com>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Swag\MigrationMagento\Profile\Magento\Gateway\Local\Reader;

use Swag\MigrationMagento\Profile\Magento\Gateway\Local\Magento19LocalGateway;
use Swag\MigrationMagento\Profile\Magento\Magento19Profile;
use SwagMigrationAssistant\Migration\DataSelection\DefaultEntities;
use SwagMigrationAssistant\Migration\MigrationContextInterface;

class CurrencyReader extends AbstractReader
{
    public function supports(MigrationContextInterface $migrationContext): bool
    {
        return $migrationContext->getProfile() instanceof Magento19Profile
            && $migrationContext->getGateway()->getName() === Magento19LocalGateway::GATEWAY_NAME
            && $migrationContext->getDataSet()::getEntity() === DefaultEntities::CURRENCY;
    }

    public function read(MigrationContextInterface $migrationContext, array $params = []): array
    {
        $this->setConnection($migrationContext);
        $fetchedCurrencies = $this->fetchCurrencies();
        $baseCurrency = $this->fetchBaseCurrency();

        $currencies = [];
        foreach ($fetchedCurrencies as $currency) {
            $currencies[] = [
                'isBaseCurrency' => $baseCurrency === $currency,
                'isoCode' => $currency,
            ];
        }

        return $currencies;
    }

    private function fetchCurrencies(): array
    {
        $query = $this->connection->createQueryBuilder();

        $query->from($this->tablePrefix . 'core_config_data', 'currency');
        $query->addSelect('scope_id as store_id');
        $query->addSelect('value');
        $query->andWhere('path = \'currency/options/allow\'');

        $configurations = $query->execute()->fetchAll(\PDO::FETCH_GROUP | \PDO::FETCH_UNIQUE);

        $currencyConfig = [];
        foreach ($configurations as $key => $config) {
            if (isset($config['value'])) {
                $currencyConfig = array_merge($currencyConfig, explode(',', $config['value']));
            }
        }

        return array_values(array_unique($currencyConfig));
    }

    private function fetchBaseCurrency(): string
    {
        $query = $this->connection->createQueryBuilder();

        $query->from($this->tablePrefix . 'core_config_data', 'baseCurrency');
        $query->addSelect('value');
        $query->andwhere('path = \'currency/options/base\' AND scope = \'default\'');

        $baseCurrency = $query->execute()->fetch(\PDO::FETCH_COLUMN);

        return $baseCurrency;
    }
}
