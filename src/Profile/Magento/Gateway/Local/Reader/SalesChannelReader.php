<?php declare(strict_types=1);
/*
 * (c) shopware AG <info@shopware.com>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Swag\MigrationMagento\Profile\Magento\Gateway\Local\Reader;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver\ResultStatement;
use SwagMigrationAssistant\Migration\DataSelection\DefaultEntities;
use SwagMigrationAssistant\Migration\MigrationContextInterface;
use SwagMigrationAssistant\Migration\TotalStruct;

abstract class SalesChannelReader extends AbstractReader
{
    public function read(MigrationContextInterface $migrationContext, array $params = []): array
    {
        $this->setConnection($migrationContext);

        $storeGroups = $this->mapData($this->fetchStoreGroups($migrationContext), [], ['storeGroup', 'website']);
        $groupIds = \array_column($storeGroups, 'group_id');
        $websiteIds = \array_column($storeGroups, 'website_id');

        $storeViews = $this->mapData($this->fetchStoreViews($groupIds), [], ['storeView']);
        $storeIds = [];

        foreach ($storeGroups as &$storeGroup) {
            $storeGroup['storeViews'] = [];
            if (isset($storeViews[$storeGroup['group_id']])) {
                $storeGroup['storeViews'] = $storeViews[$storeGroup['group_id']];
                $storeIds[] = \array_column($storeViews[$storeGroup['group_id']], 'store_id');
            }
        }
        $storeIds = \array_merge(...$storeIds);

        $carriers = $this->fetchCarriers();
        $payments = $this->fetchPayments();

        $storeAllowedCurrencies = $this->fetchStoreCurrencies($storeIds);
        $storeCountryConfig = $this->fetchStoreCountryConfig($storeIds);
        $locales = $this->fetchLocales($storeIds);

        $defaults = $this->fetchDefaults();
        $websiteConfigs = $this->fetchWebsiteConfig($websiteIds);

        foreach ($storeGroups as &$store) {
            $this->setDefaultConfig($store, $defaults, $websiteConfigs);

            foreach ($store['storeViews'] as $storeView) {
                $storeId = $storeView['store_id'];
                if (isset($storeAllowedCurrencies[$storeId])) {
                    $store['currencies'] = \array_merge($store['currencies'], $storeAllowedCurrencies[$storeId]);
                }
                if (isset($storeCountryConfig[$storeId])) {
                    $allowedCountries = [];
                    if (isset($storeCountryConfig[$storeId]['allowedCountries'])) {
                        $allowedCountries = $storeCountryConfig[$storeId]['allowedCountries'];
                    }

                    $store['countries'] = \array_merge($store['countries'], $allowedCountries);
                }

                if (isset($locales['stores'][$storeId])) {
                    $store['locales'][] = $locales['stores'][$storeId];
                }
            }
            $store['locales'] = \array_unique($store['locales']);
            $store['currencies'] = \array_unique($store['currencies']);
            $this->setCarriers($store, $carriers);
            $this->setPayments($store, $payments);
        }

        return $this->cleanupResultSet($storeGroups);
    }

    public function readTotal(MigrationContextInterface $migrationContext): ?TotalStruct
    {
        $this->setConnection($migrationContext);

        $sql = <<<SQL
SELECT COUNT(*)
FROM {$this->tablePrefix}core_store_group
WHERE website_id != 0;
SQL;
        $total = (int) $this->connection->executeQuery($sql)->fetchColumn();

        return new TotalStruct(DefaultEntities::SALES_CHANNEL, $total);
    }

    protected function fetchDefaults(): array
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

        $query = $query->execute();
        if (!($query instanceof ResultStatement)) {
            return [];
        }

        $defaults = $query->fetch(\PDO::FETCH_ASSOC);

        if ($defaults['defaultAllowedCurrencies'] === null) {
            $defaults['defaultAllowedCountries'] = '';
        }
        $defaults['defaultAllowedCurrencies'] = \explode(',', $defaults['defaultAllowedCurrencies']);

        if ($defaults['defaultAllowedCountries'] === null) {
            $defaults['defaultAllowedCountries'] = '';
        }
        $defaults['defaultAllowedCountries'] = \explode(',', $defaults['defaultAllowedCountries']);

        if ($defaults['defaultLocale'] === null) {
            $defaults['defaultLocale'] = '';
        }
        $defaults['defaultLocale'] = \str_replace('_', '-', $defaults['defaultLocale']);

        return $defaults;
    }

    protected function fetchStoreGroups(MigrationContextInterface $migrationContext): array
    {
        $query = $this->connection->createQueryBuilder();

        $query->from($this->tablePrefix . 'core_store_group', 'storeGroup');
        $this->addTableSelection($query, $this->tablePrefix . 'core_store_group', 'storeGroup');
        $query->where('storeGroup.website_id != 0');

        $query->setFirstResult($migrationContext->getOffset());
        $query->setMaxResults($migrationContext->getLimit());

        $query = $query->execute();
        if (!($query instanceof ResultStatement)) {
            return [];
        }

        return $query->fetchAll();
    }

    protected function fetchStoreViews(array $groupIds): array
    {
        $query = $this->connection->createQueryBuilder();

        $query->from($this->tablePrefix . 'core_store', 'storeView');
        $query->addSelect('storeView.group_id as storegroup');
        $this->addTableSelection($query, $this->tablePrefix . 'core_store', 'storeView');

        $query->andWhere('storeView.group_id IN (:ids)');
        $query->setParameter('ids', $groupIds, Connection::PARAM_INT_ARRAY);

        $query = $query->execute();
        if (!($query instanceof ResultStatement)) {
            return [];
        }

        return $query->fetchAll(\PDO::FETCH_GROUP);
    }

    protected function fetchCarriers(): array
    {
        $sql = <<<SQL
SELECT carrier_active.*
FROM (
          SELECT
            REPLACE(REPLACE(config.path, '/title', ''), 'carriers/', '') AS carrier_id
          FROM {$this->tablePrefix}core_config_data config
          WHERE path LIKE 'carriers/%/title' AND scope = 'default'
      ) AS carrier,
      (
          SELECT
            REPLACE(REPLACE(config.path, '/active', ''), 'carriers/', '') AS carrier_id,
            config.*
          FROM {$this->tablePrefix}core_config_data config
          WHERE path LIKE 'carriers/%/active'
      ) AS carrier_active
WHERE carrier.carrier_id = carrier_active.carrier_id
    AND ((carrier_active.scope = 'default' AND carrier_active.value = 1) OR carrier_active.scope != 'default')
ORDER BY carrier_active.scope
SQL;

        return $this->connection->executeQuery($sql)->fetchAll(\PDO::FETCH_ASSOC);
    }

    protected function setCarriers(array &$store, array $carriers): void
    {
        $website = $store['website_id'];

        $resultCarriers = [];
        foreach ($carriers as $carrier) {
            $carrierId = $carrier['carrier_id'];

            if ($carrier['scope'] === 'websites' && $carrier['scope_id'] !== $website) {
                continue;
            }

            if ((int) $carrier['value'] === 1) {
                $resultCarriers[$carrierId] = $carrier;
            } else {
                unset($resultCarriers[$carrierId]);
            }
        }

        $store['carriers'] = \array_values($resultCarriers);
    }

    protected function fetchPayments(): array
    {
        $sql = <<<SQL
SELECT payment_active.*
FROM (
         SELECT
             REPLACE(REPLACE(config.path, '/title', ''), 'payment/', '') AS payment_id
         FROM {$this->tablePrefix}core_config_data config
         WHERE path LIKE 'payment/%/title' AND scope = 'default'
     ) AS payment,
     (
         SELECT
             REPLACE(REPLACE(config.path, '/active', ''), 'payment/', '') AS payment_id,
             config.*
         FROM {$this->tablePrefix}core_config_data config
         WHERE path LIKE 'payment/%/active'
     ) AS payment_active
WHERE payment.payment_id = payment_active.payment_id
  AND ((payment_active.scope = 'default' AND payment_active.value = 1) OR payment_active.scope != 'default')
ORDER BY payment_active.scope;
SQL;

        return $this->connection->executeQuery($sql)->fetchAll(\PDO::FETCH_ASSOC);
    }

    protected function setPayments(array &$store, array $payments): void
    {
        $website = $store['website_id'];

        $resultPayments = [];
        foreach ($payments as $payment) {
            $paymentId = $payment['payment_id'];

            if ($payment['scope'] === 'websites' && $payment['scope_id'] !== $website) {
                continue;
            }

            if ((int) $payment['value'] === 1) {
                $resultPayments[$paymentId] = $payment;
            } else {
                unset($resultPayments[$paymentId]);
            }
        }

        $store['payments'] = \array_values($resultPayments);
    }

    protected function fetchWebsiteConfig(array $websiteIds): array
    {
        $query = $this->connection->createQueryBuilder();

        $query->from($this->tablePrefix . 'core_config_data', 'config');
        $query->addSelect('scope_id AS website_id');
        $query->addSelect('path');
        $query->addSelect('value');

        $query->andWhere('scope = \'websites\'');
        $query->andWhere('scope_id IN (:websiteId)');
        $query->andWhere('(path = \'currency/options/allow\' OR path = \'general/locale/code\' OR path = \'currency/options/default\' OR path = \'general/country/allow\' OR path = \'general/country/default\')');
        $query->setParameter('websiteId', $websiteIds, Connection::PARAM_STR_ARRAY);

        $query = $query->execute();
        if (!($query instanceof ResultStatement)) {
            return [];
        }

        $configs = $query->fetchAll(\PDO::FETCH_GROUP);

        $returnConfig = [];
        foreach ($configs as $key => $config) {
            foreach ($config as $entry) {
                if (!isset($entry['path'])) {
                    continue;
                }

                $valueKey = null;
                $value = $entry['value'];
                switch ($entry['path']) {
                    case 'general/locale/code':
                        $valueKey = 'defaultLocale';
                        $value = \str_replace('_', '-', $value);

                        break;
                    case 'currency/options/default':
                        $valueKey = 'defaultCurrency';

                        break;
                    case 'currency/options/allow':
                        $valueKey = 'allowedCurrencies';
                        $value = \explode(',', $value);

                        break;
                    case 'general/country/allow':
                        $valueKey = 'allowedCountries';
                        $value = \explode(',', $value);

                        break;
                    case 'general/country/default':
                        $valueKey = 'defaultCountry';

                        break;
                }

                if ($valueKey === null) {
                    continue;
                }

                $returnConfig[$key][$valueKey] = $value;
            }
        }

        return $returnConfig;
    }

    protected function fetchStoreCurrencies(array $storeIds): array
    {
        $query = $this->connection->createQueryBuilder();

        $query->from($this->tablePrefix . 'core_config_data', 'currency');
        $query->addSelect('scope_id AS store_id');
        $query->addSelect('value AS currencies');

        $query->andWhere('scope = \'stores\'');
        $query->andWhere('scope_id IN (:storeId)');
        $query->andWhere('path = \'currency/options/allow\'');
        $query->setParameter('storeId', $storeIds, Connection::PARAM_STR_ARRAY);

        $query = $query->execute();
        if (!($query instanceof ResultStatement)) {
            return [];
        }

        $storeCurrencies = $query->fetchAll(\PDO::FETCH_GROUP | \PDO::FETCH_UNIQUE);

        foreach ($storeCurrencies as $key => $storeCurrency) {
            if (isset($storeCurrency['currencies'])) {
                $storeCurrencies[$key] = \explode(',', $storeCurrency['currencies']);
            }
        }

        return $storeCurrencies;
    }

    protected function fetchStoreCountryConfig(array $storeIds): array
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

        $query = $query->execute();
        if (!($query instanceof ResultStatement)) {
            return [];
        }

        $configurations = $query->fetchAll(\PDO::FETCH_GROUP);

        $storeCountryConfig = [];
        foreach ($configurations as $key => $storeConfig) {
            foreach ($storeConfig as $config) {
                if ($config['path'] === 'general/country/allow') {
                    if (isset($storeCountryConfig[$key]['allowedCountries'])) {
                        $storeCountryConfig[$key]['allowedCountries'] = \explode(',', $config['value']);
                    }
                } else {
                    $storeCountryConfig[$key]['defaultCountry'] = $config['value'];
                }
            }
        }

        return $storeCountryConfig;
    }

    protected function fetchLocales(array $storeIds): array
    {
        $query = $this->connection->createQueryBuilder();

        $query->from($this->tablePrefix . 'core_config_data', 'locales');
        $query->addSelect('scope_id AS store_id');
        $query->addSelect('value AS locale');

        $query->orWhere('scope = \'stores\' AND scope_id IN (:storeId) AND path = \'general/locale/code\'');
        $query->setParameter('storeId', $storeIds, Connection::PARAM_STR_ARRAY);

        $query = $query->execute();
        if (!($query instanceof ResultStatement)) {
            return [];
        }

        $configurations = $query->fetchAll(\PDO::FETCH_GROUP | \PDO::FETCH_UNIQUE);

        $storeConfigs = [];
        foreach ($configurations as $key => $storeConfig) {
            $storeConfigs[$key] = \str_replace('_', '-', $storeConfig['locale']);
        }
        $configurations['stores'] = $storeConfigs;

        return $configurations;
    }

    protected function setDefaultConfig(array &$store, array $defaults, array $websiteConfigs): void
    {
        $websiteId = $store['website_id'];

        $store['currencies'] = $defaults['defaultAllowedCurrencies'];
        if (isset($websiteConfigs[$websiteId]['allowedCurrencies'])) {
            $store['currencies'] = $websiteConfigs[$websiteId]['allowedCurrencies'];
        }

        $store['defaultCurrency'] = $defaults['defaultCurrency'];
        if (isset($websiteConfigs[$websiteId]['defaultCurrency'])) {
            $store['defaultCurrency'] = $websiteConfigs[$websiteId]['defaultCurrency'];
        }

        if (isset($websiteConfigs[$websiteId]['defaultLocale'])) {
            $store['locales'][] = $websiteConfigs[$websiteId]['defaultLocale'];
            $store['defaultLocale'] = $websiteConfigs[$websiteId]['defaultLocale'];
        } else {
            $store['locales'][] = $defaults['defaultLocale'];
            $store['defaultLocale'] = $defaults['defaultLocale'];
        }

        $store['countries'] = $defaults['defaultAllowedCountries'];
        if (isset($websiteConfigs[$websiteId]['allowedCountries'])) {
            $store['countries'] = $websiteConfigs[$websiteId]['allowedCountries'];
        }

        $store['defaultCountry'] = $defaults['defaultCountry'];
        if (isset($websiteConfigs[$websiteId]['defaultCountry'])) {
            $store['defaultCountry'] = $websiteConfigs[$websiteId]['defaultCountry'];
        }
    }
}
