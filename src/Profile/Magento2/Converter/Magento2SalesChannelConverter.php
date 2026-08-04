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
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelDefinition;
use Swag\MigrationMagento\Migration\Mapping\MagentoMappingServiceInterface;
use Swag\MigrationMagento\Profile\Magento\Converter\SalesChannelConverter;
use Swag\MigrationMagento\Profile\Magento\DataSelection\DefaultEntities as MagentoDefaultEntities;
use Swag\MigrationMagento\Profile\Magento\Premapping\PaymentMethodReader;
use Swag\MigrationMagento\Profile\Magento\Premapping\ShippingMethodReader;
use Swag\MigrationMagento\Profile\Magento2\Premapping\Magento2CountryReader;
use Swag\MigrationMagento\Profile\Magento2\Premapping\Magento2CurrencyReader;
use Swag\MigrationMagento\Profile\Magento2\Premapping\Magento2LanguageReader;
use SwagMigrationAssistant\Migration\Converter\ConvertStruct;
use SwagMigrationAssistant\Migration\DataSelection\DefaultEntities;
use SwagMigrationAssistant\Migration\Logging\Log\Builder\MigrationLogBuilder;
use SwagMigrationAssistant\Migration\Logging\Log\ConvertSourceDataIncompleteLog;
use SwagMigrationAssistant\Migration\MigrationContextInterface;

#[Package('fundamentals@after-sales')]
abstract class Magento2SalesChannelConverter extends SalesChannelConverter
{
    public function convert(array $data, Context $context, MigrationContextInterface $migrationContext): ConvertStruct
    {
        if (!isset($data['group_id']) || $data['group_id'] === '') {
            $this->loggingService->log(
                MigrationLogBuilder::fromMigrationContext($migrationContext)
                    ->withEntityName(SalesChannelDefinition::ENTITY_NAME)
                    ->withFieldName('group_id')
                    ->withSourceData($data)
                    ->build(ConvertSourceDataIncompleteLog::class)
            );

            return new ConvertStruct(null, $data);
        }

        /*
         * Set main data
         */
        $this->generateChecksum($data);
        $this->originalData = $data;
        $this->context = $context;
        $this->runId = $migrationContext->getRunUuid();
        $this->oldIdentifier = $data['group_id'];

        $connection = $migrationContext->getConnection();
        $converted = [];
        $this->connectionId = $connection->getId();

        $defaultCustomerGroupId = $this->mappingService->getValue(
            $this->connectionId,
            DefaultEntities::CUSTOMER_GROUP,
            'default_customer_group',
            $context
        );

        if ($defaultCustomerGroupId !== null) {
            $mapping = $this->mappingService->getMapping(
                $this->connectionId,
                DefaultEntities::CUSTOMER_GROUP,
                $defaultCustomerGroupId,
                $context
            );
            if ($mapping !== null) {
                $converted['customerGroupId'] = $mapping['entityId'];
            }
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

        $converted['id'] = $this->mainMapping['entityId'];
        unset($data['group_id']);

        $this->setStores($data, $converted);

        $this->setLanguageUuid($data, $converted, $context);
        $this->setCurrencyUuid($data, $converted);
        $this->setCategoryUuid($data, $converted);
        $this->setCountryUuid($data, $converted);
        $this->setPaymentMethodUuid($data, $converted);
        $this->setShippingMethodUuid($data, $converted);

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
            $data['website_id'],
            $data['default_store_id']
        );

        $resultData = null;

        if ($data !== []) {
            $resultData = $data;
        }

        return new ConvertStruct($converted, $resultData, $this->mainMapping['id'] ?? null);
    }

    protected function setStores(array &$data, array &$converted): void
    {
        if (isset($data['storeViews'])) {
            foreach ($data['storeViews'] as $storeView) {
                $mapping = $this->mappingService->getOrCreateMapping(
                    $this->connectionId,
                    MagentoDefaultEntities::STORE,
                    $storeView['store_id'],
                    $this->context,
                    null,
                    null,
                    $converted['id']
                );
                $this->mappingIds[] = $mapping['id'];
            }

            \assert($this->mappingService instanceof MagentoMappingServiceInterface);
            $this->mappingService->createListItemMapping(
                $this->connectionId,
                MagentoDefaultEntities::STORE_DEFAULT,
                '0',
                $this->context,
                null,
                $converted['id']
            );
        }
        unset($data['storeViews']);
    }

    protected function setLanguageUuid(array &$data, array &$converted, Context $context): ?string
    {
        $languageMapping = $this->mappingService->getMapping(
            $this->connectionId,
            Magento2LanguageReader::getMappingName(),
            $data['defaultLocale'],
            $this->context
        );

        if ($languageMapping !== null) {
            $languageUuid = $languageMapping['entityId'];
            $this->mappingIds[] = $languageMapping['id'];
        } else {
            $languageUuid = null;

            if (isset($data['defaultLocale']) && $data['defaultLocale'] !== '') {
                $languageUuid = $this->languageLookup->get($data['defaultLocale'], $this->context);
            }

            if ($languageUuid === null) {
                $languageMapping = $this->mappingService->getMapping(
                    $this->connectionId,
                    Magento2LanguageReader::getMappingName(),
                    'default_language',
                    $this->context
                );

                if ($languageMapping === null || !isset($languageMapping['entityId'])) {
                    return null;
                }

                $this->mappingIds[] = $languageMapping['id'];
                $languageUuid = $languageMapping['entityId'];
            }
        }

        if ($languageUuid === null) {
            return null;
        }

        $this->mappingService->getOrCreateMapping(
            $this->connectionId,
            DefaultEntities::LOCALE,
            'global_default',
            $context,
            null,
            null,
            null,
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

        if (isset($data['defaultCurrency']) && $data['defaultCurrency'] !== '') {
            $currencyUuid = $this->currencyLookup->get($data['defaultCurrency'], $this->context);
        }

        if ($currencyUuid === null) {
            $currencyMapping = $this->mappingService->getMapping(
                $this->connectionId,
                Magento2CurrencyReader::getMappingName(),
                'default_currency',
                $this->context
            );

            if ($currencyMapping === null || !isset($currencyMapping['entityId'])) {
                return null;
            }

            $this->mappingIds[] = $currencyMapping['id'];
            $currencyUuid = $currencyMapping['entityId'];
        }

        $converted['currencyId'] = $currencyUuid;
        $converted['currencies'] = $this->getSalesChannelCurrencies($currencyUuid, $data, $this->context);
        unset($data['currencies'], $data['defaultCurrency']);

        return $currencyUuid;
    }

    protected function setCategoryUuid(array &$data, array &$converted): ?string
    {
        if (!isset($data['root_category_id'])) {
            return null;
        }

        $categoryMapping = $this->mappingService->getMapping(
            $this->connectionId,
            DefaultEntities::CATEGORY,
            $data['root_category_id'],
            $this->context
        );

        if ($categoryMapping === null) {
            return null;
        }

        $categoryUuid = $categoryMapping['entityId'];
        $this->mappingIds[] = $categoryMapping['id'];
        $converted['navigationCategoryId'] = $categoryUuid;
        unset($data['root_category_id']);

        return $categoryUuid;
    }

    protected function setCountryUuid(array &$data, array &$converted): ?string
    {
        $countryUuid = null;

        if (isset($data['defaultCountry']) && $data['defaultCountry'] !== '') {
            $countryMapping = $this->mappingService->getMapping($this->connectionId, DefaultEntities::COUNTRY, $data['defaultCountry'], $this->context);

            if ($countryMapping !== null) {
                $countryUuid = $countryMapping['entityId'];
            } else {
                $countryUuid = $this->countryLookup->getByIso2($data['defaultCountry'], $this->context);

                if ($countryUuid !== null) {
                    $this->mappingService->createMapping(
                        $this->connectionId,
                        DefaultEntities::COUNTRY,
                        $data['isoCode'],
                        $this->checksum,
                    );
                }
            }
        }

        if ($countryUuid === null) {
            $countryMapping = $this->mappingService->getMapping(
                $this->connectionId,
                Magento2CountryReader::getMappingName(),
                'default_country',
                $this->context
            );

            if ($countryMapping === null) {
                return null;
            }

            $this->mappingIds[] = $countryMapping['id'];
            $countryUuid = $countryMapping['entityId'];
        }

        $converted['countryId'] = $countryUuid;
        $converted['countries'] = $this->getSalesChannelCountries((string) $countryUuid, $data, $this->context);
        unset($data['countries'], $data['defaultCountry']);

        return $countryUuid;
    }

    protected function setPaymentMethodUuid(array &$data, array &$converted): ?string
    {
        $converted['paymentMethods'] = $this->getPaymentMethods($data, $this->context);

        if ($converted['paymentMethods'] === []) {
            $paymentMethodMapping = $this->mappingService->getMapping(
                $this->connectionId,
                PaymentMethodReader::getMappingName(),
                'default_payment_method',
                $this->context
            );

            if ($paymentMethodMapping === null) {
                return null;
            }

            $this->mappingIds[] = $paymentMethodMapping['id'];
            $converted['paymentMethods'][0]['id'] = $paymentMethodMapping['entityId'];
        }

        $converted['paymentMethodId'] = $converted['paymentMethods'][0]['id'];
        unset($data['payments']);

        return $converted['paymentMethodId'];
    }

    protected function setShippingMethodUuid(array &$data, array &$converted): ?string
    {
        $converted['shippingMethods'] = $this->getShippingMethods($data, $this->context);

        if ($converted['shippingMethods'] === []) {
            $shippingMethodMapping = $this->mappingService->getMapping(
                $this->connectionId,
                ShippingMethodReader::getMappingName(),
                'default_shipping_method',
                $this->context
            );

            if ($shippingMethodMapping === null) {
                return null;
            }

            $this->mappingIds[] = $shippingMethodMapping['id'];
            $converted['shippingMethods'][0]['id'] = $shippingMethodMapping['entityId'];
        }

        $converted['shippingMethodId'] = $converted['shippingMethods'][0]['id'];
        unset($data['carriers']);

        return $converted['shippingMethodId'];
    }
}
