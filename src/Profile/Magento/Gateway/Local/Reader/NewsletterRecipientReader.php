<?php declare(strict_types=1);

namespace Swag\MigrationMagento\Profile\Magento\Gateway\Local\Reader;

use Doctrine\DBAL\Connection;
use Swag\MigrationMagento\Profile\Magento\Magento19Profile;
use SwagMigrationAssistant\Migration\DataSelection\DefaultEntities;
use SwagMigrationAssistant\Migration\MigrationContextInterface;

class NewsletterRecipientReader extends AbstractReader implements LocalReaderInterface
{
    public function supports(MigrationContextInterface $migrationContext): bool
    {
        return $migrationContext->getProfile() instanceof Magento19Profile
            && $migrationContext->getDataSet()::getEntity() === DefaultEntities::NEWSLETTER_RECIPIENT;
    }

    public function read(MigrationContextInterface $migrationContext, array $params = []): array
    {
        $this->setConnection($migrationContext);
        $ids = $this->fetchIdentifiers('newsletter_subscriber', 'subscriber_id', $migrationContext->getOffset(), $migrationContext->getLimit());
        $fetchedRecipients = $this->mapData($this->fetchNewsletterRecipients($ids, $migrationContext), [], ['recipient']);

        $customerIds = array_values(
            array_filter(
                array_column($fetchedRecipients, 'customer_id'),
                function ($value) {
                    return $value !== '0';
                }
            )
        );
        $fetchedCustomers = $this->fetchCustomers($customerIds);

        foreach ($fetchedRecipients as &$recipient) {
            if (isset($fetchedCustomers[$recipient['customer_id']])) {
                $customer = $fetchedCustomers[$recipient['customer_id']];
                $recipient['firstName'] = $customer['firstname'];
                $recipient['lastName'] = $customer['lastname'];
                $recipient['title'] = $customer['prefix'];
            }
        }
        unset($recipient);
        $fetchedRecipients = $this->utf8ize($fetchedRecipients);

        return $this->cleanupResultSet($fetchedRecipients);
    }

    private function fetchNewsletterRecipients(array $ids, MigrationContextInterface $migrationContext): array
    {
        $query = $this->connection->createQueryBuilder();

        $query->from('newsletter_subscriber', 'recipient');
        $this->addTableSelection($query, 'newsletter_subscriber', 'recipient');
        $query->where('recipient.subscriber_id IN (:ids)');
        $query->setParameter('ids', $ids, Connection::PARAM_STR_ARRAY);

        return $query->execute()->fetchAll(\PDO::FETCH_ASSOC);
    }

    private function fetchCustomers(array $ids): array
    {
        $query = $this->connection->createQueryBuilder();

        $query->from('customer_entity', 'customer');
        $query->addSelect('customer.entity_id');
        $this->addTableSelection($query, 'customer_entity', 'customer');
        $query->where('customer.entity_id IN (:ids)');
        $query->orderBy('customer.entity_id');
        $query->setParameter('ids', $ids, Connection::PARAM_STR_ARRAY);

        $fetchedCustomers = $this->mapData(
            $query->execute()->fetchAll(\PDO::FETCH_GROUP | \PDO::FETCH_ASSOC | \PDO::FETCH_UNIQUE),
            [],
            ['customer']
        );
        $this->appendAttributes(
            $fetchedCustomers,
            $this->fetchAttributes($ids, 'customer')
        );

        return $fetchedCustomers;
    }
}
