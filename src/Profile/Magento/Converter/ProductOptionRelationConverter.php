<?php declare(strict_types=1);
/*
 * (c) shopware AG <info@shopware.com>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Swag\MigrationMagento\Profile\Magento\Converter;

use Shopware\Core\Framework\Context;
use SwagMigrationAssistant\Migration\Converter\ConvertStruct;
use SwagMigrationAssistant\Migration\DataSelection\DefaultEntities;
use SwagMigrationAssistant\Migration\MigrationContextInterface;

abstract class ProductOptionRelationConverter extends MagentoConverter
{
    public function getSourceIdentifier(array $data): string
    {
        return $data['entity_id'] . '_' . $data['option_id'];
    }

    public function convert(array $data, Context $context, MigrationContextInterface $migrationContext): ConvertStruct
    {
        $this->generateChecksum($data);

        $connection = $migrationContext->getConnection();
        $connectionId = '';
        if ($connection !== null) {
            $connectionId = $connection->getId();
        }

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
        $converted['id'] = $productMapping['entityUuid'];
        $this->mappingIds = $productMapping['id'];

        $optionMapping = $this->mappingService->getMapping(
            $connectionId,
            DefaultEntities::PROPERTY_GROUP_OPTION,
            $data['option_id'],
            $context
        );

        if ($optionMapping === null) {
            return new ConvertStruct(null, $data);
        }

        $converted['options'][] = ['id' => $optionMapping['entityUuid']];
        $this->mappingIds = $productMapping['id'];

        unset(
            $data['entity_id'],
            $data['option_id']
        );

        $returnData = $data;
        if (empty($returnData)) {
            $returnData = null;
        }

        return new ConvertStruct($converted, $returnData);
    }
}
