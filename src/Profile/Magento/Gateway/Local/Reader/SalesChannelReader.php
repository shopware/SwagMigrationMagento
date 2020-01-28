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

class SalesChannelReader extends AbstractReader
{
    public function supports(MigrationContextInterface $migrationContext): bool
    {
        return $migrationContext->getProfile() instanceof Magento19Profile
            && $migrationContext->getGateway()->getName() === Magento19LocalGateway::GATEWAY_NAME
            && $migrationContext->getDataSet()::getEntity() === DefaultEntities::SALES_CHANNEL;
    }

    public function supportsTotal(MigrationContextInterface $migrationContext): bool
    {
        return $migrationContext->getProfile() instanceof Magento19Profile
            && $migrationContext->getGateway()->getName() === Magento19LocalGateway::GATEWAY_NAME;
    }

    public function read(MigrationContextInterface $migrationContext, array $params = []): array
    {
        $this->setConnection($migrationContext);

        $fetchedWebsites = $this->mapData($this->fetchWebsites($migrationContext), [], ['website']);
        $ids = array_keys($fetchedWebsites);

        $stores = $this->mapData($this->fetchStores($ids), [], ['store']);
        $storeIds = [];
        array_map(function ($website) use (&$storeIds): void {
            array_map(function ($store) use (&$storeIds): void {
                $storeId = $store['store_id'];
                $storeIds[$storeId] = $storeId;
            }, $website);
        }, $stores);

        $storeGroups = $this->mapData($this->fetchStoreGroups($ids), [], ['storeGroup']);
        $carriers = $this->fetchCarriers();
        $payments = $this->fetchPayments();

        $storeAllowedCurrencies = $this->fetchStoreCurrencies($storeIds);
        $storeCountryConfig = $this->fetchStoreCountryConfig($storeIds);
        $locales = $this->fetchLocales($storeIds);

        $defaults = $this->fetchDefaults();

        foreach ($fetchedWebsites as &$website) {
            $websiteId = $website['website_id'];

            $website['currencies'] = $defaults['defaultAllowedCurrencies'];
            $website['countries'] = $defaults['defaultAllowedCountries'];
            $website['locales'][] = $defaults['defaultLocale'];

            $website['defaultCurrency'] = $defaults['defaultCurrency'];
            $website['defaultCountry'] = $defaults['defaultCountry'];
            $website['defaultLocale'] = $defaults['defaultLocale'];
            if (isset($stores[$websiteId])) {
                $website['stores'] = $stores[$websiteId];

                foreach ($website['stores'] as $store) {
                    $storeId = $store['store_id'];

                    if (isset($storeAllowedCurrencies[$storeId])) {
                        $website['currencies'] = array_merge($website['currencies'], $storeAllowedCurrencies[$storeId]);
                    }

                    if (isset($storeCountryConfig[$storeId])) {
                        $allowedCountries = [];
                        if (isset($storeCountryConfig[$storeId]['allowedCountries'])) {
                            $allowedCountries = $storeCountryConfig[$storeId]['allowedCountries'];
                        }

                        $website['countries'] = array_merge($website['countries'], $allowedCountries);
                    }

                    if (isset($locales['stores'][$storeId])) {
                        $website['locales'][] = $locales['stores'][$storeId];
                    }
                }

                $website['locales'] = array_unique($website['locales']);
                $website['currencies'] = array_unique($website['currencies']);
            }

            if (isset($storeGroups[$websiteId])) {
                $website['store_group'] = $storeGroups[$websiteId];
            }

            $website['carriers'] = $carriers;
            $website['payments'] = $payments;
        }

        return $this->cleanupResultSet($fetchedWebsites);
    }

    public function readTotal(MigrationContextInterface $migrationContext): ?TotalStruct
    {
        $this->setConnection($migrationContext);

        $sql = <<<SQL
SELECT COUNT(*)
FROM {$this->tablePrefix}core_website
WHERE website_id != 0;
SQL;
        $total = (int) $this->connection->executeQuery($sql)->fetchColumn();

        return new TotalStruct(DefaultEntities::SALES_CHANNEL, $total);
    }

    private function fetchWebsites(MigrationContextInterface $migrationContext): array
    {
        $query = $this->connection->createQueryBuilder();

        $query->from($this->tablePrefix . 'core_website', 'website');
        $query->addSelect('website.website_id as identifier');
        $this->addTableSelection($query, $this->tablePrefix . 'core_website', 'website');

        $query->andWhere('website_id != 0');
        $query->setFirstResult($migrationContext->getOffset());
        $query->setMaxResults($migrationContext->getLimit());

        return $query->execute()->fetchAll(\PDO::FETCH_GROUP | \PDO::FETCH_UNIQUE);
    }

    private function fetchDefaults(): array
    {
        $query = $this->connection->createQueryBuilder();

        $query->from($this->tablePrefix . 'core_config_data', 'defaultCurrency');
        $query->addSelect('defaultCurrency.value AS defaultCurrency');

        $query->andWhere('defaultCurrency.scope = \'default\'');
        $query->andWhere('defaultCurrency.path = \'currency/options/default\'');

        $query->leftJoin(
            'defaultCurrency',
            $this->tablePrefix . 'core_config_data',
            'defaultAllowedCurrencies',
            'defaultAllowedCurrencies.scope = \'default\' AND defaultAllowedCurrencies.path = \'currency/options/allow\''
        );
        $query->addSelect('defaultAllowedCurrencies.value AS defaultAllowedCurrencies');

        $query->leftJoin(
            'defaultCurrency',
            $this->tablePrefix . 'core_config_data',
            'defaultCountry',
            'defaultCountry.scope = \'default\' AND defaultCountry.path = \'general/country/default\''
        );
        $query->addSelect('defaultCountry.value AS defaultCountry');

        $query->leftJoin(
            'defaultCurrency',
            $this->tablePrefix . 'core_config_data',
            'defaultAllowedCountries',
            'defaultAllowedCountries.scope = \'default\' AND defaultAllowedCountries.path = \'general/country/allow\''
        );
        $query->addSelect('defaultAllowedCountries.value AS defaultAllowedCountries');

        $query->leftJoin(
            'defaultCurrency',
            $this->tablePrefix . 'core_config_data',
            'defaultLocale',
            'defaultLocale.scope = \'default\' AND defaultLocale.path = \'general/locale/code\''
        );
        $query->addSelect('defaultLocale.value AS defaultLocale');

        $defaults = $query->execute()->fetch(\PDO::FETCH_ASSOC);

        if ($defaults['defaultAllowedCurrencies'] === null) {
            $defaults['defaultAllowedCountries'] = '';
        }
        $defaults['defaultAllowedCurrencies'] = explode(',', $defaults['defaultAllowedCurrencies']);

        if ($defaults['defaultAllowedCountries'] === null) {
            $defaults['defaultAllowedCountries'] = '';
        }
        $defaults['defaultAllowedCountries'] = explode(',', $defaults['defaultAllowedCountries']);

        if ($defaults['defaultLocale'] === null) {
            $defaults['defaultLocale'] = '';
        }
        $defaults['defaultLocale'] = str_replace('_', '-', $defaults['defaultLocale']);

        return $defaults;
    }

    private function fetchStores(array $ids): array
    {
        $query = $this->connection->createQueryBuilder();

        $query->from($this->tablePrefix . 'core_store', 'store');
        $query->addSelect('store.website_id as website');
        $this->addTableSelection($query, $this->tablePrefix . 'core_store', 'store');

        $query->andWhere('website_id in (:ids)');
        $query->setParameter('ids', $ids, Connection::PARAM_INT_ARRAY);

        return $query->execute()->fetchAll(\PDO::FETCH_GROUP);
    }

    private function fetchStoreGroups(array $ids): array
    {
        $query = $this->connection->createQueryBuilder();

        $query->from($this->tablePrefix . 'core_store_group', 'storeGroup');
        $query->addSelect('storeGroup.website_id as website');
        $this->addTableSelection($query, $this->tablePrefix . 'core_store_group', 'storeGroup');

        $query->andWhere('website_id in (:ids)');
        $query->setParameter('ids', $ids, Connection::PARAM_INT_ARRAY);

        return $query->execute()->fetchAll(\PDO::FETCH_GROUP | \PDO::FETCH_UNIQUE);
    }

    private function fetchCarriers(): array
    {
        $sql = <<<SQL
SELECT carrier.*
FROM (
          SELECT
            REPLACE(REPLACE(config.path, '/title', ''), 'carriers/', '') AS carrier_id,
            config.*
          FROM {$this->tablePrefix}core_config_data config
          WHERE path LIKE 'carriers/%/title' AND scope = 'default'
      ) AS carrier,
      (
          SELECT
            REPLACE(REPLACE(config.path, '/active', ''), 'carriers/', '') AS carrier_id
          FROM {$this->tablePrefix}core_config_data config
          WHERE path LIKE 'carriers/%/active' AND scope = 'default' AND value = true
      ) AS carrier_active
WHERE carrier.carrier_id = carrier_active.carrier_id;
SQL;

        return $this->connection->executeQuery($sql)->fetchAll(\PDO::FETCH_ASSOC);
    }

    private function fetchPayments(): array
    {
        $sql = <<<SQL
SELECT payment.*
FROM (
      SELECT
             REPLACE(REPLACE(config.path, '/title', ''), 'payment/', '') AS payment_id,
             config.*
      FROM {$this->tablePrefix}core_config_data config
      WHERE path LIKE 'payment/%/title' AND scope = 'default'
      ) AS payment,
      (
      SELECT
             REPLACE(REPLACE(config.path, '/active', ''), 'payment/', '') AS payment_id
      FROM {$this->tablePrefix}core_config_data config
      WHERE path LIKE 'payment/%/active' AND scope = 'default' AND value = true
      ) AS payment_active
WHERE payment.payment_id = payment_active.payment_id;
SQL;

        return $this->connection->executeQuery($sql)->fetchAll(\PDO::FETCH_ASSOC);
    }

    private function fetchStoreCurrencies(array $storeIds): array
    {
        $query = $this->connection->createQueryBuilder();

        $query->from($this->tablePrefix . 'core_config_data', 'currency');
        $query->addSelect('scope_id AS store_id');
        $query->addSelect('value AS currencies');

        $query->andWhere('scope = \'stores\'');
        $query->andWhere('scope_id IN (:storeId)');
        $query->andWhere('path = \'currency/options/allow\'');
        $query->setParameter('storeId', $storeIds, Connection::PARAM_STR_ARRAY);

        $storeCurrencies = $query->execute()->fetchAll(\PDO::FETCH_GROUP | \PDO::FETCH_UNIQUE);

        foreach ($storeCurrencies as $key => $storeCurrency) {
            if (isset($storeCurrency['currencies'])) {
                $storeCurrencies[$key] = explode(',', $storeCurrency['currencies']);
            }
        }

        return $storeCurrencies;
    }

    private function fetchStoreCountryConfig(array $storeIds): array
    {
        $query = $this->connection->createQueryBuilder();

        $query->from($this->tablePrefix . 'core_config_data', 'currency');
        $query->addSelect('scope_id AS store_id');
        $query->addSelect('path');
        $query->addSelect('value');

        $query->andWhere('scope = \'stores\'');
        $query->andWhere('scope_id IN (:storeId)');
        $query->andWhere('path = \'general/country/allow\' OR path = \'general/country/default\'');
        $query->setParameter('storeId', $storeIds, Connection::PARAM_STR_ARRAY);

        $configurations = $query->execute()->fetchAll(\PDO::FETCH_GROUP);

        $storeCountryConfig = [];
        foreach ($configurations as $key => $storeConfig) {
            foreach ($storeConfig as $config) {
                if ($config['path'] === 'general/country/allow') {
                    if (isset($storeCountryConfig[$key]['allowedCountries'])) {
                        $storeCountryConfig[$key]['allowedCountries'] = explode(',', $config['value']);
                    }
                } else {
                    $storeCountryConfig[$key]['defaultCountry'] = $config['value'];
                }
            }
        }

        return $storeCountryConfig;
    }

    private function fetchLocales(array $storeIds): array
    {
        $query = $this->connection->createQueryBuilder();

        $query->from($this->tablePrefix . 'core_config_data', 'locales');
        $query->addSelect('scope_id AS store_id');
        $query->addSelect('value AS locale');

        $query->orWhere('scope = \'stores\' AND scope_id IN (:storeId) AND path = \'general/locale/code\'');
        $query->setParameter('storeId', $storeIds, Connection::PARAM_STR_ARRAY);

        $configurations = $query->execute()->fetchAll(\PDO::FETCH_GROUP | \PDO::FETCH_UNIQUE);

        $storeConfigs = [];
        foreach ($configurations as $key => $storeConfig) {
            $storeConfigs[$key] = str_replace('_', '-', $storeConfig['locale']);
        }
        $configurations['stores'] = $storeConfigs;

        return $configurations;
    }
}
