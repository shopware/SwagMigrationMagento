<?php declare(strict_types=1);
/*
 * (c) shopware AG <info@shopware.com>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Swag\MigrationMagento\Profile\Magento\Gateway\Local\Reader;

use Doctrine\DBAL\ArrayParameterType;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\DataAbstractionLayer\Doctrine\FetchModeHelper;
use SwagMigrationAssistant\Migration\DataSelection\DefaultEntities;
use SwagMigrationAssistant\Migration\MigrationContextInterface;
use SwagMigrationAssistant\Migration\TotalStruct;

#[Package('services-settings')]
abstract class NewsletterRecipientReader extends AbstractReader
{
    public function read(MigrationContextInterface $migrationContext, array $params = []): array
    {
        $this->setConnection($migrationContext);
        $ids = $this->fetchIdentifiers($this->tablePrefix . 'newsletter_subscriber', 'subscriber_id', $migrationContext->getOffset(), $migrationContext->getLimit());
        $fetchedRecipients = $this->mapData($this->fetchNewsletterRecipients($ids, $migrationContext), [], ['recipient']);

        $customerIds = \array_values(
            \array_filter(
                \array_column($fetchedRecipients, 'customer_id'),
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

    public function readTotal(MigrationContextInterface $migrationContext): ?TotalStruct
    {
        $this->setConnection($migrationContext);

        $sql = <<<SQL
SELECT COUNT(*)
FROM {$this->tablePrefix}newsletter_subscriber;
SQL;
        $total = (int) $this->connection->executeQuery($sql)->fetchOne();

        return new TotalStruct(DefaultEntities::NEWSLETTER_RECIPIENT, $total);
    }

    protected function fetchNewsletterRecipients(array $ids, MigrationContextInterface $migrationContext): array
    {
        $query = $this->connection->createQueryBuilder();

        $query->from($this->tablePrefix . 'newsletter_subscriber', 'recipient');
        $this->addTableSelection($query, $this->tablePrefix . 'newsletter_subscriber', 'recipient');
        $query->where('recipient.subscriber_id IN (:ids)');
        $query->setParameter('ids', $ids, ArrayParameterType::STRING);

        return $query->executeQuery()->fetchAllAssociative();
    }

    protected function fetchCustomers(array $ids): array
    {
        $query = $this->connection->createQueryBuilder();

        $query->from($this->tablePrefix . 'customer_entity', 'customer');
        $query->addSelect('customer.entity_id');
        $this->addTableSelection($query, $this->tablePrefix . 'customer_entity', 'customer');
        $query->where('customer.entity_id IN (:ids)');
        $query->orderBy('customer.entity_id');
        $query->setParameter('ids', $ids, ArrayParameterType::STRING);

        $result = $query->executeQuery()->fetchAllAssociative();
        $fetchedCustomers = $this->mapData(
            FetchModeHelper::groupUnique($result),
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
