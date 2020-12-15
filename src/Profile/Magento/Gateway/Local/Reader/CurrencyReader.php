<?php declare(strict_types=1);
/*
 * (c) shopware AG <info@shopware.com>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Swag\MigrationMagento\Profile\Magento\Gateway\Local\Reader;

use Doctrine\DBAL\Driver\ResultStatement;
use SwagMigrationAssistant\Migration\MigrationContextInterface;

abstract class CurrencyReader extends AbstractReader
{
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

    protected function fetchCurrencies(): array
    {
        $query = $this->connection->createQueryBuilder();

        $query->from($this->tablePrefix . 'core_config_data', 'currency');
        $query->addSelect('scope_id as store_id');
        $query->addSelect('value');
        $query->andWhere('path = \'currency/options/allow\'');
        $query = $query->execute();

        if (!($query instanceof ResultStatement)) {
            return [];
        }

        $configurations = $query->fetchAll(\PDO::FETCH_GROUP | \PDO::FETCH_UNIQUE);

        $currencyConfig = [];
        foreach ($configurations as $config) {
            if (isset($config['value'])) {
                $currencyConfig = \array_merge($currencyConfig, \explode(',', $config['value']));
            }
        }

        return \array_values(\array_unique($currencyConfig));
    }

    protected function fetchBaseCurrency(): string
    {
        $query = $this->connection->createQueryBuilder();

        $query->from($this->tablePrefix . 'core_config_data', 'baseCurrency');
        $query->addSelect('value');
        $query->andwhere('path = \'currency/options/base\' AND scope = \'default\'');
        $query = $query->execute();

        if (!($query instanceof ResultStatement)) {
            return '';
        }

        $value = $query->fetch(\PDO::FETCH_COLUMN);

        if ($value === false) {
            return '';
        }

        return $value;
    }
}
