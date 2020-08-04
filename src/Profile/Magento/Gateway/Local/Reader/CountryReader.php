<?php declare(strict_types=1);
/*
 * (c) shopware AG <info@shopware.com>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Swag\MigrationMagento\Profile\Magento\Gateway\Local\Reader;

use Doctrine\DBAL\Driver\ResultStatement;
use SwagMigrationAssistant\Migration\MigrationContextInterface;

abstract class CountryReader extends AbstractReader
{
    public function read(MigrationContextInterface $migrationContext, array $params = []): array
    {
        $this->setConnection($migrationContext);
        $locales = $this->fetchLocales();

        $countries = [];
        foreach ($locales as $locale) {
            $countries[]['isoCode'] = $locale;
        }

        return $countries;
    }

    protected function fetchLocales(): array
    {
        $query = $this->connection->createQueryBuilder();

        $query->from($this->tablePrefix . 'core_config_data', 'country');
        $query->addSelect('scope_id as store_id');
        $query->addSelect('value');
        $query->andwhere('path = \'general/country/allow\'');

        $query = $query->execute();
        if (!($query instanceof ResultStatement)) {
            return [];
        }

        $configurations = $query->fetchAll(\PDO::FETCH_GROUP | \PDO::FETCH_UNIQUE);

        $countryConfig = [];
        foreach ($configurations as $config) {
            if (isset($config['value'])) {
                $countryConfig = \array_merge($countryConfig, \explode(',', $config['value']));
            }
        }

        return \array_values(\array_unique($countryConfig));
    }
}
