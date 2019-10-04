<?php declare(strict_types=1);

namespace Swag\MigrationMagento\Profile\Magento\Converter;

use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Util\AccessKeyHelper;
use Shopware\Core\Framework\Context;
use Swag\MigrationMagento\Migration\Mapping\MagentoMappingServiceInterface;
use Swag\MigrationMagento\Profile\Magento\DataSelection\DataSet\SalesChannelDataSet;
use Swag\MigrationMagento\Profile\Magento\Magento19Profile;
use Swag\MigrationMagento\Profile\Magento\Premapping\PaymentMethodReader;
use Swag\MigrationMagento\Profile\Magento\Premapping\ShippingMethodReader;
use SwagMigrationAssistant\Migration\Converter\ConvertStruct;
use SwagMigrationAssistant\Migration\DataSelection\DefaultEntities;
use SwagMigrationAssistant\Migration\Logging\Log\AssociationRequiredMissingLog;
use SwagMigrationAssistant\Migration\Logging\LoggingServiceInterface;
use SwagMigrationAssistant\Migration\MigrationContextInterface;

class SalesChannelConverter extends MagentoConverter
{
    /**
     * @var MagentoMappingServiceInterface
     */
    private $mappingService;

    /**
     * @var LoggingServiceInterface
     */
    private $loggingService;

    /**
     * @var string
     */
    private $connectionId;

    /**
     * @var Context
     */
    private $context;

    public function __construct(
        MagentoMappingServiceInterface $mappingService,
        LoggingServiceInterface $loggingService
    ) {
        $this->mappingService = $mappingService;
        $this->loggingService = $loggingService;
    }

    public function supports(MigrationContextInterface $migrationContext): bool
    {
        return $migrationContext->getProfile()->getName() === Magento19Profile::PROFILE_NAME
            && $migrationContext->getDataSet()::getEntity() === SalesChannelDataSet::getEntity();
    }

    public function getSourceIdentifier(array $data): string
    {
        return $data['customerID'];
    }

    public function writeMapping(Context $context): void
    {
        $this->mappingService->writeMapping($context);
    }

    public function convert(array $data, Context $context, MigrationContextInterface $migrationContext): ConvertStruct
    {
        $this->context = $context;
        $this->connectionId = $migrationContext->getConnection()->getId();

        $converted = [];
        $converted['id'] = $this->mappingService->createNewUuid(
            $this->connectionId,
            DefaultEntities::SALES_CHANNEL,
            $data['website_id'],
            $context
        );

        $converted['customerGroupId'] = Defaults::FALLBACK_CUSTOMER_GROUP;

        $languageUuid = $this->mappingService->getLanguageUuid(
            $this->connectionId,
            $data['defaultLocale'],
            $context
        );

        if ($languageUuid === null) {
            $this->loggingService->addLogEntry(
                new AssociationRequiredMissingLog(
                    $migrationContext->getRunUuid(),
                    DefaultEntities::LANGUAGE,
                    $data['defaultLocale'],
                    DefaultEntities::SALES_CHANNEL
                )
            );

            return new ConvertStruct(null, $data);
        }

        $converted['languageId'] = $languageUuid;
        $converted['languages'] = $this->getSalesChannelLanguages($languageUuid, $data, $context);

        $currencyUuid = $this->mappingService->getCurrencyUuid(
            $this->connectionId,
            $data['defaultCurrency'],
            $context
        );

        if ($currencyUuid === null) {
            $this->loggingService->addLogEntry(
                new AssociationRequiredMissingLog(
                    $migrationContext->getRunUuid(),
                    DefaultEntities::CURRENCY,
                    $data['defaultCurrency'],
                    DefaultEntities::SALES_CHANNEL
                )
            );

            return new ConvertStruct(null, $data);
        }

        $converted['currencyId'] = $currencyUuid;
        $converted['currencies'] = $this->getSalesChannelCurrencies($currencyUuid, $data, $context);

        $categoryUuid = $this->mappingService->getUuid(
            $this->connectionId,
            DefaultEntities::CATEGORY,
            $data['store_group']['root_category_id'],
            $context
        );

        if ($categoryUuid === null) {
            $this->loggingService->addLogEntry(
                new AssociationRequiredMissingLog(
                    $migrationContext->getRunUuid(),
                    DefaultEntities::CATEGORY,
                    $data['store_group']['root_category_id'],
                    DefaultEntities::SALES_CHANNEL
                )
            );

            return new ConvertStruct(null, $data);
        }
        $converted['navigationCategoryId'] = $categoryUuid;

        $countryUuid = $this->mappingService->getMagentoCountryUuid(
            $data['defaultCountry'],
            $this->connectionId,
            $context
        );

        if ($countryUuid === null) {
            $this->loggingService->addLogEntry(
                new AssociationRequiredMissingLog(
                    $migrationContext->getRunUuid(),
                    DefaultEntities::COUNTRY,
                    $data['defaultCountry'],
                    DefaultEntities::SALES_CHANNEL
                )
            );

            return new ConvertStruct(null, $data);
        }

        $converted['countryId'] = $countryUuid;
        $converted['countries'] = $this->getSalesChannelCountries($countryUuid, $data, $context);

        $converted['paymentMethods'] = $this->getPaymentMethods($data, $context);
        $converted['paymentMethodId'] = $converted['paymentMethods'][0]['id'];

        $converted['shippingMethods'] = $this->getShippingMethods($data, $context);
        $converted['shippingMethodId'] = $converted['shippingMethods'][0]['id'];

        $converted['typeId'] = Defaults::SALES_CHANNEL_TYPE_STOREFRONT;
        $this->getSalesChannelTranslation($converted, $data);
        $this->convertValue($converted, 'name', $data, 'name');
        $converted['accessKey'] = AccessKeyHelper::generateAccessKey('sales-channel');

        return new ConvertStruct($converted, $data);
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
                $uuid = $this->mappingService->getUuid(
                    $this->connectionId,
                    PaymentMethodReader::getMappingName(),
                    $payment['payment_id'],
                    $context
                );

                if ($uuid === null) {
                    continue;
                }

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
                $uuid = $this->mappingService->getUuid(
                    $this->connectionId,
                    ShippingMethodReader::getMappingName(),
                    $payment['carrier_id'],
                    $context
                );

                if ($uuid === null) {
                    continue;
                }

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

        $localeTranslation['id'] = $this->mappingService->createNewUuid(
            $this->connectionId,
            DefaultEntities::SALES_CHANNEL_TRANSLATION,
            $data['website_id'] . ':' . $data['defaultLocale'],
            $this->context
        );
        $languageUuid = $this->mappingService->getLanguageUuid($this->connectionId, $data['defaultLocale'], $this->context);
        $localeTranslation['languageId'] = $languageUuid;

        $salesChannel['translations'][$languageUuid] = $localeTranslation;
    }
}