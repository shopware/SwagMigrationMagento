<?php declare(strict_types=1);
/*
 * (c) shopware AG <info@shopware.com>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Swag\MigrationMagento\Profile\Magento\Gateway\Local\Reader;

use Shopware\Core\Framework\DataAbstractionLayer\Doctrine\FetchModeHelper;
use Shopware\Core\Framework\Log\Package;
use SwagMigrationAssistant\Migration\MigrationContextInterface;

#[Package('services-settings')]
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
        $rows = $query->executeQuery()->fetchAllAssociative();

        $configurations = FetchModeHelper::groupUnique($rows);
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

        $value = $query->executeQuery()->fetchOne();
        if ($value === false) {
            return '';
        }

        return $value;
    }
}
