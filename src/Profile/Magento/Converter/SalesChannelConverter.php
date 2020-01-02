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
use Swag\MigrationMagento\Profile\Magento\DataSelection\DataSet\SalesChannelDataSet;
use Swag\MigrationMagento\Profile\Magento\DataSelection\DefaultEntities as MagentoDefaultEntities;
use Swag\MigrationMagento\Profile\Magento\Magento19Profile;
use Swag\MigrationMagento\Profile\Magento\Premapping\PaymentMethodReader;
use Swag\MigrationMagento\Profile\Magento\Premapping\ShippingMethodReader;
use SwagMigrationAssistant\Migration\Converter\ConvertStruct;
use SwagMigrationAssistant\Migration\DataSelection\DefaultEntities;
use SwagMigrationAssistant\Migration\Logging\Log\AssociationRequiredMissingLog;
use SwagMigrationAssistant\Migration\Logging\Log\EmptyNecessaryFieldRunLog;
use SwagMigrationAssistant\Migration\Logging\LoggingServiceInterface;
use SwagMigrationAssistant\Migration\MigrationContextInterface;

class SalesChannelConverter extends MagentoConverter
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
        'store_group',
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

    public function supports(MigrationContextInterface $migrationContext): bool
    {
        return $migrationContext->getProfile()->getName() === Magento19Profile::PROFILE_NAME
            && $migrationContext->getDataSet()::getEntity() === SalesChannelDataSet::getEntity();
    }

    public function getSourceIdentifier(array $data): string
    {
        return $data['website_id'];
    }

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
        $this->connectionId = $migrationContext->getConnection()->getId();
        $this->runId = $migrationContext->getRunUuid();
        $this->oldIdentifier = $data['website_id'];

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
        unset($data['website_id']);

        /*
         * Create the store mappings
         */
        if (isset($data['stores'])) {
            foreach ($data['stores'] as $store) {
                $mapping = $this->mappingService->getOrCreateMapping(
                    $this->connectionId,
                    MagentoDefaultEntities::STORE,
                    $store['store_id'],
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
        unset($data['stores']);

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

        /*
         * Set main language and allowed languages
         */
        $languageUuid = $this->mappingService->getLanguageUuid(
            $this->connectionId,
            $data['defaultLocale'],
            $context
        );

        if ($languageUuid === null) {
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
                new AssociationRequiredMissingLog(
                    $this->runId,
                    DefaultEntities::CURRENCY,
                    $data['defaultCurrency'],
                    DefaultEntities::SALES_CHANNEL
                )
            );

            return new ConvertStruct(null, $this->originalData);
        }
        $converted['currencyId'] = $currencyUuid;
        $converted['currencies'] = $this->getSalesChannelCurrencies($currencyUuid, $data, $context);
        unset($data['currencies'], $data['defaultCurrency']);

        /*
         * Set navigation category
         */
        $categoryMapping = $this->mappingService->getMapping(
            $this->connectionId,
            DefaultEntities::CATEGORY,
            $data['store_group']['root_category_id'],
            $context
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

            return new ConvertStruct(null, $this->originalData);
        }
        $categoryUuid = $categoryMapping['entityUuid'];
        $this->mappingIds[] = $categoryMapping['id'];
        $converted['navigationCategoryId'] = $categoryUuid;
        unset($data['store_group']);

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
            $this->loggingService->addLogEntry(new EmptyNecessaryFieldRunLog(
                $this->runId,
                DefaultEntities::SALES_CHANNEL,
                $this->oldIdentifier,
                'payment methods'
            ));

            return new ConvertStruct(null, $this->originalData);
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

        if (empty($data)) {
            $data = null;
        }

        return new ConvertStruct($converted, $data, $this->mainMapping['id']);
    }

    protected function getSalesChannelLanguages(string $languageUuid, array $data, Context $context): array
    {
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

        return array_values($languages);
    }

    protected function getSalesChannelCurrencies(string $currencyUuid, array $data, Context $context): array
    {
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

        return array_values($currencies);
    }

    protected function getSalesChannelCountries(string $countryUuid, array $data, Context $context): array
    {
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

        return array_values($countries);
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
                        DefaultEntities::PAYMENT_METHOD,
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

        return array_values($payments);
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
                        DefaultEntities::SHIPPING_METHOD,
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

        return array_values($carriers);
    }

    protected function getSalesChannelTranslation(array &$salesChannel, array $data): void
    {
        $language = $this->mappingService->getDefaultLanguage($this->context);
        if ($language->getLocale()->getCode() === $data['defaultLocale']) {
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
        $localeTranslation['languageId'] = $languageUuid;

        $salesChannel['translations'][$languageUuid] = $localeTranslation;
    }
}
