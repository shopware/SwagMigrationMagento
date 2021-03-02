<?php declare(strict_types=1);
/*
 * (c) shopware AG <info@shopware.com>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Swag\MigrationMagento\Profile\Magento\Gateway\Local\Reader;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver\ResultStatement;
use SwagMigrationAssistant\Migration\DataSelection\DefaultEntities;
use SwagMigrationAssistant\Migration\MigrationContextInterface;
use SwagMigrationAssistant\Migration\TotalStruct;

abstract class ProductCustomFieldReader extends AbstractReader
{
    public function read(MigrationContextInterface $migrationContext, array $params = []): array
    {
        $this->setConnection($migrationContext);

        $customFields = $this->fetchCustomFields($migrationContext);
        $ids = \array_column($customFields, 'attribute_id');
        $options = $this->fetchSelectOptions($ids);
        $optionIds = [];

        foreach ($options as $attributeOptions) {
            foreach ($attributeOptions as $option) {
                $optionIds[] = $option['option_id'];
            }
        }
        $attributeTranslations = $this->fetchAttributeTranslations($ids);
        $optionTranslations = $this->fetchAttributeOptionTranslations($optionIds);
        $locales = $this->fetchLocales();

        foreach ($options as &$optionList) {
            foreach ($optionList as &$option) {
                $optionId = $option['option_id'];

                if (isset($optionTranslations[$optionId])) {
                    $untranslatedLocales = $locales;
                    foreach ($optionTranslations[$optionId] as $optionTranslation) {
                        $storeId = $optionTranslation['store_id'];
                        $value = $optionTranslation['value'];

                        if (isset($locales[$storeId])) {
                            unset($untranslatedLocales[$storeId]);
                            $translation = [
                                'value' => $value,
                                'locale' => $locales[$storeId],
                            ];
                            $option['translations'][] = $translation;
                        }
                    }
                    $this->setFallbackTranslations($option, $untranslatedLocales, 'value');
                } else {
                    $this->setFallbackTranslations($option, $locales, 'value');
                }
            }
        }

        foreach ($customFields as &$customField) {
            $attributeId = $customField['attribute_id'];
            if (isset($options[$attributeId])) {
                $customField['options'] = $options[$attributeId];
            }

            if (isset($attributeTranslations[$attributeId])) {
                $untranslatedLocales = $locales;
                foreach ($attributeTranslations[$attributeId] as $attributeTranslation) {
                    $storeId = $attributeTranslation['store_id'];
                    $value = $attributeTranslation['value'];

                    if (isset($locales[$storeId])) {
                        unset($untranslatedLocales[$storeId]);
                        $translation = [
                            'value' => $value,
                            'locale' => $locales[$storeId],
                        ];
                        $customField['translations'][] = $translation;
                    }
                }
                $this->setFallbackTranslations($customField, $untranslatedLocales);
            } else {
                $this->setFallbackTranslations($customField, $locales);
            }
        }

        return $this->utf8ize($customFields);
    }

    public function readTotal(MigrationContextInterface $migrationContext): ?TotalStruct
    {
        $this->setConnection($migrationContext);

        $sql = <<<SQL
SELECT COUNT(*)
FROM {$this->tablePrefix}eav_attribute eav
INNER JOIN {$this->tablePrefix}eav_entity_type AS et ON et.entity_type_id = eav.entity_type_id AND et.entity_type_code = 'catalog_product'
INNER JOIN {$this->tablePrefix}eav_entity_attribute AS eea ON eav.attribute_id = eea.attribute_id
INNER JOIN {$this->tablePrefix}eav_attribute_set AS attributeSet ON attributeSet.attribute_set_id = eea.attribute_set_id
WHERE eav.frontend_input != ''
AND eav.is_user_defined = 1
AND eav.attribute_code NOT IN ('manufacturer', 'cost');
SQL;
        $total = (int) $this->connection->executeQuery($sql)->fetchColumn();

        return new TotalStruct(DefaultEntities::PRODUCT_CUSTOM_FIELD, $total);
    }

    protected function fetchCustomFields(MigrationContextInterface $migrationContext): array
    {
        $query = $this->connection->createQueryBuilder();

        $query->from($this->tablePrefix . 'eav_attribute', 'eav');
        $this->addTableSelection($query, $this->tablePrefix . 'eav_attribute', 'eav');
        $query->innerJoin('eav', $this->tablePrefix . 'eav_entity_type', 'et', 'eav.entity_type_id = et.entity_type_id AND et.entity_type_code = \'catalog_product\'');

        $query->innerJoin('et', $this->tablePrefix . 'eav_entity_attribute', 'eea', 'eav.attribute_id = eea.attribute_id');
        $query->innerJoin('eea', $this->tablePrefix . 'eav_attribute_set', 'attributeSet', 'attributeSet.attribute_set_id = eea.attribute_set_id');
        $query->addSelect('eea.attribute_set_id AS setId');
        $query->addSelect('attributeSet.attribute_set_name AS setName');

        $query->where('eav.frontend_input != \'\'');
        $query->andWhere('eav.is_user_defined = 1');
        $query->andWhere('eav.attribute_code NOT IN (\'manufacturer\', \'cost\')');
        $query->addOrderBy('eav.attribute_id');

        $query->setFirstResult($migrationContext->getOffset());
        $query->setMaxResults($migrationContext->getLimit());

        $query = $query->execute();
        if (!($query instanceof ResultStatement)) {
            return [];
        }

        return $this->mapData($query->fetchAll(\PDO::FETCH_ASSOC), [], ['eav', 'setId', 'setName']);
    }

    protected function fetchSelectOptions(array $ids): array
    {
        $sql = <<<SQL
SELECT DISTINCT
    attribute.attribute_id,
    optionValue.option_id,
    optionValue.value
FROM {$this->tablePrefix}eav_attribute_option_value optionValue
INNER JOIN {$this->tablePrefix}eav_attribute_option AS attributeOption ON optionValue.option_id = attributeOption.option_id
INNER JOIN {$this->tablePrefix}eav_attribute AS attribute ON attribute.attribute_id = attributeOption.attribute_id
WHERE attribute.attribute_id IN (?) AND store_id = 0;
SQL;

        return $this->connection->executeQuery($sql, [$ids], [Connection::PARAM_STR_ARRAY])->fetchAll(\PDO::FETCH_GROUP | \PDO::FETCH_ASSOC);
    }

    protected function fetchAttributeOptionTranslations(array $ids): array
    {
        $query = $this->connection->createQueryBuilder();

        $query->addSelect('optionValue.option_id');
        $query->addSelect('optionValue.store_id');
        $query->addSelect('attributeOption.attribute_id');
        $query->addSelect('optionValue.value');
        $query->from($this->tablePrefix . 'eav_attribute_option', 'attributeOption');

        $query->innerJoin('attributeOption', $this->tablePrefix . 'eav_attribute_option_value', 'optionValue', 'optionValue.option_id = attributeOption.option_id AND optionValue.store_id != 0');

        $query->where('attributeOption.option_id IN (:ids)');
        $query->setParameter('ids', $ids, Connection::PARAM_INT_ARRAY);

        $query = $query->execute();
        if (!($query instanceof ResultStatement)) {
            return [];
        }

        return $query->fetchAll(\PDO::FETCH_GROUP | \PDO::FETCH_ASSOC);
    }

    protected function fetchAttributeTranslations(array $ids): array
    {
        $query = $this->connection->createQueryBuilder();

        $query->addSelect('attributeLabel.attribute_id AS identifier');
        $query->addSelect('attributeLabel.attribute_id');
        $query->addSelect('attributeLabel.store_id');
        $query->addSelect('attributeLabel.value');
        $query->from($this->tablePrefix . 'eav_attribute_label', 'attributeLabel');

        $query->where('attributeLabel.attribute_id IN (:ids)');
        $query->setParameter('ids', $ids, Connection::PARAM_INT_ARRAY);

        $query = $query->execute();
        if (!($query instanceof ResultStatement)) {
            return [];
        }

        return $query->fetchAll(\PDO::FETCH_GROUP | \PDO::FETCH_ASSOC);
    }

    protected function fetchLocales(): array
    {
        $query = $this->connection->createQueryBuilder();

        $query->addSelect('scope_id AS store_id');
        $query->addSelect('value AS locale');
        $query->from($this->tablePrefix . 'core_config_data', 'locales');

        $query->orWhere('scope = \'stores\' AND path = \'general/locale/code\'');

        $query = $query->execute();
        if (!($query instanceof ResultStatement)) {
            return [];
        }

        $locales = $query->fetchAll(\PDO::FETCH_KEY_PAIR);
        foreach ($locales as $storeId => &$locale) {
            $locale = \str_replace('_', '-', $locale);
        }

        return $locales;
    }

    protected function setFallbackTranslations(array &$valueObject, array $locales, string $property = 'frontend_label'): void
    {
        foreach ($locales as $locale) {
            $translation = [
                'value' => $valueObject[$property],
                'locale' => $locale,
            ];
            $valueObject['translations'][] = $translation;
        }
    }
}
