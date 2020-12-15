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

abstract class ProductReviewConverter extends MagentoConverter
{
    /**
     * @var string
     */
    protected $connectionId;

    /**
     * @var string
     */
    protected $oldIdentifier;

    public function getSourceIdentifier(array $data): string
    {
        return $data['review_id'];
    }

    public function convert(array $data, Context $context, MigrationContextInterface $migrationContext): ConvertStruct
    {
        $this->generateChecksum($data);
        $this->originalData = $data;
        $this->oldIdentifier = $data['review_id'];
        unset($data['review_id']);

        $connection = $migrationContext->getConnection();
        $this->connectionId = '';
        if ($connection !== null) {
            $this->connectionId = $connection->getId();
        }

        $converted = [];
        $this->mainMapping = $this->mappingService->getOrCreateMapping(
            $this->connectionId,
            DefaultEntities::PRODUCT_REVIEW,
            $this->oldIdentifier,
            $context,
            $this->checksum
        );
        $converted['id'] = $this->mainMapping['entityUuid'];

        $mapping = $this->mappingService->getMapping(
            $this->connectionId,
            DefaultEntities::PRODUCT,
            $data['productId'],
            $context
        );

        if ($mapping === null) {
            $this->loggingService->addLogEntry(
                new AssociationRequiredMissingLog(
                    $migrationContext->getRunUuid(),
                    DefaultEntities::PRODUCT,
                    $data['productId'],
                    DefaultEntities::PRODUCT_REVIEW
                )
            );

            return new ConvertStruct(null, $this->originalData);
        }
        $converted['productId'] = $mapping['entityUuid'];
        $this->mappingIds[] = $mapping['id'];
        unset($data['productId']);

        if (isset($data['customer_id'])) {
            $mapping = $this->mappingService->getMapping(
                $this->connectionId,
                DefaultEntities::CUSTOMER,
                $data['customer_id'],
                $context
            );

            if ($mapping !== null) {
                $converted['customerId'] = $mapping['entityUuid'];
                $this->mappingIds[] = $mapping['id'];
            }
            unset($data['customer_id']);
        }

        $mapping = $this->mappingService->getMapping(
            $this->connectionId,
            MagentoDefaultEntities::STORE,
            $data['store_id'],
            $context
        );

        if ($mapping === null) {
            $this->loggingService->addLogEntry(
                new AssociationRequiredMissingLog(
                    $migrationContext->getRunUuid(),
                    DefaultEntities::SALES_CHANNEL,
                    $data['store_id'],
                    DefaultEntities::PRODUCT_REVIEW
                )
            );

            return new ConvertStruct(null, $this->originalData);
        }
        $converted['salesChannelId'] = $mapping['entityUuid'];
        $this->mappingIds[] = $mapping['id'];

        $languageMapping = $this->mappingService->getMapping(
            $this->connectionId,
            MagentoDefaultEntities::STORE_LANGUAGE,
            $data['store_id'],
            $context
        );

        if ($languageMapping === null) {
            $this->loggingService->addLogEntry(
                new AssociationRequiredMissingLog(
                    $migrationContext->getRunUuid(),
                    MagentoDefaultEntities::STORE_LANGUAGE,
                    $data['store_id'],
                    DefaultEntities::PRODUCT_REVIEW
                )
            );

            return new ConvertStruct(null, $this->originalData);
        }
        $converted['languageId'] = $languageMapping['entityUuid'];
        $this->mappingIds[] = $languageMapping['id'];
        unset($data['store_id']);

        if (isset($data['created_at'])) {
            $this->convertValue($converted, 'createdAt', $data, 'created_at', self::TYPE_DATETIME);
        }

        $this->convertValue($converted, 'title', $data, 'title');
        $this->convertValue($converted, 'externalUser', $data, 'nickname');
        if (empty($converted['title'])) {
            $converted['title'] = \mb_substr($data['detail'], 0, 30) . '...';
        }
        $this->convertValue($converted, 'content', $data, 'detail');

        if (isset($data['status'])) {
            $converted['status'] = $data['status'] === 'Approved';
        }
        unset($data['status']);

        if (isset($data['ratings'])) {
            $this->setPoints($converted, $data);
        }
        unset($data['ratings']);

        $this->updateMainMapping($migrationContext, $context);

        // There is no equivalent field
        unset(
            $data['detail_id']
        );

        $resultData = $data;
        if (empty($resultData)) {
            $resultData = null;
        }

        return new ConvertStruct($converted, $resultData, $this->mainMapping['id']);
    }

    protected function setPoints(array &$converted, array &$data): void
    {
        $counting = \count($data['ratings']);
        if ($counting === 0) {
            $converted['points'] = 0;

            return;
        }

        $sumRating = 0;
        foreach ($data['ratings'] as $rating) {
            $sumRating += $rating['value'];
        }

        $converted['points'] = \round($sumRating / $counting);
    }
}
