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
use Swag\MigrationMagento\Migration\Mapping\MagentoMappingServiceInterface;
use Swag\MigrationMagento\Profile\Magento\DataSelection\DefaultEntities as MagentoDefaultEntities;
use Swag\MigrationMagento\Profile\Magento\Premapping\PaymentMethodReader;
use Swag\MigrationMagento\Profile\Magento\Premapping\ShippingMethodReader;
use SwagMigrationAssistant\Migration\Converter\ConvertStruct;
use SwagMigrationAssistant\Migration\DataSelection\DefaultEntities;
use SwagMigrationAssistant\Migration\Logging\Log\AssociationRequiredMissingLog;
use SwagMigrationAssistant\Migration\Logging\Log\EmptyNecessaryFieldRunLog;
use SwagMigrationAssistant\Migration\Logging\Log\FieldReassignedRunLog;
use SwagMigrationAssistant\Migration\Logging\LoggingServiceInterface;
use SwagMigrationAssistant\Migration\MigrationContextInterface;

abstract class SalesChannelConverter extends MagentoConverter
{
    /**
     * @var string
     */
    protected $connectionId;

    /**
     * @var Context
     */
    protected $context;

    /**
     * @var MagentoMappingServiceInterface
     */
    protected $mappingService;

    /**
     * @var string[]
     */
    protected static $requiredDataFieldKeys = [
        'website_id',
        'name',
        'carriers',
        'payments',
        'defaultCurrency',
        'defaultCountry',
        'defaultLocale',
        'group_id',
        'root_category_id',
    ];

    /**
     * @var string
     */
    protected $runId;

    /**
     * @var string
     */
    protected $oldIdentifier;

    public function __construct(MagentoMappingServiceInterface $mappingService, LoggingServiceInterface $loggingService)
    {
        parent::__construct($mappingService, $loggingService);
    }

    public function getSourceIdentifier(array $data): string
    {
        return $data['group_id'];
    }

    public function convert(array $data, Context $context, MigrationContextInterface $migrationContext): ConvertStruct
    {
        $fields = $this->checkForEmptyRequiredDataFields($data, self::$requiredDataFieldKeys);
        if (!empty($fields)) {
            $this->loggingService->addLogEntry(new EmptyNecessaryFieldRunLog(
                $migrationContext->getRunUuid(),
                DefaultEntities::SALES_CHANNEL,
                $data['group_id'],
                \implode(',', $fields)
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
        $this->oldIdentifier = $data['group_id'];
        $converted = [];
        $connection = $migrationContext->getConnection();
        $this->connectionId = '';
        if ($connection !== null) {
            $this->connectionId = $connection->getId();
        }

        $defaultCustomerGroupId = $this->mappingService->getValue(
            $this->connectionId,
            DefaultEntities::CUSTOMER_GROUP,
            'default_customer_group',
            $context
        );
        $converted['customerGroupId'] = Defaults::FALLBACK_CUSTOMER_GROUP;
        if ($defaultCustomerGroupId !== null) {
            $mapping = $this->mappingService->getMapping(
                $this->connectionId,
                DefaultEntities::CUSTOMER_GROUP,
                $defaultCustomerGroupId,
                $context
            );
            if ($mapping !== null) {
                $converted['customerGroupId'] = $mapping['entityUuid'];
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

        $converted['id'] = $this->mainMapping['entityUuid'];
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
        $languageUuid = $this->mappingService->getLanguageUuid(
            $this->connectionId,
            $data['defaultLocale'],
            $context
        );

        if ($languageUuid === null) {
            $defaultLanguage = $this->mappingService->getDefaultLanguage($context);

            if ($defaultLanguage === null) {
                $this->loggingService->addLogEntry(
                    new AssociationRequiredMissingLog(
                        $this->runId,
                        DefaultEntities::LANGUAGE,
                        $data['defaultLocale'],
                        DefaultEntities::SALES_CHANNEL
                    )
                );

                return new ConvertStruct(null, $this->originalData);
            }

            $this->loggingService->addLogEntry(
                new FieldReassignedRunLog(
                    $this->runId,
                    DefaultEntities::SALES_CHANNEL,
                    $this->oldIdentifier,
                    'defaultLocale',
                    'system default language'
                )
            );

            $languageUuid = $defaultLanguage->getId();
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
        $converted['languages'] = $this->getSalesChannelLanguages($languageUuid, $data, $context);
        unset($data['locales']);

        /*
         * Set main currency and allowed currencies
         */
        $currencyUuid = $this->mappingService->getCurrencyUuid(
            $this->connectionId,
            $data['defaultCurrency'],
            $context
        );

        if ($currencyUuid === null) {
            $this->loggingService->addLogEntry(
                new FieldReassignedRunLog(
                    $this->runId,
                    DefaultEntities::SALES_CHANNEL,
                    $this->oldIdentifier,
                    'defaultCurrency',
                    'system default currency'
                )
            );

            $currencyUuid = Defaults::CURRENCY;
        }
        $converted['currencyId'] = $currencyUuid;
        $converted['currencies'] = $this->getSalesChannelCurrencies($currencyUuid, $data, $context);
        unset($data['currencies'], $data['defaultCurrency']);

        /*
         * Set navigation category
         */
        $mainCategory = $data['root_category_id'];
        $categoryMapping = null;
        if ($mainCategory !== null) {
            $categoryMapping = $this->mappingService->getMapping(
                $this->connectionId,
                DefaultEntities::CATEGORY,
                $mainCategory,
                $context
            );
        }

        if ($categoryMapping === null) {
            $this->loggingService->addLogEntry(
                new AssociationRequiredMissingLog(
                    $this->runId,
                    DefaultEntities::CATEGORY,
                    $mainCategory ?? '',
                    DefaultEntities::SALES_CHANNEL
                )
            );

            return new ConvertStruct(null, $this->originalData);
        }
        $categoryUuid = $categoryMapping['entityUuid'];
        $this->mappingIds[] = $categoryMapping['id'];
        $converted['navigationCategoryId'] = $categoryUuid;
        unset($data['root_category_id']);

        /*
         * Set main country and allowed countries
         */
        $countryUuid = $this->mappingService->getMagentoCountryUuid(
            $data['defaultCountry'],
            $this->connectionId,
            $context
        );

        if ($countryUuid === null) {
            $this->loggingService->addLogEntry(
                new AssociationRequiredMissingLog(
                    $this->runId,
                    DefaultEntities::COUNTRY,
                    $data['defaultCountry'],
                    DefaultEntities::SALES_CHANNEL
                )
            );

            return new ConvertStruct(null, $this->originalData);
        }
        $converted['countryId'] = $countryUuid;
        $converted['countries'] = $this->getSalesChannelCountries($countryUuid, $data, $context);
        unset($data['countries'], $data['defaultCountry']);

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

            if (empty($defaultPaymentMethod)) {
                $this->loggingService->addLogEntry(new EmptyNecessaryFieldRunLog(
                    $this->runId,
                    DefaultEntities::SALES_CHANNEL,
                    $this->oldIdentifier,
                    'payment methods'
                ));

                return new ConvertStruct(null, $this->originalData);
            }
            $this->mappingIds[] = $defaultPaymentMethod['id'];
            $converted['paymentMethods'][0]['id'] = $defaultPaymentMethod['entityUuid'];
        }
        $converted['paymentMethodId'] = $converted['paymentMethods'][0]['id'];
        unset($data['payments']);

        /*
         * Set main shipping method and allowed shipping methods
         */
        $converted['shippingMethods'] = $this->getShippingMethods($data, $context);
        if (empty($converted['shippingMethods'])) {
            $this->loggingService->addLogEntry(new EmptyNecessaryFieldRunLog(
                $this->runId,
                DefaultEntities::SALES_CHANNEL,
                $this->oldIdentifier,
                'shipping methods'
            ));

            return new ConvertStruct(null, $this->originalData);
        }
        $converted['shippingMethodId'] = $converted['shippingMethods'][0]['id'];
        unset($data['carriers']);

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

        return new ConvertStruct($converted, $resultData, $this->mainMapping['id']);
    }

    protected function getSalesChannelLanguages(string $languageUuid, array $data, Context $context): array
    {
        $languages = [];
        $languages[$languageUuid] = [
            'id' => $languageUuid,
        ];

        if (isset($data['locales'])) {
            foreach ($data['locales'] as $locale) {
                $uuid = $this->mappingService->getLanguageUuid(
                    $this->connectionId,
                    $locale,
                    $context
                );

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
                $uuid = $this->mappingService->getCurrencyUuid(
                    $this->connectionId,
                    $currency,
                    $context
                );

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
                $uuid = $this->mappingService->getMagentoCountryUuid(
                    $country,
                    $this->connectionId,
                    $context
                );

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

                if ($mapping === null) {
                    $this->loggingService->addLogEntry(new AssociationRequiredMissingLog(
                        $this->runId,
                        PaymentMethodReader::getMappingName(),
                        $payment['payment_id'],
                        DefaultEntities::SALES_CHANNEL
                    ));

                    continue;
                }
                $uuid = $mapping['entityUuid'];
                $payments[$uuid] = [
                    'id' => $uuid,
                ];
            }
        }

        if ($payments === []) {
            $mapping = $this->mappingService->getMapping(
                $this->connectionId,
                PaymentMethodReader::getMappingName(),
                'default_payment_method',
                $context
            );

            if (isset($mapping['entityUuid'])) {
                $uuid = $mapping['entityUuid'];
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

                if ($mapping === null) {
                    $this->loggingService->addLogEntry(new AssociationRequiredMissingLog(
                        $this->runId,
                        ShippingMethodReader::getMappingName(),
                        $payment['carrier_id'],
                        DefaultEntities::SALES_CHANNEL
                    ));

                    continue;
                }
                $uuid = $mapping['entityUuid'];
                $carriers[$uuid] = [
                    'id' => $uuid,
                ];
            }
        }

        if ($carriers === []) {
            $mapping = $this->mappingService->getMapping(
                $this->connectionId,
                ShippingMethodReader::getMappingName(),
                'default_shipping_method',
                $context
            );

            if (isset($mapping['entityUuid'])) {
                $uuid = $mapping['entityUuid'];
                $carriers[$uuid] = [
                    'id' => $uuid,
                ];
            }
        }

        return \array_values($carriers);
    }

    protected function getSalesChannelTranslation(array &$salesChannel, array $data): void
    {
        $language = $this->mappingService->getDefaultLanguage($this->context);
        if ($language === null) {
            return;
        }

        $locale = $language->getLocale();
        if ($locale === null) {
            return;
        }

        if ($locale->getCode() === $data['defaultLocale']) {
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
        $localeTranslation['id'] = $mapping['entityUuid'];
        $this->mappingIds[] = $mapping['id'];
        $languageUuid = $this->mappingService->getLanguageUuid($this->connectionId, $data['defaultLocale'], $this->context);

        if ($languageUuid !== null) {
            $localeTranslation['languageId'] = $languageUuid;
            $salesChannel['translations'][$languageUuid] = $localeTranslation;
        }
    }
}
