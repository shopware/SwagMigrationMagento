<?php declare(strict_types=1);
/*
 * (c) shopware AG <info@shopware.com>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Swag\MigrationMagento\Profile\Magento2\Gateway\Local\Reader;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver\ResultStatement;
use Swag\MigrationMagento\Profile\Magento\Gateway\Local\Reader\SalesChannelReader;
use SwagMigrationAssistant\Migration\DataSelection\DefaultEntities;
use SwagMigrationAssistant\Migration\MigrationContextInterface;
use SwagMigrationAssistant\Migration\TotalStruct;

abstract class Magento2SalesChannelReader extends SalesChannelReader
{
    public function readTotal(MigrationContextInterface $migrationContext): ?TotalStruct
    {
        $this->setConnection($migrationContext);

        $sql = <<<SQL
SELECT COUNT(*)
FROM {$this->tablePrefix}store_group
WHERE website_id != 0;
SQL;
        $total = (int) $this->connection->executeQuery($sql)->fetchColumn();

        return new TotalStruct(DefaultEntities::SALES_CHANNEL, $total);
    }

    protected function fetchStoreGroups(MigrationContextInterface $migrationContext): array
    {
        $query = $this->connection->createQueryBuilder();

        $query->from($this->tablePrefix . 'store_group', 'storeGroup');
        $this->addTableSelection($query, $this->tablePrefix . 'store_group', 'storeGroup');
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

        $query->from($this->tablePrefix . 'store', 'storeView');
        $query->addSelect('storeView.group_id as storegroup');
        $this->addTableSelection($query, $this->tablePrefix . 'store', 'storeView');

        $query->andWhere('storeView.group_id IN (:ids)');
        $query->setParameter('ids', $groupIds, Connection::PARAM_INT_ARRAY);

        $query = $query->execute();
        if (!($query instanceof ResultStatement)) {
            return [];
        }

        return $query->fetchAll(\PDO::FETCH_GROUP);
    }
}
