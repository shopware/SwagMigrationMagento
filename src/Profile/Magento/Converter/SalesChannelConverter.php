<?php declare(strict_types=1);
/*
 * (c) shopware AG <info@shopware.com>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Swag\MigrationMagento\Profile\Magento\Converter;

use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Util\AccessKeyHelper;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelDefinition;
use Swag\MigrationMagento\Migration\Mapping\MagentoMappingServiceInterface;
use Swag\MigrationMagento\Profile\Magento\DataSelection\DefaultEntities as MagentoDefaultEntities;
use Swag\MigrationMagento\Profile\Magento\Premapping\PaymentMethodReader;
use Swag\MigrationMagento\Profile\Magento\Premapping\ShippingMethodReader;
use SwagMigrationAssistant\Migration\Converter\ConvertStruct;
use SwagMigrationAssistant\Migration\DataSelection\DefaultEntities;
use SwagMigrationAssistant\Migration\Logging\Log\Builder\MigrationLogBuilder;
use SwagMigrationAssistant\Migration\Logging\Log\ConvertFieldReassignedLog;
use SwagMigrationAssistant\Migration\Logging\Log\ConvertSourceDataIncompleteLog;
use SwagMigrationAssistant\Migration\Logging\LoggingServiceInterface;
use SwagMigrationAssistant\Migration\Mapping\Lookup\CountryLookup;
use SwagMigrationAssistant\Migration\Mapping\Lookup\CurrencyLookup;
use SwagMigrationAssistant\Migration\Mapping\Lookup\LanguageLookup;
use SwagMigrationAssistant\Migration\MigrationContextInterface;

#[Package('fundamentals@after-sales')]
abstract class SalesChannelConverter extends MagentoConverter
{
    protected string $connectionId;

    protected Context $context;

    protected string $runId;

    protected string $oldIdentifier;

    /**
     * @internal
     */
    public function __construct(
        MagentoMappingServiceInterface $mappingService,
        LoggingServiceInterface $loggingService,
        protected readonly CurrencyLookup $currencyLookup,
        protected readonly LanguageLookup $languageLookup,
        protected readonly CountryLookup $countryLookup,
    ) {
        parent::__construct($mappingService, $loggingService);
    }

    public function getSourceIdentifier(array $data): string
    {
        return $data['group_id'];
    }

    public function convert(array $data, Context $context, MigrationContextInterface $migrationContext): ConvertStruct
    {
        if (empty($data['group_id'])) {
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
        $converted = [];
        $connection = $migrationContext->getConnection();
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

        /*
         * Create the store mappings
         */
        if (isset($data['storeViews'])) {
            foreach ($data['storeViews'] as $storeView) {
                $mapping = $this->mappingService->getOrCreateMapping(
                    $this->connectionId,
                    MagentoDefaultEntities::STORE,
                    $storeView['store_id'],
                    $context,
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
        unset($data['storeViews']);

        /*
         * Set main language and allowed languages
         */
        $languageUuid = null;
        if (isset($data['defaultLocale'])) {
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

            $languageUuid = $this->languageLookup->get($data['defaultLocale'], $context);
        }

        if ($languageUuid === null) {
            $defaultLanguage = $this->languageLookup->getLanguageEntity($context);

            $this->loggingService->log(
                MigrationLogBuilder::fromMigrationContext($migrationContext)
                    ->withEntityName(SalesChannelDefinition::ENTITY_NAME)
                    ->withFieldName('languageId')
                    ->withFieldSourcePath('defaultLocale')
                    ->withSourceData($data)
                    ->withConvertedData($converted)
                    ->build(ConvertSourceDataIncompleteLog::class)
            );

            $languageUuid = $defaultLanguage->getId();
        }

        if ($languageUuid !== null) {
            $this->mappingService->getOrCreateMapping(
                $this->connectionId,
                DefaultEntities::LANGUAGE,
                'global_default',
                $this->context,
                null,
                null,
                $languageUuid
            );
        }

        $converted['languageId'] = $languageUuid;
        $converted['languages'] = $this->getSalesChannelLanguages($languageUuid, $data, $context);
        unset($data['locales']);

        /*
         * Set main currency and allowed currencies
         */
        $currencyUuid = null;
        if (isset($data['defaultCurrency'])) {
            $currencyUuid = $this->currencyLookup->get($data['defaultCurrency'], $context);
        }

        if ($currencyUuid === null) {
            $this->loggingService->log(
                MigrationLogBuilder::fromMigrationContext($migrationContext)
                    ->withEntityName(SalesChannelDefinition::ENTITY_NAME)
                    ->withFieldName('currencyId')
                    ->withFieldSourcePath('defaultCurrency')
                    ->withSourceData($data)
                    ->withConvertedData($converted)
                    ->build(ConvertFieldReassignedLog::class)
            );

            $currencyUuid = Defaults::CURRENCY;
        }

        $converted['currencyId'] = $currencyUuid;
        $converted['currencies'] = $this->getSalesChannelCurrencies($currencyUuid, $data, $context);
        unset($data['currencies'], $data['defaultCurrency']);

        /*
         * Set navigation category
         */
        $mainCategory = $data['root_category_id'] ?? null;
        $categoryMapping = null;

        if ($mainCategory !== null) {
            $categoryMapping = $this->mappingService->getMapping(
                $this->connectionId,
                DefaultEntities::CATEGORY,
                $mainCategory,
                $context
            );
        }

        if ($categoryMapping !== null) {
            $categoryUuid = $categoryMapping['entityId'];
            $this->mappingIds[] = $categoryMapping['id'];
            $converted['navigationCategoryId'] = $categoryUuid;
            unset($data['root_category_id']);
        }

        /*
         * Set main country and allowed countries
         */
        $countryUuid = null;
        if (isset($data['defaultCountry'])) {
            $countryUuid = $this->getCountryUuid($data['defaultCountry'], $context);
        }


        if ($countryUuid !== null) {
            $converted['countryId'] = $countryUuid;
            $converted['countries'] = $this->getSalesChannelCountries($countryUuid, $data, $context);
            unset($data['countries'], $data['defaultCountry']);
        }

        /*
         * Set main payment method and allowed payment methods
         */
        $converted['paymentMethods'] = $this->getPaymentMethods($data, $context);

        if (empty($converted['paymentMethods'])) {
            $defaultPaymentMethod = $this->mappingService->getMapping(
                $this->connectionId,
                PaymentMethodReader::getMappingName(),
                'default_payment_method',
                $this->context
            );

            if (!empty($defaultPaymentMethod)) {
                $this->mappingIds[] = $defaultPaymentMethod['id'];
                $converted['paymentMethods'][0]['id'] = $defaultPaymentMethod['entityId'];
            }
        }

        if (!empty($converted['paymentMethods'])) {
            $converted['paymentMethodId'] = $converted['paymentMethods'][0]['id'];
            unset($data['payments']);
        }

        /*
         * Set main shipping method and allowed shipping methods
         */
        $converted['shippingMethods'] = $this->getShippingMethods($data, $context);

        if (!empty($converted['shippingMethods'])) {
            $converted['shippingMethodId'] = $converted['shippingMethods'][0]['id'];
            unset($data['carriers']);
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

        unset(
            $data['website_id'],
            $data['default_store_id']
        );

        $resultData = $data;
        if (empty($resultData)) {
            $resultData = null;
        }

        return new ConvertStruct($converted, $resultData, $this->mainMapping['id'] ?? null);
    }

    protected function getSalesChannelLanguages(string $languageUuid, array $data, Context $context): array
    {
        $languages = [];
        $languages[$languageUuid] = [
            'id' => $languageUuid,
        ];

        if (isset($data['locales'])) {
            foreach ($data['locales'] as $locale) {
                $uuid = $this->languageLookup->get($locale, $context);
                if ($uuid === null) {
                    continue;
                }

                $languages[$uuid] = [
                    'id' => $uuid,
                ];
            }
        }

        return \array_values($languages);
    }

    protected function getSalesChannelCurrencies(string $currencyUuid, array $data, Context $context): array
    {
        $currencies = [];
        $currencies[$currencyUuid] = [
            'id' => $currencyUuid,
        ];

        if (isset($data['currencies'])) {
            foreach ($data['currencies'] as $currency) {
                $uuid = $this->currencyLookup->get($currency, $context);
                if ($uuid === null) {
                    continue;
                }

                $currencies[$uuid] = [
                    'id' => $uuid,
                ];
            }
        }

        return \array_values($currencies);
    }

    protected function getSalesChannelCountries(string $countryUuid, array $data, Context $context): array
    {
        $countries = [];
        $countries[$countryUuid] = [
            'id' => $countryUuid,
        ];

        if (isset($data['countries'])) {
            foreach ($data['countries'] as $country) {
                $uuid = $this->getCountryUuid($country, $context);

                if ($uuid === null) {
                    continue;
                }

                $countries[$uuid] = [
                    'id' => $uuid,
                ];
            }
        }

        return \array_values($countries);
    }

    protected function getPaymentMethods(array $data, Context $context): array
    {
        $payments = [];

        if (isset($data['payments'])) {
            foreach ($data['payments'] as $payment) {
                $mapping = $this->mappingService->getMapping(
                    $this->connectionId,
                    PaymentMethodReader::getMappingName(),
                    $payment['payment_id'],
                    $context
                );

                if ($mapping !== null) {
                    $uuid = $mapping['entityId'];
                    $payments[$uuid] = [
                        'id' => $uuid,
                    ];
                }
            }
        }

        if ($payments === []) {
            $mapping = $this->mappingService->getMapping(
                $this->connectionId,
                PaymentMethodReader::getMappingName(),
                'default_payment_method',
                $context
            );

            if (isset($mapping['entityId'])) {
                $uuid = $mapping['entityId'];
                $payments[$uuid] = [
                    'id' => $uuid,
                ];
            }
        }

        return \array_values($payments);
    }

    protected function getShippingMethods(array $data, Context $context): array
    {
        $carriers = [];

        if (isset($data['carriers'])) {
            foreach ($data['carriers'] as $payment) {
                $mapping = $this->mappingService->getMapping(
                    $this->connectionId,
                    ShippingMethodReader::getMappingName(),
                    $payment['carrier_id'],
                    $context
                );

                if ($mapping !== null) {
                    $uuid = $mapping['entityId'];
                    $carriers[$uuid] = [
                        'id' => $uuid,
                    ];
                }
            }
        }

        if ($carriers === []) {
            $mapping = $this->mappingService->getMapping(
                $this->connectionId,
                ShippingMethodReader::getMappingName(),
                'default_shipping_method',
                $context
            );

            if (isset($mapping['entityId'])) {
                $uuid = $mapping['entityId'];
                $carriers[$uuid] = [
                    'id' => $uuid,
                ];
            }
        }

        return \array_values($carriers);
    }

    protected function getSalesChannelTranslation(array &$salesChannel, array $data): void
    {
        $language = $this->languageLookup->getLanguageEntity($this->context);
        if ($language === null) {
            return;
        }

        $locale = $language->getLocale();
        if ($locale === null) {
            return;
        }

        if (!isset($data['defaultLocale']) || $data['defaultLocale'] === $locale->getCode()) {
            return;
        }

        $localeTranslation = [];

        $this->convertValue($localeTranslation, 'name', $data, 'name');

        $mapping = $this->mappingService->getOrCreateMapping(
            $this->connectionId,
            DefaultEntities::SALES_CHANNEL_TRANSLATION,
            $this->oldIdentifier . ':' . $data['defaultLocale'],
            $this->context
        );
        $localeTranslation['id'] = $mapping['entityId'];
        $this->mappingIds[] = $mapping['id'];

        $languageUuid = $this->languageLookup->get($data['defaultLocale'], $this->context);
        if ($languageUuid !== null) {
            $localeTranslation['languageId'] = $languageUuid;
            $salesChannel['translations'][$languageUuid] = $localeTranslation;
        }
    }

    private function getCountryUuid(string $iso, Context $context): ?string
    {
        $countryMapping = $this->mappingService->getMapping($this->connectionId, DefaultEntities::COUNTRY, $iso, $context);
        if ($countryMapping !== null) {
            $countryUuid = $countryMapping['entityId'];
        } else {
            $countryUuid = $this->countryLookup->getByIso2($iso, $context);

            if ($countryUuid !== null) {
                $this->mappingService->createMapping(
                    $this->connectionId,
                    DefaultEntities::COUNTRY,
                    $iso,
                    $this->checksum,
                );
            }
        }

        return $countryUuid;
    }
}
