<?php declare(strict_types=1);

namespace Swag\MigrationMagento\Profile\Magento\Gateway\Local\Reader;

use Doctrine\DBAL\Connection;
use Swag\MigrationMagento\Profile\Magento\Magento19Profile;
use SwagMigrationAssistant\Migration\DataSelection\DefaultEntities;
use SwagMigrationAssistant\Migration\MigrationContextInterface;

class CustomerReader extends AbstractReader implements LocalReaderInterface
{
    public function supports(MigrationContextInterface $migrationContext): bool
    {
        return $migrationContext->getProfile() instanceof Magento19Profile
            && $migrationContext->getDataSet()::getEntity() === DefaultEntities::CUSTOMER;
    }

    public function read(MigrationContextInterface $migrationContext, array $params = []): array
    {
        $this->setConnection($migrationContext);

        $ids = $this->fetchIdentifiers('customer_entity', 'entity_id', $migrationContext->getOffset(), $migrationContext->getLimit());
        $addressIds = $this->fetchIdentifiersByRelation('customer_address_entity', 'entity_id', 'parent_id', $ids);

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

    private function fetchCustomers(array $ids): array
    {
        $sql = <<<SQL
SELECT customer.*
FROM customer_entity customer
WHERE customer.entity_id IN (?)
ORDER BY customer.entity_id;
SQL;

        return $this->connection->executeQuery($sql, [$ids], [Connection::PARAM_STR_ARRAY])->fetchAll(\PDO::FETCH_ASSOC);
    }

    private function fetchAddresses(array $ids): array
    {
        $sql = <<<SQL
SELECT 
    customer_address.*,
    directory_country.iso2_code as country_iso2,
    directory_country.iso3_code as country_iso3
FROM customer_address_entity as customer_address
LEFT JOIN eav_attribute attribute ON attribute.entity_type_id = customer_address.entity_type_id AND attribute.attribute_code = 'country_id'
LEFT JOIN customer_address_entity_varchar country_attribute ON attribute.attribute_id = country_attribute.attribute_id AND country_attribute.entity_id = customer_address.entity_id
LEFT JOIN directory_country ON directory_country.country_id = country_attribute.value
WHERE customer_address.parent_id in (?);
SQL;

        return $this->connection->executeQuery($sql, [$ids], [Connection::PARAM_STR_ARRAY])->fetchAll(\PDO::FETCH_ASSOC);
    }
}
