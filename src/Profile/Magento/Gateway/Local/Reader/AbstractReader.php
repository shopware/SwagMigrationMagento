<?php declare(strict_types=1);
/*
 * (c) shopware AG <info@shopware.com>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Swag\MigrationMagento\Profile\Magento\Gateway\Local\Reader;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver\ResultStatement;
use Doctrine\DBAL\Query\QueryBuilder;
use Swag\MigrationMagento\Profile\Magento\Gateway\Connection\ConnectionFactoryInterface;
use SwagMigrationAssistant\Migration\Gateway\Reader\ReaderInterface;
use SwagMigrationAssistant\Migration\MigrationContextInterface;
use SwagMigrationAssistant\Migration\TotalStruct;

abstract class AbstractReader implements ReaderInterface
{
    /**
     * @var ConnectionFactoryInterface
     */
    protected $connectionFactory;

    /**
     * @var Connection
     */
    protected $connection;

    /**
     * @var string
     */
    protected $tablePrefix;

    public function __construct(ConnectionFactoryInterface $connectionFactory)
    {
        $this->connectionFactory = $connectionFactory;
        $this->tablePrefix = '';
    }

    public function supportsTotal(MigrationContextInterface $migrationContext): bool
    {
        return false;
    }

    public function readTotal(MigrationContextInterface $migrationContext): ?TotalStruct
    {
        return null;
    }

    /**
     * @psalm-suppress RedundantConditionGivenDocblockType
     */
    protected function setConnection(MigrationContextInterface $migrationContext): void
    {
        if ($this->connection instanceof Connection && $this->connection->isConnected()) {
            return;
        }

        $connection = $migrationContext->getConnection();
        if ($connection === null) {
            return;
        }

        $dbConnection = $this->connectionFactory->createDatabaseConnection($migrationContext);
        if ($dbConnection === null) {
            return;
        }

        $this->connection = $dbConnection;
        $credentials = $connection->getCredentialFields();
        if (isset($credentials['tablePrefix'])) {
            $this->tablePrefix = $credentials['tablePrefix'];
        }
    }

    protected function fetchDefaultLocale(): string
    {
        $query = $this->connection->createQueryBuilder();

        $query->addSelect('locale.value AS locale');
        $query->from($this->tablePrefix . 'core_config_data', 'locale');
        $query->where('locale.scope = \'default\' AND path = \'general/locale/code\'');
        $query = $query->execute();

        if (!($query instanceof ResultStatement)) {
            return '';
        }

        return $query->fetch(\PDO::FETCH_COLUMN);
    }

    protected function utf8ize(array $array): array
    {
        foreach ($array as $key => $value) {
            $array[$key] = $this->childrenUtf8ize($value);
        }

        return $array;
    }

    protected function addTableSelection(QueryBuilder $query, string $table, string $tableAlias): void
    {
        $columns = $this->connection->getSchemaManager()->listTableColumns($table);

        foreach ($columns as $column) {
            $selection = \str_replace(
                ['#tableAlias#', '#column#'],
                [$tableAlias, $column->getName()],
                '`#tableAlias#`.`#column#` AS `#tableAlias#.#column#`'
            );

            $query->addSelect($selection);
        }
    }

    /**
     * @psalm-suppress MissingParamType
     */
    protected function buildArrayFromChunks(array &$array, array $path, string $fieldKey, $value): void
    {
        $key = \array_shift($path);

        if (empty($key)) {
            $array[$fieldKey] = $value;
        } elseif (empty($path)) {
            $array[$key][$fieldKey] = $value;
        } else {
            if (!isset($array[$key]) || !\is_array($array[$key])) {
                $array[$key] = [];
            }
            $this->buildArrayFromChunks($array[$key], $path, $fieldKey, $value);
        }
    }

    protected function cleanupResultSet(array &$data): array
    {
        foreach ($data as $key => &$value) {
            if (\is_array($value)) {
                if (empty(\array_filter($value))) {
                    unset($data[$key]);

                    continue;
                }

                $this->cleanupResultSet($value);

                if (empty(\array_filter($value))) {
                    unset($data[$key]);

                    continue;
                }
            }
        }

        return $data;
    }

    protected function fetchIdentifiers(string $table, string $identifier = 'id', int $offset = 0, int $limit = 250, bool $distinct = false): array
    {
        $query = $this->connection->createQueryBuilder();

        $query->select($identifier);
        if ($distinct) {
            $query->select('DISTINCT ' . $identifier);
        }

        $query->from($table);
        $query->addOrderBy($identifier);

        $query->setFirstResult($offset);
        $query->setMaxResults($limit);

        $query = $query->execute();
        if (!($query instanceof ResultStatement)) {
            return [];
        }

        return $query->fetchAll(\PDO::FETCH_COLUMN);
    }

    protected function fetchIdentifiersByRelation(string $table, string $identifier, string $relationKey, array $relationIds): array
    {
        $query = $this->connection->createQueryBuilder();
        $query->select($identifier)
            ->from($table)
            ->where($relationKey . ' IN (:ids)')
            ->addOrderBy($identifier)
            ->setParameter('ids', $relationIds, Connection::PARAM_STR_ARRAY);

        $query = $query->execute();
        if (!($query instanceof ResultStatement)) {
            return [];
        }

        return $query->fetchAll(\PDO::FETCH_COLUMN);
    }

    protected function mapData(array $data, array $result = [], array $pathsToRemove = []): array
    {
        foreach ($data as $key => $value) {
            if (\is_numeric($key)) {
                $result[$key] = $this->mapData($value, [], $pathsToRemove);
            } else {
                $paths = \explode('.', $key);
                $fieldKey = $paths[\count($paths) - 1];
                $chunks = \explode('_', $paths[0]);

                if (!empty($pathsToRemove)) {
                    $chunks = \array_diff($chunks, $pathsToRemove);
                }
                $this->buildArrayFromChunks($result, $chunks, $fieldKey, $value);
            }
        }

        return $result;
    }

    protected function fetchAttributes(array $ids, string $entity, array $customAttributes = []): array
    {
        $sql = <<<SQL
SELECT
    {$entity}.entity_id,
    attribute.attribute_code,
    CASE attribute.backend_type
       WHEN 'varchar' THEN {$entity}_varchar.value
       WHEN 'int' THEN {$entity}_int.value
       WHEN 'text' THEN {$entity}_text.value
       WHEN 'decimal' THEN {$entity}_decimal.value
       WHEN 'datetime' THEN {$entity}_datetime.value
       ELSE attribute.backend_type
    END AS value
FROM {$this->tablePrefix}{$entity}_entity {$entity}
LEFT JOIN {$this->tablePrefix}eav_attribute AS attribute
    ON attribute.entity_type_id = (SELECT entity_type_id FROM {$this->tablePrefix}eav_entity_type WHERE entity_type_code = '{$entity}')
LEFT JOIN {$this->tablePrefix}{$entity}_entity_varchar AS {$entity}_varchar
    ON {$entity}.entity_id = {$entity}_varchar.entity_id
    AND attribute.attribute_id = {$entity}_varchar.attribute_id
    AND attribute.backend_type = 'varchar'
LEFT JOIN {$this->tablePrefix}{$entity}_entity_int AS {$entity}_int
    ON {$entity}.entity_id = {$entity}_int.entity_id
    AND attribute.attribute_id = {$entity}_int.attribute_id
    AND attribute.backend_type = 'int'
LEFT JOIN {$this->tablePrefix}{$entity}_entity_text AS {$entity}_text
    ON {$entity}.entity_id = {$entity}_text.entity_id
    AND attribute.attribute_id = {$entity}_text.attribute_id
    AND attribute.backend_type = 'text'
LEFT JOIN {$this->tablePrefix}{$entity}_entity_decimal AS {$entity}_decimal
    ON {$entity}.entity_id = {$entity}_decimal.entity_id
    AND attribute.attribute_id = {$entity}_decimal.attribute_id
    AND attribute.backend_type = 'decimal'
LEFT JOIN {$this->tablePrefix}{$entity}_entity_datetime AS {$entity}_datetime
    ON {$entity}.entity_id = {$entity}_datetime.entity_id
    AND attribute.attribute_id = {$entity}_datetime.attribute_id
    AND attribute.backend_type = 'datetime'
WHERE {$entity}.entity_id IN (?)
AND (attribute.is_user_defined = 0 OR attribute.attribute_code IN (?))
AND attribute.backend_type != 'static'
AND attribute.frontend_input IS NOT NULL
GROUP BY {$entity}.entity_id, attribute_code, value;
SQL;

        return $this->connection->executeQuery(
            $sql,
            [$ids, $customAttributes],
            [Connection::PARAM_STR_ARRAY, Connection::PARAM_STR_ARRAY]
        )->fetchAll(\PDO::FETCH_GROUP | \PDO::FETCH_ASSOC);
    }

    protected function appendAttributes(array &$fetchedEntities, array $fetchDefaultAttributes): void
    {
        foreach ($fetchedEntities as &$fetchedEntity) {
            if (isset($fetchDefaultAttributes[$fetchedEntity['entity_id']])) {
                $attributes = $fetchDefaultAttributes[$fetchedEntity['entity_id']];
                $preparedAttributes = \array_combine(
                    \array_column($attributes, 'attribute_code'),
                    \array_column($attributes, 'value')
                );
                $fetchedEntity = \array_merge($fetchedEntity, $preparedAttributes);
            }
        }
    }

    protected function groupByProperty(array $resultSet, string $property): array
    {
        $groupedResult = [];
        foreach ($resultSet as $result) {
            $groupedResult[$result[$property]][] = $result;
        }

        return $groupedResult;
    }

    protected function getDataSetEntity(MigrationContextInterface $migrationContext): ?string
    {
        $dataSet = $migrationContext->getDataSet();
        if ($dataSet === null) {
            return null;
        }

        return $dataSet::getEntity();
    }

    /**
     * @param array|bool|string|string[] $mixed
     *
     * @return array|bool|string|string[]
     */
    private function childrenUtf8ize($mixed)
    {
        if (\is_array($mixed)) {
            foreach ($mixed as $key => $value) {
                $mixed[$key] = $this->childrenUtf8ize($value);
            }
        } elseif (\is_string($mixed)) {
            return \mb_convert_encoding($mixed, 'UTF-8', 'UTF-8');
        }

        return $mixed;
    }
}
