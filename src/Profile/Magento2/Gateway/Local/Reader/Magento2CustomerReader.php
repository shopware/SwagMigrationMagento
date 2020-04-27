<?php declare(strict_types=1);
/*
 * (c) shopware AG <info@shopware.com>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Swag\MigrationMagento\Profile\Magento2\Gateway\Local\Reader;

use Doctrine\DBAL\Connection;
use Swag\MigrationMagento\Profile\Magento\Gateway\Local\Reader\CustomerReader;

abstract class Magento2CustomerReader extends CustomerReader
{
    protected function fetchAddresses(array $ids): array
    {
        $sql = <<<SQL
SELECT
    customer_address.*,
    directory_country.iso2_code AS country_iso2,
    directory_country.iso3_code AS country_iso3
FROM {$this->tablePrefix}customer_address_entity customer_address
LEFT JOIN {$this->tablePrefix}directory_country AS directory_country ON directory_country.country_id = customer_address.country_id
 WHERE customer_address.parent_id IN (?);
SQL;

        return $this->connection->executeQuery($sql, [$ids], [Connection::PARAM_STR_ARRAY])->fetchAll(\PDO::FETCH_ASSOC);
    }
}
