# 12.0.0

- [BREAKING] Raised required `swag/migration-assistant` version from `~16.2` to `~17.0`.
- [BREAKING] Added constructor parameter `MigrationConfiguration $migrationConfig` to `Swag\MigrationMagento\Profile\Magento\Media\LocalMediaProcessor`.
- Replaced the removed `MediaProcessingProcessor::MEDIA_ERROR_THRESHOLD` constant with `MigrationConfiguration::migrationDefaultExceptionThreshold`.

# 11.0.0

- [BREAKING] Made class `Swag\MigrationMagento\Profile\Magento\MediaLocalMediaProcessor` abstract
- [#34](https://github.com/shopware/SwagMigrationMagento/pull/35) - Compatibility with Migration Assistant v16.
  - [BREAKING] Changed return type of `Swag\MigrationMagento\Profile\Magento\Gateway\Connection\ConnectionFactoryInterface::createDatabaseConnection` from `?Connection` to `Connection`. The method now throws `MigrationMagentoException::databaseConnectionError()` instead of returning `null`.
  - [BREAKING] Changed signature of `Swag\MigrationMagento\Profile\Magento19\Gateway\Local\Magento19LocalGateway::supports` from `supports(MigrationContextInterface $migrationContext)` to `supports(ProfileInterface $profile)`.
  - [BREAKING] Changed signature of `Swag\MigrationMagento\Profile\Magento19\Gateway\Local\Magento19LocalGateway::readTotals` from `readTotals(MigrationContextInterface $migrationContext, Context $context)` to `readTotals(MigrationContextInterface $migrationContext)`.
  - [BREAKING] Changed signature of `Swag\MigrationMagento\Profile\Magento2\Gateway\Local\Magento2LocalGateway::readTotals` from `readTotals(MigrationContextInterface $migrationContext, Context $context)` to `readTotals(MigrationContextInterface $migrationContext)`.
  - [BREAKING] Removed class `Swag\MigrationMagento\Migration\Logging\FileHandleErrorLog` without replacement.
  - [BREAKING] Changed type of property `$connection` in `Swag\MigrationMagento\Profile\Magento\Gateway\Local\Reader\AbstractReader` from `?Connection` to `Connection`.
  - [BREAKING] Renamed mapping entity field from `entityUuid` to `entityId` in all mapping usages (aligns with Migration Assistant v16 changes).

# 10.0.0

- MIG-1094 - Compatibility with Shopware 6.7.
  - [BREAKING] Increased the minimum required Shopware version to ~6.7.0.
  - [BREAKING] Removed method `mediaMimeTypeError` from `Swag\MigrationMagento\Exception\MigrationMagentoException` without replacement.
  - [BREAKING] Marked `__construct` of class `Swag\MigrationMagento\Migration\Mapping\MagentoMappingService` as internal.
  - [BREAKING] Marked `__construct` of class `Swag\MigrationMagento\Profile\Magento\Converter\CategoryConverter` as internal.
  - [BREAKING] Marked `__construct` of class `Swag\MigrationMagento\Profile\Magento\Converter\CountryConverter` as internal.
  - [BREAKING] Marked `__construct` of class `Swag\MigrationMagento\Profile\Magento\Converter\CurrencyConverter` as internal.
  - [BREAKING] Marked `__construct` of class `Swag\MigrationMagento\Profile\Magento\Converter\CustomerConverter` as internal.
  - [BREAKING] Marked `__construct` of class `Swag\MigrationMagento\Profile\Magento\Converter\LanguageConverter` as internal.
  - [BREAKING] Marked `__construct` of class `Swag\MigrationMagento\Profile\Magento\Converter\MagentoConverter` as internal.
  - [BREAKING] Marked `__construct` of class `Swag\MigrationMagento\Profile\Magento\Converter\ManufacturerConverter` as internal.
  - [BREAKING] Marked `__construct` of class `Swag\MigrationMagento\Profile\Magento\Converter\MediaConverter` as internal.
  - [BREAKING] Marked `__construct` of class `Swag\MigrationMagento\Profile\Magento\Converter\NotAccociatedMediaConverter` as internal.
  - [BREAKING] Marked `__construct` of class `Swag\MigrationMagento\Profile\Magento\Converter\OrderConverter` as internal.
  - [BREAKING] Marked `__construct` of class `Swag\MigrationMagento\Profile\Magento\Converter\ProductConverter` as internal.
  - [BREAKING] Marked `__construct` of class `Swag\MigrationMagento\Profile\Magento\Converter\PropertyGroupConverter` as internal.
  - [BREAKING] Marked `__construct` of class `Swag\MigrationMagento\Profile\Magento\Converter\SalesChannelConverter` as internal.
  - [BREAKING] Marked `__construct` of class `Swag\MigrationMagento\Profile\Magento\Gateway\Local\Reader\AbstractReader` as internal.
  - [BREAKING] Marked `__construct` of class `Swag\MigrationMagento\Profile\Magento\Gateway\Local\Reader\EnvironmentReader` as internal.
  - [BREAKING] Marked `__construct` of class `Swag\MigrationMagento\Profile\Magento\Gateway\Local\Reader\LocalTableReader` as internal.
  - [BREAKING] Marked `__construct` of class `Swag\MigrationMagento\Profile\Magento\Media\LocalMediaProcessor` as internal.
  - [BREAKING] Marked `__construct` of class `Swag\MigrationMagento\Profile\Magento\Premapping\AdminStoreReader` as internal.
  - [BREAKING] Marked `__construct` of class `Swag\MigrationMagento\Profile\Magento\Premapping\CustomerGroupReader` as internal.
  - [BREAKING] Marked `__construct` of class `Swag\MigrationMagento\Profile\Magento\Premapping\OrderStateReader` as internal.
  - [BREAKING] Marked `__construct` of class `Swag\MigrationMagento\Profile\Magento\Premapping\PaymentMethodReader` as internal.
  - [BREAKING] Marked `__construct` of class `Swag\MigrationMagento\Profile\Magento\Premapping\SalutationReader` as internal.
  - [BREAKING] Marked `__construct` of class `Swag\MigrationMagento\Profile\Magento\Premapping\ShippingMethodReader` as internal.
  - [BREAKING] Marked `__construct` of class `Swag\MigrationMagento\Profile\Magento\Premapping\TaxReader` as internal.
  - [BREAKING] Marked `__construct` of class `Swag\MigrationMagento\Profile\Magento\Premapping\Magento19LocalGateway` as internal.
  - [BREAKING] Marked classes `Swag\MigrationMagento\Test\**\*` as internal.

# 9.0.0

- MIG-1039 - Updated country state conversion to use new methods provided by the migration assistant
  - [BREAKING] Removed method `getCountryStateUuid` from `Swag\MigrationMagento\Migration\Mapping\MagentoMappingServiceInterface` and its default implementation `Swag\MigrationMagento\Migration\Mapping\MagentoMappingService`
- MIG-1071 - Move to the new Lookup service structure
  - [BREAKING] Added new constructor parameter `MediaDefaultFolderLookup $mediaFolderLookup`, `LowestRootCategoryLookup $lowestRootCategoryLookup`, `DefaultCmsPageLookup $defaultCmsPageLookup`, `LanguageLookup $languageLookup` to `Swag\MigrationMagento\Profile\Magento\Converter\CategoryConverter`
  - [BREAKING] Added new constructor parameter `LanguageLookup $languageLookup`, `CountryLookup $countryLookup` to `Swag\MigrationMagento\Profile\Magento\Converter\CountryConverter`
  - [BREAKING] Added new constructor parameter `CurrencyLookup $currencyLookup`, `LanguageLookup $languageLookup` to `Swag\MigrationMagento\Profile\Magento\Converter\CurrencyConverter`
  - [BREAKING] Added new constructor parameter `CountryLookup $countryLookup`, `CountryStateLookup $countryStateLookup` to `Swag\MigrationMagento\Profile\Magento\Converter\CustomerConverter`
  - [BREAKING] Added new constructor parameter `LanguageLookup $languageLookup`, `LocaleLookup $localeLookup` to `Swag\MigrationMagento\Profile\Magento\Converter\LanguageConverter`
  - [BREAKING] Added new constructor parameter `LanguageLookup $languageLookup` to `Swag\MigrationMagento\Profile\Magento\Converter\ManufacturerConverter`
  - [BREAKING] Added new constructor parameter `MediaDefaultFolderLookup $mediaFolderLookup` to `Swag\MigrationMagento\Profile\Magento\Converter\MediaConverter`
  - [BREAKING] Added new constructor parameter `CountryLookup $countryLookup`, `CurrencyLookup $currencyLookup`, `CountryStateLookup $countryStateLookup`, `TransactionStateLookup $magentoTransactionStateLookup` to `Swag\MigrationMagento\Profile\Magento\Converter\OrderConverter`
  - [BREAKING] Added new constructor parameter `MediaDefaultFolderLookup $mediaFolderLookup`, `LanguageLookup $languageLookup` to `Swag\MigrationMagento\Profile\Magento\Converter\ProductConverter`
  - [BREAKING] Added new constructor parameter `LanguageLookup $languageLookup` to `Swag\MigrationMagento\Profile\Magento\Converter\PropertyGroupConverter`
  - [BREAKING] Added new constructor parameter `CurrencyLookup $currencyLookup`, `LanguageLookup $languageLookup`, `CountryLookup $countryLookup` to `\Swag\MigrationMagento\Profile\Magento\Converter\SalesChannelConverter`
  - [BREAKING] Added new constructor parameter `, ` to ``
  - [BREAKING] Removed method `getMagentoCountryUuid` from `Swag\MigrationMagento\Migration\Mapping\MagentoMappingService` and all implementors. Use `SwagMigrationAssistant\Migration\Mapping\Lookup\CountryLookup::get()` instead.
  - [BREAKING] Removed method `getTransactionStateUuid` from `Swag\MigrationMagento\Migration\Mapping\MagentoMappingService` and all implementors. Use `SwagMigrationAssistant\Migration\Mapping\Lookup\TransactionStateLookup::get()` instead.
  - [BREAKING] Removed method `getCountryStateUuid` from `Swag\MigrationMagento\Migration\Mapping\MagentoMappingService` and all implementors. Use `SwagMigrationAssistant\Migration\Mapping\Lookup\CountryStateLookup::get()` instead.
- MIG-1091 - Fixed errors in the service container that occurred due to the move to Lookup Services
