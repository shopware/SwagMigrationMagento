<?php declare(strict_types=1);

namespace Swag\MigrationMagento\Profile\Magento\Converter;

use Shopware\Core\Framework\Context;
use Swag\MigrationMagento\Profile\Magento\DataSelection\DataSet\ProductReviewDataSet;
use Swag\MigrationMagento\Profile\Magento\Magento19Profile;
use SwagMigrationAssistant\Migration\Converter\ConvertStruct;
use SwagMigrationAssistant\Migration\DataSelection\DefaultEntities;
use SwagMigrationAssistant\Migration\Logging\Log\AssociationRequiredMissingLog;
use SwagMigrationAssistant\Migration\Logging\Log\EmptyNecessaryFieldRunLog;
use SwagMigrationAssistant\Migration\MigrationContextInterface;

class ProductReviewConverter extends MagentoConverter
{
    /**
     * @var string
     */
    protected $connectionId;

    public function supports(MigrationContextInterface $migrationContext): bool
    {
        return $migrationContext->getProfile()->getName() === Magento19Profile::PROFILE_NAME
            && $migrationContext->getDataSet()::getEntity() === ProductReviewDataSet::getEntity();
    }

    public function getSourceIdentifier(array $data): string
    {
        return $data['review_id'];
    }

    public function convert(array $data, Context $context, MigrationContextInterface $migrationContext): ConvertStruct
    {
        $this->generateChecksum($data);
        $originalData = $data;
        $this->connectionId = $migrationContext->getConnection()->getId();

        $converted = [];
        $this->mainMapping = $this->mappingService->getOrCreateMapping(
            $this->connectionId,
            DefaultEntities::PRODUCT_REVIEW,
            $data['review_id'],
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

            return new ConvertStruct(null, $originalData);
        }
        $converted['productId'] = $mapping['entityUuid'];
        $this->mappingIds[] = $mapping['id'];

        if (!isset($data['customer_id'])) {
            new EmptyNecessaryFieldRunLog(
                $migrationContext->getRunUuid(),
                DefaultEntities::PRODUCT_REVIEW,
                $data['review_id'],
                'customer_id'
            );

            return new ConvertStruct(null, $data);
        }

        $mapping = $this->mappingService->getMapping(
            $this->connectionId,
            DefaultEntities::CUSTOMER,
            $data['customer_id'],
            $context
        );

        if ($mapping === null) {
            $this->loggingService->addLogEntry(
                new AssociationRequiredMissingLog(
                    $migrationContext->getRunUuid(),
                    DefaultEntities::CUSTOMER,
                    $data['customer_id'],
                    DefaultEntities::PRODUCT_REVIEW
                )
            );

            return new ConvertStruct(null, $originalData);
        }
        $converted['customerId'] = $mapping['entityUuid'];
        $this->mappingIds[] = $mapping['id'];

        $mapping = $this->mappingService->getMapping(
            $this->connectionId,
            DefaultEntities::SALES_CHANNEL . '_stores',
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

            return new ConvertStruct(null, $originalData);
        }
        $converted['salesChannelId'] = $mapping['entityUuid'];
        $this->mappingIds[] = $mapping['id'];

        $converted['languageId'] = $this->mappingService->getLanguageUuid(
            $this->connectionId,
            $data['locale'],
            $context
        );

        if ($converted['languageId'] === null) {
            $this->loggingService->addLogEntry(
                new AssociationRequiredMissingLog(
                    $migrationContext->getRunUuid(),
                    DefaultEntities::LANGUAGE,
                    $data['locale'],
                    DefaultEntities::PRODUCT_REVIEW
                )
            );

            return new ConvertStruct(null, $originalData);
        }

        $this->convertValue($converted, 'title', $data, 'title');
        if (empty($converted['title'])) {
            $converted['title'] = mb_substr($data['detail'], 0, 30) . '...';
        }
        $this->convertValue($converted, 'content', $data, 'detail');

        if (isset($data['status'])) {
            $converted['status'] = $data['status'] === 'Approved';
        }

        if (isset($data['ratings'])) {
            $this->setPoints($converted, $data);
        }

        $this->updateMainMapping($migrationContext, $context);

        return new ConvertStruct($converted, $data, $this->mainMapping['id']);
    }

    protected function setPoints(array &$converted, array &$data): void
    {
        $counting = count($data['ratings']);
        if ($counting === 0) {
            $converted['points'] = 0;

            return;
        }

        $sumRating = 0;
        foreach ($data['ratings'] as $rating) {
            $sumRating += $rating['value'];
        }

        $converted['points'] = round($sumRating / $counting);
    }
}
