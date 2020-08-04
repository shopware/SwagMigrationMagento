<?php declare(strict_types=1);
/*
 * (c) shopware AG <info@shopware.com>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Swag\MigrationMagento\Profile\Magento\Gateway\Local\Reader;

use Doctrine\DBAL\Connection;
use SwagMigrationAssistant\Migration\MigrationContextInterface;

abstract class ManufacturerReader extends AbstractReader
{
    public function read(MigrationContextInterface $migrationContext, array $params = []): array
    {
        $this->setConnection($migrationContext);
        $fetchedManufacturers = $this->fetchManufacturers();
        $ids = \array_column($fetchedManufacturers, 'option_id');
        $fetchedTranslations = $this->fetchTranslations($ids);

        foreach ($fetchedManufacturers as &$manufacturer) {
            $optionId = $manufacturer['option_id'];

            if (isset($fetchedTranslations[$optionId])) {
                foreach ($fetchedTranslations[$optionId] as $translation) {
                    $store_id = $translation['store_id'];
                    $attribute_id = $translation['attribute_id'];
                    $value = $translation['value'];

                    $manufacturer['translations'][$store_id]['name']['value'] = $value;
                    $manufacturer['translations'][$store_id]['name']['attribute_id'] = $attribute_id;
                }
            }
        }

        return $fetchedManufacturers;
    }

    protected function fetchManufacturers(): array
    {
        $sql = <<<SQL
SELECT
    optionValue.option_id,
    optionValue.value
FROM {$this->tablePrefix}eav_attribute_option_value optionValue
INNER JOIN {$this->tablePrefix}eav_attribute_option AS attributeOption ON optionValue.option_id = attributeOption.option_id
INNER JOIN {$this->tablePrefix}eav_attribute AS attribute ON attribute.attribute_id = attributeOption.attribute_id AND attribute.attribute_code = "manufacturer"
WHERE optionValue.store_id = 0
SQL;

        return $this->connection->executeQuery($sql)->fetchAll();
    }

    protected function fetchTranslations(array $ids): array
    {
        $sql = <<<SQL
SELECT
    optionValue.option_id,
    optionValue.store_id,
    attributeOption.attribute_id,
    optionValue.value
FROM {$this->tablePrefix}eav_attribute_option_value optionValue
INNER JOIN {$this->tablePrefix}eav_attribute_option AS attributeOption ON optionValue.option_id = attributeOption.option_id
INNER JOIN {$this->tablePrefix}eav_attribute AS attribute ON attribute.attribute_id = attributeOption.attribute_id AND attribute.attribute_code = "manufacturer"
WHERE optionValue.store_id != 0 AND optionValue.option_id IN (?);
SQL;

        return $this->connection->executeQuery($sql, [$ids], [Connection::PARAM_INT_ARRAY])->fetchAll(\PDO::FETCH_GROUP | \PDO::FETCH_ASSOC);
    }
}
