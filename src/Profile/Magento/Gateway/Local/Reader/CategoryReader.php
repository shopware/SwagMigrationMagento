<?php declare(strict_types=1);

namespace Swag\MigrationMagento\Profile\Magento\Gateway\Local\Reader;

use Doctrine\DBAL\Connection;
use Swag\MigrationMagento\Profile\Magento\Magento19Profile;
use SwagMigrationAssistant\Migration\DataSelection\DefaultEntities;
use SwagMigrationAssistant\Migration\MigrationContextInterface;

class CategoryReader extends AbstractReader implements LocalReaderInterface
{
    public function supports(MigrationContextInterface $migrationContext): bool
    {
        return $migrationContext->getProfile() instanceof Magento19Profile
            && $migrationContext->getDataSet()::getEntity() === DefaultEntities::CATEGORY;
    }

    public function read(MigrationContextInterface $migrationContext, array $params = []): array
    {
        $this->setConnection($migrationContext);

        $ids = $this->fetchIdentifiers('catalog_category_entity', 'entity_id', $migrationContext->getOffset(), $migrationContext->getLimit());
        $fetchedCategories = $this->fetchCategories($ids);

        foreach ($fetchedCategories as &$category) {
            $category['defaultLocale'] = str_replace('_', '-', $category['defaultLocale']);
        }

        return $this->cleanupResultSet($fetchedCategories);
    }

    public function fetchCategories(array $ids): array
    {
        $sql = '
        SELECT
               category.*,
               name.value AS name,
               description.value AS description,
               status.value AS status,
               sibling.entity_id as previousSiblingId,
               defaultLocale.value as defaultLocale
        FROM
             catalog_category_entity AS category
        
               LEFT JOIN catalog_category_entity_varchar as name ON
                 category.entity_id = name.entity_id
                 AND name.attribute_id = 41
                 AND name.store_id = 0
        
               LEFT JOIN catalog_category_entity_int status ON
                 category.entity_id = status.entity_id
                 AND status.attribute_id = 42
                 AND status.store_id = 0
                 
               LEFT JOIN catalog_category_entity_text AS description ON
                 category.entity_id = description.entity_id
                 AND description.attribute_id = 44
                 AND description.store_id = 0
                 
               LEFT JOIN core_config_data AS defaultLocale ON
                defaultLocale.scope = \'default\' AND defaultLocale.path = \'general/locale/code\'
                 
                LEFT JOIN catalog_category_entity AS sibling ON sibling.entity_id = (SELECT previous.entity_id
                                                                            FROM (SELECT sub_category.entity_id,
                                                                                         sub_category.parent_id,
                                                                                         IFNULL(sub_category.position,
                                                                                                IFNULL(
                                                                                                  (SELECT new_position.position + sub_category.entity_id
                                                                                                   FROM catalog_category_entity new_position
                                                                                                   WHERE sub_category.parent_id = new_position.parent_id
                                                                                                   ORDER BY new_position.position DESC
                                                                                                   LIMIT 1),
                                                                                                  sub_category.entity_id)) position
                                                                                  FROM catalog_category_entity sub_category) previous
                                                                            WHERE previous.position <
                                                                                  IFNULL(category.position, IFNULL(
                                                                                                              (SELECT previous.position + category.entity_id
                                                                                                               FROM catalog_category_entity previous
                                                                                                               WHERE category.parent_id = previous.parent_id
                                                                                                               ORDER BY previous.position DESC
                                                                                                               LIMIT 1),
                                                                                                              category.entity_id))
                                                                              AND category.parent_id = previous.parent_id
                                                                            ORDER BY previous.position DESC
                                                                            LIMIT 1) 
         WHERE category.entity_id IN (?)
        
        ORDER BY level, position
        ';

        return $this->connection->executeQuery(
            $sql,
            [$ids],
            [Connection::PARAM_STR_ARRAY]
        )->fetchAll(\PDO::FETCH_ASSOC);
    }
}
