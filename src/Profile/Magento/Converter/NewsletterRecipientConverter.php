<?php declare(strict_types=1);
/*
 * (c) shopware AG <info@shopware.com>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Swag\MigrationMagento\Profile\Magento\Converter;

use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Swag\MigrationMagento\Profile\Magento\DataSelection\DefaultEntities as MagentoDefaultEntities;
use Swag\MigrationMagento\Profile\Magento19\Premapping\Magento19NewsletterRecipientStatusReader;
use SwagMigrationAssistant\Migration\Converter\ConvertStruct;
use SwagMigrationAssistant\Migration\DataSelection\DefaultEntities;
use SwagMigrationAssistant\Migration\Logging\Log\AssociationRequiredMissingLog;
use SwagMigrationAssistant\Migration\Logging\Log\EmptyNecessaryFieldRunLog;
use SwagMigrationAssistant\Migration\MigrationContextInterface;

abstract class NewsletterRecipientConverter extends MagentoConverter
{
    /**
     * @var string
     */
    protected $connectionId;

    /**
     * @var array
     */
    protected $originalData;

    /**
     * @var Context
     */
    protected $context;

    /**
     * @var string
     */
    protected $runId;

    public function getSourceIdentifier(array $data): string
    {
        return $data['subscriber_id'];
    }

    public function convert(array $data, Context $context, MigrationContextInterface $migrationContext): ConvertStruct
    {
        $this->generateChecksum($data);
        $this->context = $context;
        $this->runId = $migrationContext->getRunUuid();
        $this->originalData = $data;

        $connection = $migrationContext->getConnection();
        $this->connectionId = '';
        if ($connection !== null) {
            $this->connectionId = $connection->getId();
        }

        $converted = [];
        $languageMapping = $this->mappingService->getMapping(
            $this->connectionId,
            MagentoDefaultEntities::STORE_LANGUAGE,
            $data['store_id'],
            $context
        );

        if ($languageMapping === null) {
            $this->loggingService->addLogEntry(new AssociationRequiredMissingLog(
                $migrationContext->getRunUuid(),
                DefaultEntities::LANGUAGE,
                $data['store_id'],
                DefaultEntities::NEWSLETTER_RECIPIENT
            ));

            return new ConvertStruct(null, $data);
        }

        $this->mainMapping = $this->mappingService->getOrCreateMapping(
            $this->connectionId,
            DefaultEntities::NEWSLETTER_RECIPIENT,
            $data['subscriber_id'],
            $context,
            $this->checksum
        );
        $converted['id'] = $this->mainMapping['entityUuid'];
        $converted['languageId'] = $languageMapping['entityUuid'];
        $converted['hash'] = $data['subscriber_confirm_code'];

        $converted['salesChannelId'] = Defaults::SALES_CHANNEL;
        $salesChannelMapping = $this->getSalesChannelMapping($data);
        if ($salesChannelMapping === null) {
            return new ConvertStruct(null, $this->originalData);
        }
        $this->mappingIds[] = $salesChannelMapping['id'];
        $converted['salesChannelId'] = $salesChannelMapping['entityUuid'];

        $this->convertValue($converted, 'email', $data, 'subscriber_email');

        if (isset($data['firstName'])) {
            $this->convertValue($converted, 'firstName', $data, 'firstName');
        }
        if (isset($data['lastName'])) {
            $this->convertValue($converted, 'lastName', $data, 'lastName');
        }
        if (isset($data['title'])) {
            $this->convertValue($converted, 'title', $data, 'title');
        }
        $status = $this->getStatus($data);
        if ($status === null) {
            return new ConvertStruct(null, $this->originalData);
        }
        $converted['status'] = $status;

        $this->updateMainMapping($migrationContext, $context);

        unset(
            $data['subscriber_id'],
            $data['store_id'],
            $data['subscriber_status'],
            $data['subscriber_confirm_code'],
            $data['customer_id'],
            $data['change_status_at']
        );

        $resultData = $data;
        if (empty($resultData)) {
            $resultData = null;
        }

        return new ConvertStruct($converted, $resultData, $this->mainMapping['id']);
    }

    private function getSalesChannelMapping(array $data): ?array
    {
        $salesChannelMapping = $this->mappingService->getMapping(
            $this->connectionId,
            MagentoDefaultEntities::STORE,
            $data['store_id'],
            $this->context
        );

        if ($salesChannelMapping === null) {
            $this->loggingService->addLogEntry(new EmptyNecessaryFieldRunLog(
                $this->runId,
                DefaultEntities::NEWSLETTER_RECIPIENT,
                $data['subscriber_id'],
                'salesChannel'
            ));
        }

        return $salesChannelMapping;
    }

    private function getStatus(array $data): ?string
    {
        $status = $this->mappingService->getValue(
            $this->connectionId,
            Magento19NewsletterRecipientStatusReader::getMappingName(),
            $data['subscriber_status'],
            $this->context
        );

        if ($status === null) {
            $status = $this->mappingService->getValue(
                $this->connectionId,
                Magento19NewsletterRecipientStatusReader::getMappingName(),
                'default_newsletter_recipient_status',
                $this->context
            );
        }

        if ($status === null) {
            $this->loggingService->addLogEntry(new EmptyNecessaryFieldRunLog(
                $this->runId,
                DefaultEntities::NEWSLETTER_RECIPIENT,
                $data['subscriber_id'],
                'status'
            ));
        }

        return $status;
    }
}
