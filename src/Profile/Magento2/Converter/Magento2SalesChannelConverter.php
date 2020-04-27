<?php declare(strict_types=1);
/*
 * (c) shopware AG <info@shopware.com>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Swag\MigrationMagento\Profile\Magento2\Converter;

use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Util\AccessKeyHelper;
use Shopware\Core\Framework\Context;
use Swag\MigrationMagento\Profile\Magento\Converter\SalesChannelConverter;
use Swag\MigrationMagento\Profile\Magento\DataSelection\DefaultEntities as MagentoDefaultEntities;
use Swag\MigrationMagento\Profile\Magento2\Premapping\Magento2CountryReader;
use Swag\MigrationMagento\Profile\Magento2\Premapping\Magento2CurrencyReader;
use Swag\MigrationMagento\Profile\Magento2\Premapping\Magento2LanguageReader;
use Swag\MigrationMagento\Profile\Magento2\Premapping\Magento2PaymentMethodReader;
use Swag\MigrationMagento\Profile\Magento2\Premapping\Magento2ShippingMethodReader;
use SwagMigrationAssistant\Migration\Converter\ConvertStruct;
use SwagMigrationAssistant\Migration\DataSelection\DefaultEntities;
use SwagMigrationAssistant\Migration\Logging\Log\AssociationRequiredMissingLog;
use SwagMigrationAssistant\Migration\Logging\Log\EmptyNecessaryFieldRunLog;
use SwagMigrationAssistant\Migration\MigrationContextInterface;

abstract class Magento2SalesChannelConverter extends SalesChannelConverter
{
    /**
     * @var string[]
     */
    protected static $requiredDataFieldKeys = [
        'website_id',
        'name',
        'store_group',
    ];

    public function convert(array $data, Context $context, MigrationContextInterface $migrationContext): ConvertStruct
    {
        $fields = $this->checkForEmptyRequiredDataFields($data, self::$requiredDataFieldKeys);
        if (!isset($data['store_group']['root_category_id'])) {
            $fields[] = 'root_category_id';
        }
        if (!empty($fields)) {
            $this->loggingService->addLogEntry(new EmptyNecessaryFieldRunLog(
                $migrationContext->getRunUuid(),
                DefaultEntities::SALES_CHANNEL,
                $data['website_id'],
                implode(',', $fields)
            ));

            return new ConvertStruct(null, $data);
        }

        /*
         * Set main data
         */
        $this->generateChecksum($data);
        $this->originalData = $data;
        $this->context = $context;
        $this->runId = $migrationContext->getRunUuid();
        $this->oldIdentifier = $data['website_id'];

        $connection = $migrationContext->getConnection();
        $this->connectionId = '';
        if ($connection !== null) {
            $this->connectionId = $connection->getId();
        }

        /*
         * Set main mapping
         */
        $this->mainMapping = $this->mappingService->getOrCreateMapping(
            $this->connectionId,
            DefaultEntities::SALES_CHANNEL,
            $this->oldIdentifier,
            $context,
            $this->checksum
        );

        $converted = [];
        $converted['id'] = $this->mainMapping['entityUuid'];
        unset($data['website_id']);

        $this->setStores($data, $converted);

        $mapping = $this->mappingService->getMapping(
            $this->connectionId,
            DefaultEntities::CUSTOMER_GROUP,
            $data['default_group_id'],
            $context
        );
        if ($mapping === null) {
            return new ConvertStruct(null, $this->originalData);
        }
        $converted['customerGroupId'] = $mapping['entityUuid'];
        unset($data['default_group_id']);

        $languageUuid = $this->setLanguageUuid($data, $converted);
        if ($languageUuid === null) {
            return new ConvertStruct(null, $this->originalData);
        }

        $currencyUuid = $this->setCurrencyUuid($data, $converted);
        if ($currencyUuid === null) {
            return new ConvertStruct(null, $this->originalData);
        }

        $categoryUuid = $this->setCategoryUuid($data, $converted);
        if ($categoryUuid === null) {
            return new ConvertStruct(null, $this->originalData);
        }

        $countryUuid = $this->setCountryUuid($data, $converted);
        if ($countryUuid === null) {
            return new ConvertStruct(null, $this->originalData);
        }

        $paymentMethodUuid = $this->setPaymentMethodUuid($data, $converted);
        if ($paymentMethodUuid === null) {
            return new ConvertStruct(null, $this->originalData);
        }

        $shippingMethodUuid = $this->setShippingMethodUuid($data, $converted);
        if ($shippingMethodUuid === null) {
            return new ConvertStruct(null, $this->originalData);
        }

        /*
         * Set translations
         */
        $this->getSalesChannelTranslation($converted, $data);
        unset($data['defaultLocale']);

        $converted['typeId'] = Defaults::SALES_CHANNEL_TYPE_STOREFRONT;
        $converted['accessKey'] = AccessKeyHelper::generateAccessKey('sales-channel');
        $this->convertValue($converted, 'name', $data, 'name');

        $this->updateMainMapping($migrationContext, $context);

        // There is no equivalent field
        unset(
            $data['code'],
            $data['sort_order'],
            $data['default_group_id'],
            $data['is_default'],
            $data['is_staging'],
            $data['master_login'],
            $data['master_password'],
            $data['visibility']
        );

        $resultData = $data;
        if (empty($resultData)) {
            $resultData = null;
        }

        return new ConvertStruct($converted, $resultData, $this->mainMapping['id']);
    }

    protected function setStores(array &$data, array &$converted): void
    {
        if (isset($data['stores'])) {
            foreach ($data['stores'] as $store) {
                $mapping = $this->mappingService->getOrCreateMapping(
                    $this->connectionId,
                    MagentoDefaultEntities::STORE,
                    $store['store_id'],
                    $this->context,
                    null,
                    null,
                    $converted['id']
                );
                $this->mappingIds[] = $mapping['id'];
            }

            $this->mappingService->createListItemMapping(
                $this->connectionId,
                MagentoDefaultEntities::STORE_DEFAULT,
                '0',
                $this->context,
                null,
                $converted['id']
            );
        }
        unset($data['stores']);
    }

    protected function setLanguageUuid(array &$data, array &$converted): ?string
    {
        $languageUuid = null;
        if (!empty($data['defaultLocale'])) {
            $languageUuid = $this->mappingService->getLanguageUuid(
                $this->connectionId,
                $data['defaultLocale'],
                $this->context
            );
        }

        if ($languageUuid === null) {
            $languageMapping = $this->mappingService->getMapping(
                $this->connectionId,
                Magento2LanguageReader::getMappingName(),
                'default_language',
                $this->context
            );

            if ($languageMapping === null) {
                $this->loggingService->addLogEntry(
                    new AssociationRequiredMissingLog(
                        $this->runId,
                        DefaultEntities::LANGUAGE,
                        'default_locale',
                        DefaultEntities::SALES_CHANNEL
                    )
                );

                return null;
            }

            $this->mappingIds[] = $languageMapping['id'];
            $languageUuid = $languageMapping['entityUuid'];
        }

        $this->mappingService->pushValueMapping(
            $this->connectionId,
            DefaultEntities::LOCALE,
            'global_default',
            $data['defaultLocale']
        );

        $this->mappingService->getOrCreateMapping(
            $this->connectionId,
            DefaultEntities::LANGUAGE,
            'global_default',
            $this->context,
            null,
            null,
            $languageUuid
        );

        $converted['languageId'] = $languageUuid;
        $converted['languages'] = $this->getSalesChannelLanguages($languageUuid, $data, $this->context);
        unset($data['locales']);

        return $languageUuid;
    }

    protected function setCurrencyUuid(array &$data, array &$converted): ?string
    {
        $currencyUuid = null;
        if (!empty($data['defaultCurrency'])) {
            $currencyUuid = $this->mappingService->getCurrencyUuid(
                $this->connectionId,
                $data['defaultCurrency'],
                $this->context
            );
        }

        if ($currencyUuid === null) {
            $currencyMapping = $this->mappingService->getMapping(
                $this->connectionId,
                Magento2CurrencyReader::getMappingName(),
                'default_currency',
                $this->context
            );

            if ($currencyMapping === null) {
                $this->loggingService->addLogEntry(
                    new AssociationRequiredMissingLog(
                        $this->runId,
                        DefaultEntities::CURRENCY,
                        'default_currency',
                        DefaultEntities::SALES_CHANNEL
                    )
                );

                return null;
            }

            $this->mappingIds[] = $currencyMapping['id'];
            $currencyUuid = $currencyMapping['entityUuid'];
        }
        $converted['currencyId'] = $currencyUuid;
        $converted['currencies'] = $this->getSalesChannelCurrencies($currencyUuid, $data, $this->context);
        unset($data['currencies'], $data['defaultCurrency']);

        return $currencyUuid;
    }

    protected function setCategoryUuid(array &$data, array &$converted): ?string
    {
        $categoryMapping = $this->mappingService->getMapping(
            $this->connectionId,
            DefaultEntities::CATEGORY,
            $data['store_group']['root_category_id'],
            $this->context
        );

        if ($categoryMapping === null) {
            $this->loggingService->addLogEntry(
                new AssociationRequiredMissingLog(
                    $this->runId,
                    DefaultEntities::CATEGORY,
                    $data['store_group']['root_category_id'],
                    DefaultEntities::SALES_CHANNEL
                )
            );

            return null;
        }
        $categoryUuid = $categoryMapping['entityUuid'];
        $this->mappingIds[] = $categoryMapping['id'];
        $converted['navigationCategoryId'] = $categoryUuid;
        unset($data['store_group']);

        return $categoryUuid;
    }

    protected function setCountryUuid(array &$data, array &$converted): ?string
    {
        $countryUuid = null;
        if (!empty($data['defaultCountry'])) {
            $countryUuid = $this->mappingService->getMagentoCountryUuid(
                $data['defaultCountry'],
                $this->connectionId,
                $this->context
            );
        }

        if ($countryUuid === null) {
            $countryMapping = $this->mappingService->getMapping(
                $this->connectionId,
                Magento2CountryReader::getMappingName(),
                'default_country',
                $this->context
            );

            if ($countryMapping === null) {
                $this->loggingService->addLogEntry(
                    new AssociationRequiredMissingLog(
                        $this->runId,
                        DefaultEntities::COUNTRY,
                        'default_country',
                        DefaultEntities::SALES_CHANNEL
                    )
                );

                return null;
            }

            $this->mappingIds[] = $countryMapping['id'];
            $countryUuid = $countryMapping['entityUuid'];
        }
        $converted['countryId'] = $countryUuid;
        $converted['countries'] = $this->getSalesChannelCountries($countryUuid, $data, $this->context);
        unset($data['countries'], $data['defaultCountry']);

        return $countryUuid;
    }

    protected function setPaymentMethodUuid(array &$data, array &$converted): ?string
    {
        $converted['paymentMethods'] = $this->getPaymentMethods($data, $this->context);
        if (empty($converted['paymentMethods'])) {
            $paymentMethodMapping = $this->mappingService->getMapping(
                $this->connectionId,
                Magento2PaymentMethodReader::getMappingName(),
                'default_payment_method',
                $this->context
            );

            if ($paymentMethodMapping === null) {
                $this->loggingService->addLogEntry(new EmptyNecessaryFieldRunLog(
                    $this->runId,
                    DefaultEntities::SALES_CHANNEL,
                    $this->oldIdentifier,
                    'payment methods'
                ));

                return null;
            }

            $this->mappingIds[] = $paymentMethodMapping['id'];
            $converted['paymentMethods'][0]['id'] = $paymentMethodMapping['entityUuid'];
        }
        $converted['paymentMethodId'] = $converted['paymentMethods'][0]['id'];
        unset($data['payments']);

        return $converted['paymentMethodId'];
    }

    protected function setShippingMethodUuid(array &$data, array &$converted): ?string
    {
        $converted['shippingMethods'] = $this->getShippingMethods($data, $this->context);
        if (empty($converted['shippingMethods'])) {
            $shippingMethodMapping = $this->mappingService->getMapping(
                $this->connectionId,
                Magento2ShippingMethodReader::getMappingName(),
                'default_shipping_method',
                $this->context
            );

            if ($shippingMethodMapping === null) {
                $this->loggingService->addLogEntry(new EmptyNecessaryFieldRunLog(
                    $this->runId,
                    DefaultEntities::SALES_CHANNEL,
                    $this->oldIdentifier,
                    'shipping methods'
                ));

                return null;
            }

            $this->mappingIds[] = $shippingMethodMapping['id'];
            $converted['shippingMethods'][0]['id'] = $shippingMethodMapping['entityUuid'];
        }
        $converted['shippingMethodId'] = $converted['shippingMethods'][0]['id'];
        unset($data['carriers']);

        return $converted['shippingMethodId'];
    }
}
