<?php declare(strict_types=1);
/*
 * (c) shopware AG <info@shopware.com>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Swag\MigrationMagento\Profile\Magento\Converter;

use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressDefinition;
use Shopware\Core\Checkout\Customer\CustomerDefinition;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\NumberRange\ValueGenerator\NumberRangeValueGeneratorInterface;
use Swag\MigrationMagento\Migration\Mapping\MagentoMappingServiceInterface;
use Swag\MigrationMagento\Profile\Magento\DataSelection\DefaultEntities as MagentoDefaultEntities;
use Swag\MigrationMagento\Profile\Magento\Premapping\AdminStoreReader;
use Swag\MigrationMagento\Profile\Magento\Premapping\PaymentMethodReader;
use SwagMigrationAssistant\Migration\Converter\ConvertStruct;
use SwagMigrationAssistant\Migration\DataSelection\DefaultEntities;
use SwagMigrationAssistant\Migration\Logging\Log\Builder\MigrationLogBuilder;
use SwagMigrationAssistant\Migration\Logging\Log\ConvertFieldReassignedLog;
use SwagMigrationAssistant\Migration\Logging\Log\ConvertObjectTypeUnsupportedLog;
use SwagMigrationAssistant\Migration\Logging\Log\ConvertSourceDataIncompleteLog;
use SwagMigrationAssistant\Migration\Logging\LoggingServiceInterface;
use SwagMigrationAssistant\Migration\Mapping\Lookup\CountryLookup;
use SwagMigrationAssistant\Migration\Mapping\Lookup\CountryStateLookup;
use SwagMigrationAssistant\Migration\Mapping\MappingServiceInterface;
use SwagMigrationAssistant\Migration\MigrationContextInterface;
use SwagMigrationAssistant\Profile\Shopware\Premapping\SalutationReader;

#[Package('fundamentals@after-sales')]
abstract class CustomerConverter extends MagentoConverter
{
    protected MappingServiceInterface|MagentoMappingServiceInterface $mappingService;

    protected string $runId;

    protected string $connectionId;

    protected Context $context;

    protected NumberRangeValueGeneratorInterface $numberRangeValueGenerator;

    /**
     * @var list<string>
     */
    protected static array $requiredDataFieldKeys = [
        'email',
        'firstname',
        'lastname',
    ];

    /**
     * @var list<string>
     */
    protected static array $requiredAddressDataFieldKeys = [
        'entity_id',
        'country_id',
        'country_iso2',
        'country_iso3',
    ];

    protected string $oldIdentifier;

    /**
     * @internal
     */
    public function __construct(
        MagentoMappingServiceInterface $mappingService,
        LoggingServiceInterface $loggingService,
        NumberRangeValueGeneratorInterface $numberRangeValueGenerator,
        protected readonly CountryLookup $countryLookup,
        protected readonly CountryStateLookup $countryStateLookup,
    ) {
        parent::__construct($mappingService, $loggingService);

        $this->numberRangeValueGenerator = $numberRangeValueGenerator;
    }

    public function getSourceIdentifier(array $data): string
    {
        return $data['entity_id'];
    }

    public function convert(array $data, Context $context, MigrationContextInterface $migrationContext): ConvertStruct
    {
        $fields = $this->checkForEmptyRequiredDataFields($data, self::$requiredDataFieldKeys);

        if (!empty($fields)) {
            $this->loggingService->log(
                MigrationLogBuilder::fromMigrationContext($migrationContext)
                    ->withEntityName(CustomerDefinition::ENTITY_NAME)
                    ->withFieldName(\implode(', ', $fields))
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
        $this->runId = $migrationContext->getRunUuid();
        $this->migrationContext = $migrationContext;
        $this->oldIdentifier = $data['entity_id'];
        $this->context = $context;
        unset($data['entity_id']);

        $connection = $migrationContext->getConnection();
        $this->connectionId = $connection->getId();

        /*
         * Set main mapping
         */
        $this->mainMapping = $this->mappingService->getOrCreateMapping(
            $this->connectionId,
            DefaultEntities::CUSTOMER,
            $this->oldIdentifier,
            $this->context,
            $this->checksum
        );

        $mapping = $this->mappingService->getOrCreateMapping(
            $this->connectionId,
            DefaultEntities::CUSTOMER,
            $data['email'],
            $this->context,
            null,
            null,
            $this->mainMapping['entityId']
        );
        $this->mappingIds[] = $mapping['id'];

        $converted = [];
        $converted['id'] = $this->mainMapping['entityId'];

        /*
         * Set sales channel
         */
        if (isset($data['store_id'])) {
            $this->setSalesChannelId($data, $converted);
            $this->setLanguageId($data, $converted);
            unset($data['store_id']);
        }

        if (empty($converted['salesChannelId'])) {
            $this->setSalesChannelIdViaAdminStore($converted);
        }

        $converted['guest'] = false;
        $this->convertValue($converted, 'active', $data, 'is_active', self::TYPE_BOOLEAN);
        $this->convertValue($converted, 'email', $data, 'email');
        $this->convertValue($converted, 'title', $data, 'prefix');
        $this->convertValue($converted, 'firstName', $data, 'firstname');
        $this->convertValue($converted, 'lastName', $data, 'lastname');
        $this->convertValue($converted, 'birthday', $data, 'dob', self::TYPE_DATETIME);
        if (isset($data['password_hash']) && !$this->setPassword($data, $converted)) {
            return new ConvertStruct(null, $data);
        }
        $customerNumber = $this->mappingService->getValue($this->connectionId, DefaultEntities::CUSTOMER, $this->oldIdentifier, $this->context);
        if ($customerNumber === null) {
            $customerNumber = $this->numberRangeValueGenerator->getValue('customer', $this->context, null);

            /** @phpstan-ignore assign.propertyType (PHPStan cannot recognize the result correctly) */
            $this->mainMapping['entityValue'] = $customerNumber;
        }
        $converted['customerNumber'] = $customerNumber;
        unset($data['increment_id']);

        /*
         * Set salutation
         */
        $salutationUuid = null;

        if (isset($data['gender'])) {
            $salutationUuid = $this->getSalutation($data['gender'], $migrationContext);
        } else {
            $mapping = $this->mappingService->getMapping(
                $this->connectionId,
                DefaultEntities::SALUTATION,
                'default_salutation',
                $this->context
            );

            if ($mapping !== null) {
                $salutationUuid = $mapping['entityId'];
                $this->mappingIds[] = $mapping['id'];
            }
        }

        if ($salutationUuid !== null) {
            $converted['salutationId'] = $salutationUuid;
            unset($data['gender']);
        }

        $mapping = $this->mappingService->getMapping(
            $this->connectionId,
            DefaultEntities::CUSTOMER_GROUP,
            $data['group_id'],
            $context
        );

        if ($mapping === null) {
            return new ConvertStruct(null, $this->originalData);
        }

        $converted['groupId'] = $mapping['entityId'];
        unset($data['group_id']);

        /*
         * Set payment method
         */
        $defaultPaymentMethodUuid = $this->getDefaultPaymentMethod($migrationContext);

        if ($defaultPaymentMethodUuid === null) {
            return new ConvertStruct(null, $this->originalData);
        }

        $converted['defaultPaymentMethodId'] = $defaultPaymentMethodUuid;

        /*
         * Set addresses
         */
        if (isset($data['addresses'], $this->mainMapping['entityId']) && !empty($data['addresses'])) {
            $this->getAddresses($data, $converted, $this->mainMapping['entityId'], $migrationContext);
            unset($data['addresses']);
            unset($data['default_billing'], $data['default_shipping']);
        }

        $this->updateMainMapping($migrationContext, $context);

        // There is no equivalent field
        unset(
            $data['entity_type_id'],
            $data['attribute_set_id'],
            $data['website_id'],
            $data['created_at'],
            $data['updated_at'],
            $data['disable_auto_group_change'],
            $data['confirmation'],
            $data['created_in'],
            $data['middlename'],
            $data['password_hash'],
            $data['reward_update_notification'],
            $data['reward_warning_notification'],
            $data['rp_token'],
            $data['rp_token_created_at'],
            $data['suffix'],
            $data['taxvat']
        );

        $resultData = $data;
        if (empty($resultData)) {
            $resultData = null;
        }

        return new ConvertStruct($converted, $resultData, $this->mainMapping['id'] ?? null);
    }

    protected function getAddresses(array &$originalData, array &$converted, string $customerUuid, MigrationContextInterface $migrationContext): void
    {
        $addresses = [];
        foreach ($originalData['addresses'] as $address) {
            $newAddress = [];

            $fields = $this->checkForEmptyRequiredDataFields($address, self::$requiredAddressDataFieldKeys);

            if (!empty($fields)) {
                $this->loggingService->log(
                    MigrationLogBuilder::fromMigrationContext($migrationContext)
                        ->withEntityName(CustomerAddressDefinition::ENTITY_NAME)
                        ->withFieldName(\implode(', ', $fields))
                        ->withSourceData($address)
                        ->build(ConvertSourceDataIncompleteLog::class)
                );

                continue;
            }

            $addressMapping = $this->mappingService->getOrCreateMapping(
                $this->connectionId,
                DefaultEntities::CUSTOMER_ADDRESS,
                $address['entity_id'],
                $this->context
            );
            $newAddress['id'] = $addressMapping['entityId'];
            $this->mappingIds[] = $addressMapping['id'];

            if (isset($originalData['default_billing']) && $address['entity_id'] === $originalData['default_billing']) {
                $converted['defaultBillingAddressId'] = $newAddress['id'];
                unset($originalData['default_billing']);
            }

            if (isset($originalData['default_shipping']) && $address['entity_id'] === $originalData['default_shipping']) {
                $converted['defaultShippingAddressId'] = $newAddress['id'];
                unset($originalData['default_shipping']);
            }

            $newAddress['salutationId'] = $converted['salutationId'];
            $newAddress['customerId'] = $customerUuid;

            $countryUuid = $this->countryLookup->getByIso3($address['country_iso3'], $this->context);

            if ($countryUuid === null) {
                $this->loggingService->log(
                    MigrationLogBuilder::fromMigrationContext($migrationContext)
                        ->withEntityName(CustomerDefinition::ENTITY_NAME)
                        ->withFieldName('countryId')
                        ->withFieldSourcePath('country_iso3')
                        ->withSourceData($originalData)
                        ->withConvertedData($converted)
                        ->build(ConvertObjectTypeUnsupportedLog::class)
                );

                continue;
            }

            $newAddress['countryId'] = $countryUuid;

            if (isset($address['region_id'])
                && isset($address['region_code'])
            ) {
                $countryStateUuid = $this->countryStateLookup->get(
                    $address['country_iso2'],
                    $address['region_code'],
                    $this->context
                );

                if ($countryStateUuid !== null) {
                    $newAddress['countryStateId'] = $countryStateUuid;
                } elseif (!empty($address['region_name'])) {
                    $mapping = $this->mappingService->createMapping(
                        $this->connectionId,
                        DefaultEntities::COUNTRY_STATE,
                        $address['region_id']
                    );

                    $newAddress['countryState'] = [
                        'id' => $mapping['entityId'],
                        'name' => $address['region_name'],
                        'shortCode' => $address['region_code'],
                        'countryId' => $countryUuid,
                    ];
                } else {
                    $this->loggingService->log(
                        MigrationLogBuilder::fromMigrationContext($migrationContext)
                            ->withEntityName(CustomerDefinition::ENTITY_NAME)
                            ->withFieldName('countryState')
                            ->withFieldSourcePath('region_code')
                            ->withSourceData($originalData)
                            ->withConvertedData($converted)
                            ->build(ConvertObjectTypeUnsupportedLog::class)
                    );
                }
            }

            $this->convertValue($newAddress, 'firstName', $address, 'firstname');
            $this->convertValue($newAddress, 'lastName', $address, 'lastname');
            $this->convertValue($newAddress, 'zipcode', $address, 'postcode');
            $this->convertValue($newAddress, 'city', $address, 'city');
            $this->convertValue($newAddress, 'company', $address, 'company');
            $this->convertValue($newAddress, 'street', $address, 'street');
            $this->convertValue($newAddress, 'phoneNumber', $address, 'telephone');

            $addresses[] = $newAddress;
        }

        if (empty($addresses)) {
            return;
        }

        $converted['addresses'] = $addresses;

        // No valid default billing and shipping address was converted, so use the first valid one as default
        $this->checkUnsetDefaultShippingAndDefaultBillingAddress($originalData, $converted, $addresses, $migrationContext);

        // No valid default shipping address was converted, but the default billing address is valid
        $this->checkUnsetDefaultShippingAddress($originalData, $converted, $migrationContext);

        // No valid default billing address was converted, but the default shipping address is valid
        $this->checkUnsetDefaultBillingAddress($originalData, $converted, $migrationContext);
    }

    protected function checkUnsetDefaultShippingAndDefaultBillingAddress(array &$originalData, array &$converted, array $addresses, MigrationContextInterface $migrationContext): void
    {
        if (!isset($converted['defaultBillingAddressId']) && !isset($converted['defaultShippingAddressId'])) {
            $converted['defaultBillingAddressId'] = $addresses[0]['id'];
            $converted['defaultShippingAddressId'] = $addresses[0]['id'];

            unset($originalData['default_billing_address_id'], $originalData['default_shipping_address_id']);

            $this->loggingService->addLogForEach(
                [
                    'defaultBillingAddressId' => 'default_billing_address_id',
                    'defaultShippingAddressId' => 'default_shipping_address_id',
                ],
                fn (string $key, string $value) => MigrationLogBuilder::fromMigrationContext($this->migrationContext)
                    ->withEntityName(CustomerAddressDefinition::ENTITY_NAME)
                    ->withFieldName($key)
                    ->withFieldSourcePath($value)
                    ->withSourceData($originalData)
                    ->build(ConvertFieldReassignedLog::class)
            );
        }
    }

    protected function checkUnsetDefaultShippingAddress(array &$originalData, array &$converted, MigrationContextInterface $migrationContext): void
    {
        if (!isset($converted['defaultShippingAddressId']) && isset($converted['defaultBillingAddressId'])) {
            $converted['defaultShippingAddressId'] = $converted['defaultBillingAddressId'];

            unset($originalData['default_shipping_address_id']);

            $this->loggingService->addLogForEach(
                [
                    'defaultBillingAddressId' => 'default_billing_address_id',
                    'defaultShippingAddressId' => 'default_shipping_address_id',
                ],
                fn (string $key, string $value) => MigrationLogBuilder::fromMigrationContext($this->migrationContext)
                    ->withEntityName(CustomerAddressDefinition::ENTITY_NAME)
                    ->withFieldName($key)
                    ->withFieldSourcePath($value)
                    ->withSourceData($originalData)
                    ->build(ConvertFieldReassignedLog::class)
            );
        }
    }

    protected function checkUnsetDefaultBillingAddress(array &$originalData, array &$converted, MigrationContextInterface $migrationContext): void
    {
        if (!isset($converted['defaultBillingAddressId']) && isset($converted['defaultShippingAddressId'])) {
            $converted['defaultBillingAddressId'] = $converted['defaultShippingAddressId'];

            unset($originalData['default_billing_address_id']);

            $this->loggingService->addLogForEach(
                [
                    'defaultBillingAddressId' => 'default_billing_address_id',
                    'defaultShippingAddressId' => 'default_shipping_address_id',
                ],
                fn (string $key, string $value) => MigrationLogBuilder::fromMigrationContext($this->migrationContext)
                    ->withEntityName(CustomerAddressDefinition::ENTITY_NAME)
                    ->withFieldName($key)
                    ->withFieldSourcePath($value)
                    ->withSourceData($originalData)
                    ->build(ConvertFieldReassignedLog::class)
            );
        }
    }

    protected function getSalutation(string $gender, MigrationContextInterface $migrationContext): ?string
    {
        $mapping = $this->mappingService->getMapping(
            $this->connectionId,
            SalutationReader::getMappingName(),
            $gender,
            $this->context
        );

        if ($mapping === null) {
            $mapping = $this->mappingService->getMapping(
                $this->connectionId,
                SalutationReader::getMappingName(),
                'default_salutation',
                $this->context
            );

            if ($mapping === null) {
                return null;
            }
        }

        $this->mappingIds[] = $mapping['id'];

        return $mapping['entityId'];
    }

    protected function getDefaultPaymentMethod(MigrationContextInterface $migrationContext): ?string
    {
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

        return $paymentMethodMapping['entityId'];
    }

    protected function setPassword(array &$data, array &$converted): bool
    {
        $converted['legacyPassword'] = $data['password_hash'];
        // we assume md5 as default for Magento 1.9.x
        // This has to be overridden if differs
        $converted['legacyEncoder'] = 'Magento19';
        unset($data['password_hash']);

        return true;
    }

    private function setSalesChannelId(array $data, array &$converted): void
    {
        $salesChannelMapping = $this->mappingService->getMapping(
            $this->connectionId,
            MagentoDefaultEntities::STORE,
            $data['store_id'],
            $this->context
        );

        if ($salesChannelMapping !== null) {
            $this->mappingIds[] = $salesChannelMapping['id'];
            $converted['salesChannelId'] = $salesChannelMapping['entityId'];
        }
    }

    private function setLanguageId(array $data, array &$converted): void
    {
        $languageMapping = $this->mappingService->getMapping(
            $this->connectionId,
            MagentoDefaultEntities::STORE_LANGUAGE,
            $data['store_id'],
            $this->context
        );

        if ($languageMapping !== null) {
            $this->mappingIds[] = $languageMapping['id'];
            $converted['languageId'] = $languageMapping['entityId'];
        }
    }

    private function setSalesChannelIdViaAdminStore(array &$converted): void
    {
        $adminStore = $this->mappingService->getMapping(
            $this->connectionId,
            AdminStoreReader::getMappingName(),
            'admin_store',
            $this->context
        );

        if ($adminStore !== null && isset($adminStore['entityValue'])) {
            $adminStoreId = $adminStore['entityValue'];
            $salesChannelMapping = $this->mappingService->getMapping(
                $this->connectionId,
                MagentoDefaultEntities::STORE,
                $adminStoreId,
                $this->context
            );

            if ($salesChannelMapping !== null) {
                $this->mappingIds[] = $salesChannelMapping['id'];
                $converted['salesChannelId'] = $salesChannelMapping['entityId'];
            }
        }
    }
}
