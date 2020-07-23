<?php declare(strict_types=1);
/*
 * (c) shopware AG <info@shopware.com>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Swag\MigrationMagento\Profile\Magento\Converter;

use Shopware\Core\Framework\Context;
use Swag\MigrationMagento\Profile\Magento\DataSelection\DefaultEntities as MagentoDefaultEntities;
use SwagMigrationAssistant\Migration\Converter\ConvertStruct;
use SwagMigrationAssistant\Migration\DataSelection\DefaultEntities;
use SwagMigrationAssistant\Migration\Logging\Log\AssociationRequiredMissingLog;
use SwagMigrationAssistant\Migration\MigrationContextInterface;

abstract class CrossSellingConverter extends MagentoConverter
{
    /**
     * @var string
     */
    protected $runId;

    /**
     * @var string
     */
    protected $oldIdentifier;

    /**
     * @var string
     */
    protected $connectionId;

    /**
     * @var Context
     */
    protected $context;

    public function getSourceIdentifier(array $data): string
    {
        return $data['type'] . '_' . $data['sourceProductId'] . '_' . $data['linked_product_id'];
    }

    public function convert(array $data, Context $context, MigrationContextInterface $migrationContext): ConvertStruct
    {
        $this->generateChecksum($data);
        $this->runId = $migrationContext->getRunUuid();
        $this->migrationContext = $migrationContext;
        $this->oldIdentifier = $data['link_id'];
        $this->context = $context;

        $connection = $migrationContext->getConnection();
        $this->connectionId = '';
        if ($connection !== null) {
            $this->connectionId = $connection->getId();
        }

        $this->mainMapping = $this->mappingService->getOrCreateMapping(
            $this->connectionId,
            $data['type'],
            $data['sourceProductId'],
            $this->context,
            $this->checksum
        );

        $converted = [];
        $converted['id'] = $this->mainMapping['entityUuid'];

        $sourceProductMapping = $this->mappingService->getMapping(
            $this->connectionId,
            DefaultEntities::PRODUCT,
            $data['sourceProductId'],
            $this->context
        );

        if ($sourceProductMapping === null) {
            $this->loggingService->addLogEntry(new AssociationRequiredMissingLog(
                $this->runId,
                DefaultEntities::PRODUCT,
                $data['sourceProductId'],
                $data['type']
            ));

            return new ConvertStruct(null, $data);
        }
        $this->mappingIds[] = $sourceProductMapping['id'];

        $relatedProductMapping = $this->mappingService->getMapping(
            $this->connectionId,
            DefaultEntities::PRODUCT,
            $data['linked_product_id'],
            $this->context
        );

        if ($relatedProductMapping === null) {
            $this->loggingService->addLogEntry(new AssociationRequiredMissingLog(
                $this->runId,
                DefaultEntities::PRODUCT,
                $data['linked_product_id'],
                $data['type']
            ));

            return new ConvertStruct(null, $data);
        }
        $this->mappingIds[] = $relatedProductMapping['id'];

        $converted['name'] = 'Related products';
        if ($data['type'] === MagentoDefaultEntities::CROSS_SELLING_TYPE) {
            $converted['name'] = 'Cross-sells';
        }

        if ($data['type'] === MagentoDefaultEntities::UP_SELLING_TYPE) {
            $converted['name'] = 'Up-sells';
        }

        $relationMapping = $this->mappingService->getOrCreateMapping(
            $this->connectionId,
            $data['type'] . '_relation',
            $data['sourceProductId'] . '_' . $data['linked_product_id'],
            $context
        );

        $converted['type'] = 'productList';
        $converted['active'] = true;
        $converted['productId'] = $sourceProductMapping['entityUuid'];
        $converted['assignedProducts'] = [
            [
                'id' => $relationMapping['entityUuid'],
                'position' => $data['position'],
                'productId' => $relatedProductMapping['entityUuid'],
            ],
        ];

        unset(
            $data['type'],
            $data['link_id'],
            $data['product_id'],
            $data['linked_product_id'],
            $data['link_type_id'],
            $data['position'],
            $data['sourceProductId']
        );

        $resultData = $data;
        if (empty($resultData)) {
            $resultData = null;
        }

        return new ConvertStruct($converted, $resultData, $this->mainMapping['id']);
    }
}
