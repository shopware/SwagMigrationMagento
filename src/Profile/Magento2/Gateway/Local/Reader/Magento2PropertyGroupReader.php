<?php declare(strict_types=1);
/*
 * (c) shopware AG <info@shopware.com>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Swag\MigrationMagento\Profile\Magento2\Gateway\Local\Reader;

use Doctrine\DBAL\Driver\ResultStatement;
use Swag\MigrationMagento\Profile\Magento\Gateway\Local\Reader\PropertyGroupReader;
use SwagMigrationAssistant\Migration\DataSelection\DefaultEntities;
use SwagMigrationAssistant\Migration\MigrationContextInterface;
use SwagMigrationAssistant\Migration\TotalStruct;

abstract class Magento2PropertyGroupReader extends PropertyGroupReader
{
    public function readTotal(MigrationContextInterface $migrationContext): ?TotalStruct
    {
        $this->setConnection($migrationContext);

        $sql = <<<SQL
SELECT COUNT(*)
FROM {$this->tablePrefix}eav_attribute AS eav
INNER JOIN {$this->tablePrefix}catalog_eav_attribute AS eav_settings ON eav_settings.attribute_id = eav.attribute_id
WHERE eav.is_user_defined = 1 AND (eav_settings.is_filterable = 1 OR eav.frontend_input = 'select') AND eav.attribute_code NOT IN ('manufacturer', 'cost');
SQL;
        $total = (int) $this->connection->executeQuery($sql)->fetchColumn();

        return new TotalStruct(DefaultEntities::PROPERTY_GROUP, $total);
    }

    public function fetchPropertyGroups(MigrationContextInterface $migrationContext): array
    {
        $query = $this->connection->createQueryBuilder();

        $query->addSelect('eav.attribute_id AS identifier');
        $query->addSelect('eav.attribute_id AS id');
        $query->addSelect('eav.frontend_label AS name');
        $query->from($this->tablePrefix . 'eav_attribute', 'eav');

        $query->innerJoin('eav', $this->tablePrefix . 'catalog_eav_attribute', 'eav_settings', 'eav_settings.attribute_id = eav.attribute_id');

        $query->where('eav.is_user_defined = 1 AND (eav_settings.is_filterable = 1 OR eav.frontend_input = \'select\')');
        $query->andWhere('eav.attribute_code NOT IN (\'manufacturer\', \'cost\')');
        $query->orderBy('eav.attribute_id');

        $query->setFirstResult($migrationContext->getOffset());
        $query->setMaxResults($migrationContext->getLimit());

        $query = $query->execute();
        if (!($query instanceof ResultStatement)) {
            return [];
        }

        return $query->fetchAll(\PDO::FETCH_GROUP | \PDO::FETCH_ASSOC | \PDO::FETCH_UNIQUE);
    }
}
