<?php declare(strict_types=1);

namespace Swag\MigrationMagento\Profile\Magento\Gateway\Local\Reader;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\DBAL\Schema\Column;
use SwagMigrationAssistant\Migration\MigrationContextInterface;
use SwagMigrationAssistant\Migration\Profile\ReaderInterface;
use SwagMigrationAssistant\Profile\Shopware\Gateway\Connection\ConnectionFactoryInterface;

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

    public function __construct(ConnectionFactoryInterface $connectionFactory)
    {
        $this->connectionFactory = $connectionFactory;
    }

    protected function setConnection(MigrationContextInterface $migrationContext): void
    {
        $this->connection = $this->connectionFactory->createDatabaseConnection($migrationContext);
    }

    protected function utf8ize($mixed)
    {
        if (is_array($mixed)) {
            foreach ($mixed as $key => $value) {
                $mixed[$key] = $this->utf8ize($value);
            }
        } elseif (is_string($mixed)) {
            return mb_convert_encoding($mixed, 'UTF-8', 'UTF-8');
        }

        return $mixed;
    }

    protected function addTableSelection(QueryBuilder $query, string $table, string $tableAlias): void
    {
        $columns = $this->connection->getSchemaManager()->listTableColumns($table);

        /** @var Column $column */
        foreach ($columns as $column) {
            $selection = str_replace(
                ['#tableAlias#', '#column#'],
                [$tableAlias, $column->getName()],
                '`#tableAlias#`.`#column#` as `#tableAlias#.#column#`'
            );

            $query->addSelect($selection);
        }
    }

    protected function buildArrayFromChunks(array &$array, array $path, string $fieldKey, $value): void
    {
        $key = array_shift($path);

        if (empty($key)) {
            $array[$fieldKey] = $value;
        } elseif (empty($path)) {
            $array[$key][$fieldKey] = $value;
        } else {
            if (!isset($array[$key]) || !is_array($array[$key])) {
                $array[$key] = [];
            }
            $this->buildArrayFromChunks($array[$key], $path, $fieldKey, $value);
        }
    }

    protected function cleanupResultSet(array &$data): array
    {
        foreach ($data as $key => &$value) {
            if (is_array($value)) {
                if (empty(array_filter($value))) {
                    unset($data[$key]);

                    continue;
                }

                $this->cleanupResultSet($value);

                if (empty(array_filter($value))) {
                    unset($data[$key]);

                    continue;
                }
            }
        }

        return $data;
    }

    protected function fetchIdentifiers(string $table, string $identitfier = 'id', int $offset = 0, int $limit = 250): array
    {
        $query = $this->connection->createQueryBuilder();

        $query->select($identitfier);
        $query->from($table);
        $query->addOrderBy($identitfier);

        $query->setFirstResult($offset);
        $query->setMaxResults($limit);

        return $query->execute()->fetchAll(\PDO::FETCH_COLUMN);
    }

    protected function mapData(array $data, array $result = [], array $pathsToRemove = []): array
    {
        foreach ($data as $key => $value) {
            if (is_numeric($key)) {
                $result[$key] = $this->mapData($value, [], $pathsToRemove);
            } else {
                $paths = explode('.', $key);
                $fieldKey = $paths[count($paths) - 1];
                $chunks = explode('_', $paths[0]);

                if (!empty($pathsToRemove)) {
                    $chunks = array_diff($chunks, $pathsToRemove);
                }
                $this->buildArrayFromChunks($result, $chunks, $fieldKey, $value);
            }
        }

        return $result;
    }

    /**
     * Returns the sql statement to select the shop system article attribute fields
     */
    protected function createTableSelect(
        $type = 'catalog_product',
        $attributes = null,
        $prefix = '',
        $store_id = null
    ): string {
        $sql = "
			SELECT
				ea.attribute_code 	as `name`,
				ea.attribute_id 	as `id`,
				ea.backend_type 	as `type`,
				ea.is_required		as `required`
			FROM eav_attribute ea, eav_entity_type et
			WHERE ea.`entity_type_id` = et.entity_type_id
			AND et.entity_type_code = ?
			AND ea.frontend_input != ''
		";
        if (!empty($attributes)) {
            $sql .= 'AND ea.attribute_code IN (?)';
        } else {
            $sql .= 'ORDER BY `required` DESC, `name`';
        }

        $attribute_fields = $this->connection->executeQuery(
            $sql,
            [$type, $attributes],
            [\PDO::PARAM_STR, Connection::PARAM_STR_ARRAY]
        )->fetchAll(\PDO::FETCH_GROUP | \PDO::FETCH_UNIQUE);

        if (empty($attributes)) {
            $attributes = array_keys($attribute_fields);
        }

        $select_fields = [];
        $join_fields = '';

        // Do not use quoteTable for aliases!
        $type_quoted = "`{$prefix}{$type}`";

        foreach ($attributes as $attribute) {
            $attribute_alias = $prefix . $attribute;

            if (empty($attribute_fields[$attribute])) {
                $join_fields .= "
					LEFT JOIN (SELECT 1 as attribute_id, NULL as value) as `$attribute_alias`
					ON 1
				";
            } else {
                if ($attribute_fields[$attribute]['type'] === 'static') {
                    $select_fields[] = "{$type_quoted}.{$attribute} as $attribute_alias";
                } else {
                    $table = $type . '_entity_' . $attribute_fields[$attribute]['type'];
                    $join_fields .= "
						LEFT JOIN {$table} `$attribute_alias`
						ON	`{$attribute_alias}`.attribute_id = {$attribute_fields[$attribute]['id']}
						AND `{$attribute_alias}`.entity_id = {$type_quoted}.entity_id
					";
                    if ($store_id !== null) {
                        $join_fields .= "
						AND {$attribute_alias}.store_id = {$store_id}
						";
                    }
                    $select_fields[] = "{$attribute_alias}.value as `{$attribute_alias}`";
                }
            }
        }

        return $join_fields;
    }
}
