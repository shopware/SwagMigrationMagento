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
use SwagMigrationAssistant\Migration\Logging\Log\EmptyNecessaryFieldRunLog;
use SwagMigrationAssistant\Migration\MigrationContextInterface;

abstract class SeoUrlConverter extends MagentoConverter
{
    protected const ROUTE_NAME_NAVIGATION = 'frontend.navigation.page';
    protected const ROUTE_NAME_PRODUCT = 'frontend.detail.page';

    /**
     * @var string
     */
    protected $connectionId;

    public function getSourceIdentifier(array $data): string
    {
        return $data['url_rewrite_id'];
    }

    public function convert(array $data, Context $context, MigrationContextInterface $migrationContext): ConvertStruct
    {
        $this->generateChecksum($data);
        $this->originalData = $data;

        $connection = $migrationContext->getConnection();
        $this->connectionId = '';
        if ($connection !== null) {
            $this->connectionId = $connection->getId();
        }

        $converted = [];
        $this->mainMapping = $this->mappingService->getOrCreateMapping(
            $this->connectionId,
            DefaultEntities::SEO_URL,
            $data['url_rewrite_id'],
            $context,
            $this->checksum
        );
        $converted['id'] = $this->mainMapping['entityUuid'];

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
                    DefaultEntities::SEO_URL
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
                    DefaultEntities::SEO_URL
                )
            );

            return new ConvertStruct(null, $this->originalData);
        }
        $converted['languageId'] = $languageMapping['entityUuid'];
        $this->mappingIds[] = $languageMapping['id'];
        unset($data['store_id']);

        if (isset($data['product_id'])) {
            $converted['isModified'] = false;
            if (!isset($data['category_id'])) {
                $converted['isCanonical'] = true;
                $converted['isModified'] = true;
            }

            $mapping = $this->mappingService->getMapping(
                $this->connectionId,
                DefaultEntities::PRODUCT,
                $data['product_id'],
                $context
            );

            if ($mapping === null) {
                $this->loggingService->addLogEntry(
                    new AssociationRequiredMissingLog(
                        $migrationContext->getRunUuid(),
                        DefaultEntities::PRODUCT,
                        $data['product_id'],
                        DefaultEntities::SEO_URL
                    )
                );

                return new ConvertStruct(null, $this->originalData);
            }
            $converted['foreignKey'] = $mapping['entityUuid'];
            $converted['routeName'] = self::ROUTE_NAME_PRODUCT;
            $converted['pathInfo'] = '/detail/' . $mapping['entityUuid'];
            $this->mappingIds[] = $mapping['id'];
        } elseif (isset($data['category_id'])) {
            $mapping = $this->mappingService->getMapping(
                $this->connectionId,
                DefaultEntities::CATEGORY,
                $data['category_id'],
                $context
            );

            if ($mapping === null) {
                $this->loggingService->addLogEntry(
                    new AssociationRequiredMissingLog(
                        $migrationContext->getRunUuid(),
                        DefaultEntities::CATEGORY,
                        $data['category_id'],
                        DefaultEntities::SEO_URL
                    )
                );

                return new ConvertStruct(null, $this->originalData);
            }
            $converted['isCanonical'] = true;
            $converted['isModified'] = true;
            $converted['foreignKey'] = $mapping['entityUuid'];
            $converted['routeName'] = self::ROUTE_NAME_NAVIGATION;
            $converted['pathInfo'] = '/navigation/' . $mapping['entityUuid'];
            $this->mappingIds[] = $mapping['id'];
        } else {
            $this->loggingService->addLogEntry(
                new EmptyNecessaryFieldRunLog(
                    $migrationContext->getRunUuid(),
                    DefaultEntities::SEO_URL,
                    $this->originalData['url_rewrite_id'],
                    'category_id, product_id'
                )
            );

            return new ConvertStruct(null, $this->originalData);
        }

        $isCanonical = (isset($converted['isCanonical'])) ? 'canonical' : 'not_canonical';
        $hash = \hash('sha256', $converted['languageId'] . '_' . $converted['salesChannelId'] . '_' . $converted['foreignKey'] . '_' . $converted['routeName'] . '_' . $isCanonical);
        $uniqueUrlMapping = $this->mappingService->getMapping(
            $this->connectionId,
            DefaultEntities::SEO_URL,
            $hash,
            $context
        );

        if ($uniqueUrlMapping === null) {
            $this->mappingService->getOrCreateMapping(
                $this->connectionId,
                DefaultEntities::SEO_URL,
                $hash,
                $context,
                null,
                null,
                $converted['id']
            );
        } else {
            if ($uniqueUrlMapping['entityUuid'] !== $converted['id']) {
                return new ConvertStruct(null, $this->originalData);
            }
        }

        $this->convertValue($converted, 'seoPathInfo', $data, 'request_path');

        $this->updateMainMapping($migrationContext, $context);

        unset(
            $data['url_rewrite_id'],
            $data['entity_type'],
            $data['entity_id'],
            $data['request_path'],
            $data['target_path'],
            $data['redirect_type'],
            $data['store_id'],
            $data['description'],
            $data['is_autogenerated'],
            $data['metadata'],
            $data['locale'],
            $data['product_id'],
            $data['category_id']
        );

        $resultData = $data;
        if (empty($resultData)) {
            $resultData = null;
        }

        return new ConvertStruct($converted, $resultData, $this->mainMapping['id']);
    }
}
