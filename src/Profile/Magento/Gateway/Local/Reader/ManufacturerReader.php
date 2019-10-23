<?php declare(strict_types=1);

namespace Swag\MigrationMagento\Profile\Magento\Gateway\Local\Reader;

use Swag\MigrationMagento\Profile\Magento\Magento19Profile;
use SwagMigrationAssistant\Migration\DataSelection\DefaultEntities;
use SwagMigrationAssistant\Migration\MigrationContextInterface;

class ManufacturerReader extends AbstractReader implements LocalReaderInterface
{
    public function supports(MigrationContextInterface $migrationContext): bool
    {
        return $migrationContext->getProfile() instanceof Magento19Profile
            && $migrationContext->getDataSet()::getEntity() === DefaultEntities::PRODUCT_MANUFACTURER;
    }

    public function read(MigrationContextInterface $migrationContext, array $params = []): array
    {
        $this->setConnection($migrationContext);

        $fetchedManufacturers = $this->fetchManufacturers();

        $manufacturers = [];
        foreach ($fetchedManufacturers as $manufacturer) {
            if (!empty($manufacturer) && isset($manufacturer[0])) {
                $detail = $manufacturer[0];
                unset($manufacturer[0]);

                $manufacturers[] = [
                    'detail' => $detail,
                    'tanslations' => array_values($manufacturer),
                ];
            }
        }

        return $manufacturers;
    }

    private function fetchManufacturers(): array
    {
        $query = $this->connection->createQueryBuilder();

        $query->select([
            'optionValue.option_id as identifier',
            'optionValue.option_id',
            'optionValue.value',
            'optionValue.store_id',
        ]);
        $query->from('eav_attribute_option_value', 'optionValue');

        $query->innerJoin('optionValue', 'eav_attribute_option', 'attributeOption', 'optionValue.option_id = attributeOption.option_id');

        $query->innerJoin('optionValue', 'eav_attribute', 'attribute', 'attribute.attribute_id = attributeOption.attribute_id AND attribute.attribute_code = :code');
        $query->setParameter('code', 'manufacturer');

        $query->groupBy([
            'optionValue.option_id',
            'optionValue.value',
        ]);

        $query->orderBy('optionValue.store_id');

        return $query->execute()->fetchAll(\PDO::FETCH_GROUP);
    }
}
