<?php declare(strict_types=1);
/*
 * (c) shopware AG <info@shopware.com>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Swag\MigrationMagento\Profile\Magento\Gateway\Local\Reader;

use Doctrine\DBAL\ArrayParameterType;
use Shopware\Core\Framework\Log\Package;
use SwagMigrationAssistant\Migration\DataSelection\DefaultEntities;
use SwagMigrationAssistant\Migration\MigrationContextInterface;
use SwagMigrationAssistant\Migration\TotalStruct;

#[Package('fundamentals@after-sales')]
abstract class CustomerReader extends AbstractReader
{
    public function read(MigrationContextInterface $migrationContext, array $params = []): array
    {
        $this->setConnection($migrationContext);

        $ids = $this->fetchIdentifiers($this->tablePrefix . 'customer_entity', 'entity_id', $migrationContext->getOffset(), $migrationContext->getLimit());
        $addressIds = $this->fetchIdentifiersByRelation($this->tablePrefix . 'customer_address_entity', 'entity_id', 'parent_id', $ids);

        $fetchedCustomers = $this->fetchCustomers($ids);
        $this->appendAttributes(
            $fetchedCustomers,
            $this->fetchAttributes($ids, 'customer')
        );
        $fetchedAddresses = $this->fetchAddresses($ids);
        $this->appendAttributes(
            $fetchedAddresses,
            $this->fetchAttributes($addressIds, 'customer_address')
        );
        $fetchedAddresses = $this->groupByProperty($fetchedAddresses, 'parent_id');

        foreach ($fetchedCustomers as &$customer) {
            $customerId = $customer['entity_id'];

            if (isset($fetchedAddresses[$customerId])) {
                $customer['addresses'] = $fetchedAddresses[$customerId];
            }
        }
        $fetchedCustomers = $this->utf8ize($fetchedCustomers);

        return $this->cleanupResultSet($fetchedCustomers);
    }

    public function readTotal(MigrationContextInterface $migrationContext): ?TotalStruct
    {
        $this->setConnection($migrationContext);

        $sql = <<<SQL
SELECT COUNT(*)
FROM {$this->tablePrefix}customer_entity;
SQL;
        $total = (int) $this->connection->executeQuery($sql)->fetchOne();

        return new TotalStruct(DefaultEntities::CUSTOMER, $total);
    }

    protected function fetchCustomers(array $ids): array
    {
        $sql = <<<SQL
SELECT customer.*
FROM {$this->tablePrefix}customer_entity customer
WHERE customer.entity_id IN (?)
ORDER BY customer.entity_id;
SQL;

        return $this->connection->executeQuery($sql, [$ids], [ArrayParameterType::STRING])->fetchAllAssociative();
    }

    protected function fetchAddresses(array $ids): array
    {
        $sql = <<<SQL
            SELECT
                customer_address.*,
                directory_country.iso2_code AS country_iso2,
                directory_country.iso3_code AS country_iso3,
                directory_country_region.code AS region_code,
                region_attribute.value AS region_name
            FROM {$this->tablePrefix}customer_address_entity customer_address
                LEFT JOIN {$this->tablePrefix}eav_attribute AS country_eav_attribute
                    ON country_eav_attribute.entity_type_id = customer_address.entity_type_id AND country_eav_attribute.attribute_code = 'country_id'
                LEFT JOIN {$this->tablePrefix}eav_attribute AS region_id_eav_attribute
                    ON region_id_eav_attribute.entity_type_id = customer_address.entity_type_id AND region_id_eav_attribute.attribute_code = 'region_id'
                LEFT JOIN {$this->tablePrefix}eav_attribute AS region_eav_attribute
                    ON region_eav_attribute.entity_type_id = customer_address.entity_type_id AND region_eav_attribute.attribute_code = 'region'
                LEFT JOIN {$this->tablePrefix}customer_address_entity_varchar AS country_attribute
                    ON country_eav_attribute.attribute_id = country_attribute.attribute_id AND country_attribute.entity_id = customer_address.entity_id
                LEFT JOIN {$this->tablePrefix}customer_address_entity_int AS region_id_attribute
                    ON region_id_eav_attribute.attribute_id = region_id_attribute.attribute_id AND region_id_attribute.entity_id = customer_address.entity_id
                LEFT JOIN {$this->tablePrefix}customer_address_entity_varchar AS region_attribute
                    ON region_eav_attribute.attribute_id = region_attribute.attribute_id AND region_attribute.entity_id = customer_address.entity_id
                LEFT JOIN {$this->tablePrefix}directory_country AS directory_country
                    ON directory_country.country_id = country_attribute.value
                LEFT JOIN {$this->tablePrefix}directory_country_region AS directory_country_region
                    ON directory_country_region.region_id = region_id_attribute.value
            WHERE customer_address.parent_id IN (?);
SQL;

        return $this->connection->executeQuery($sql, [$ids], [ArrayParameterType::STRING])->fetchAllAssociative();
    }
}
