<?php declare(strict_types=1);
/*
 * (c) shopware AG <info@shopware.com>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Swag\MigrationMagento\Profile\Magento\Gateway\Local\Reader;

use Swag\MigrationMagento\Profile\Magento\Gateway\Connection\ConnectionFactoryInterface;
use Swag\MigrationMagento\Profile\Magento\Gateway\TableReaderInterface;
use SwagMigrationAssistant\Migration\MigrationContextInterface;

class LocalTableReader implements TableReaderInterface
{
    /**
     * @var ConnectionFactoryInterface
     */
    private $connectionFactory;

    public function __construct(ConnectionFactoryInterface $connectionFactory)
    {
        $this->connectionFactory = $connectionFactory;
    }

    public function read(MigrationContextInterface $migrationContext, string $tableName, array $filter = []): array
    {
        $connection = $this->connectionFactory->createDatabaseConnection($migrationContext);
        $query = $connection->createQueryBuilder();
        $query->select('*');
        $query->from($tableName);

        if (!empty($filter)) {
            foreach ($filter as $property => $value) {
                $query->andWhere($property . ' = :value');
                $query->setParameter('value', $value);
            }
        }

        return $query->execute()->fetchAll();
    }
}
