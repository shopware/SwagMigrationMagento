<?php declare(strict_types=1);
/*
 * (c) shopware AG <info@shopware.com>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Swag\MigrationMagento\Profile\Magento\Converter;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use SwagMigrationAssistant\Migration\Converter\ConvertStruct;
use SwagMigrationAssistant\Migration\DataSelection\DefaultEntities;
use SwagMigrationAssistant\Migration\MigrationContextInterface;

#[Package('fundamentals@after-sales')]
abstract class ProductMultiSelectPropertyRelationConverter extends MagentoConverter
{
    public function getSourceIdentifier(array $data): string
    {
        return $data['entity_id'] . '_' . $data['option_id'];
    }

    public function convert(array $data, Context $context, MigrationContextInterface $migrationContext): ConvertStruct
    {
        $this->generateChecksum($data);

        $connection = $migrationContext->getConnection();
        $connectionId = $connection->getId();

        $productMapping = $this->mappingService->getMapping(
            $connectionId,
            DefaultEntities::PRODUCT,
            $data['entity_id'],
            $context
        );

        if ($productMapping === null) {
            return new ConvertStruct(null, $data);
        }

        $converted = [];
        $converted['id'] = $productMapping['entityId'];
        $this->mappingIds[] = $productMapping['id'];

        $propertyMapping = $this->mappingService->getMapping(
            $connectionId,
            DefaultEntities::PROPERTY_GROUP_OPTION,
            $data['option_id'],
            $context
        );

        if ($propertyMapping === null) {
            return new ConvertStruct(null, $data);
        }

        $converted['properties'][] = ['id' => $propertyMapping['entityId']];

        unset(
            $data['entity_id'],
            $data['option_id'],
            $data['option_value']
        );

        $returnData = null;

        if ($data !== []) {
            $returnData = $data;
        }

        return new ConvertStruct($converted, $returnData);
    }
}
