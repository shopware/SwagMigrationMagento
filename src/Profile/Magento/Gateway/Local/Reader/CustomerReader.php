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

        $fetchedCustomers = $this->fetchCustomers($migrationContext);
        $customers = $this->mapData($fetchedCustomers, [], ['customer', 'customer_address']);

        return $this->cleanupResultSet($customers);
    }

    private function fetchCustomers(MigrationContextInterface $migrationContext): array
    {
        $ids = $this->fetchIdentifiers('customer_entity', 'entity_id', $migrationContext->getOffset(), $migrationContext->getLimit());

        $query = $this->connection->createQueryBuilder();
        $query->from('customer_entity', 'customer');
        $this->addTableSelection($query, 'customer_entity', 'customer');

        $query->innerJoin('customer', 'customer_address_entity', 'customer_address', 'customer.entity_id = customer_address.parent_id');
        $query->where('customer.entity_id IN (:ids)');
        $query->setParameter('ids', $ids, Connection::PARAM_STR_ARRAY);

        return $query->execute()->fetchAll();
    }
}
