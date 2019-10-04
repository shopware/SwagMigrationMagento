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
        $fetchedCustomers = $this->fetchCustomers($ids);
        $fetchedAddresses = $this->fetchAddresses($ids);

        foreach ($fetchedCustomers as &$customer) {
            $customerId = $customer['customerID'];

            if (isset($fetchedAddresses[$customerId])) {
                $customer['addresses'] = $fetchedAddresses[$customerId];
            }
        }

        $fetchedCustomers = $this->utf8ize($fetchedCustomers);

        return $this->cleanupResultSet($fetchedCustomers);
    }

    private function fetchCustomers(array $ids): array
    {
        $attributes = [
            'gender',
            'prefix',
            'firstname',
            'middlename',
            'lastname',
            'dob',
            'taxvat',
            'default_billing',
            'default_shipping',
        ];

        $sql = "
        SELECT
				customer.entity_id						as customerID,
				customer.increment_id					as customernumber,
				customer.email							as email,
				customer.store_id						as store_id,
				customer.is_active 						as is_active,
				customer.group_id						as customergroupID,
				prefix.value                            as prefix,
				firstname.value                         as firstname,
				lastname.value                          as lastname,
				IF(gender.value=2, 'ms', 'mr')			as salutation,
				dob.value 								as dob,
				taxvat.value 							as ustid,
				default_billing.value                   as default_billing_address_id,
				default_shipping.value                   as default_shipping_address_id

			FROM customer_entity customer

			{$this->createTableSelect('customer', $attributes)}
			
			WHERE
			  customer.entity_id in (?)
            ORDER BY customer.entity_id
        ";

        $customers = $this->connection->executeQuery($sql, [$ids], [Connection::PARAM_STR_ARRAY])->fetchAll(\PDO::FETCH_ASSOC);

        return $customers;
    }

    private function fetchAddresses(array $ids): array
    {
        $addressAttributes = [
            'firstname',
            'middlename',
            'lastname',
            'company',
            'city',
            'country_id',
            'postcode',
            'street',
            'telephone',
        ];

        $sql = "
        SELECT
            customer_address.parent_id      as customer_id,
            customer_address.entity_id      as id,
            company.value 				    as company,
            TRIM(CONCAT(firstname.value, ' ', IFNULL(middlename.value, '')))
                                            as firstname,
            lastname.value 					as lastname,
            street.value					as street,
            city.value						as city,
            country_id.value				as country_id,
            directory_country.iso2_code 	as country_iso2,
            directory_country.iso3_code 	as country_iso3,
            postcode.value					as zipcode,
            telephone.value					as phone
        FROM customer_address_entity as customer_address
        
        {$this->createTableSelect('customer_address', $addressAttributes)}
        
        LEFT JOIN directory_country ON directory_country.country_id = country_id.value
        
        WHERE customer_address.parent_id in (?)
        ORDER BY customer_address.parent_id
        ";

        $addresses = $this->connection->executeQuery($sql, [$ids], [Connection::PARAM_STR_ARRAY])->fetchAll(\PDO::FETCH_GROUP|\PDO::FETCH_ASSOC);

        return $addresses;
    }
}
