<?php declare(strict_types=1);
/*
 * (c) shopware AG <info@shopware.com>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Swag\MigrationMagento\Profile\Magento\Converter;

use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\System\NumberRange\ValueGenerator\NumberRangeValueGeneratorInterface;
use Swag\MigrationMagento\Migration\Mapping\MagentoMappingServiceInterface;
use Swag\MigrationMagento\Profile\Magento\DataSelection\DefaultEntities as MagentoDefaultEntities;
use Swag\MigrationMagento\Profile\Magento\Premapping\PaymentMethodReader;
use SwagMigrationAssistant\Migration\Converter\ConvertStruct;
use SwagMigrationAssistant\Migration\DataSelection\DefaultEntities;
use SwagMigrationAssistant\Migration\Logging\Log\EmptyNecessaryFieldRunLog;
use SwagMigrationAssistant\Migration\Logging\Log\FieldReassignedRunLog;
use SwagMigrationAssistant\Migration\Logging\Log\UnknownEntityLog;
use SwagMigrationAssistant\Migration\Logging\LoggingServiceInterface;
use SwagMigrationAssistant\Migration\MigrationContextInterface;
use SwagMigrationAssistant\Profile\Shopware\Premapping\SalutationReader;

abstract class CustomerConverter extends MagentoConverter
{
    /**
     * @var string
     */
    protected $runId;

    /**
     * @var string
     */
    protected $connectionId;

    /**
     * @var Context
     */
    protected $context;

    /**
     * @var NumberRangeValueGeneratorInterface
     */
    protected $numberRangeValueGenerator;

    /**
     * @var string[]
     */
    protected static $requiredDataFieldKeys = [
        'email',
        'firstname',
        'lastname',
    ];

    /**
     * @var string[]
     */
    protected static $requiredAddressDataFieldKeys = [
        'entity_id',
        'firstname',
        'lastname',
        'postcode',
        'city',
        'street',
        'country_id',
        'country_iso2',
        'country_iso3',
    ];

    /**
     * @var string
     */
    protected $oldIdentifier;

    public function __construct(
        MagentoMappingServiceInterface $mappingService,
        LoggingServiceInterface $loggingService,
        NumberRangeValueGeneratorInterface $numberRangeValueGenerator
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
            $this->loggingService->addLogEntry(new EmptyNecessaryFieldRunLog(
                $migrationContext->getRunUuid(),
                DefaultEntities::CUSTOMER,
                $data['entity_id'],
                \implode(',', $fields)
            ));

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
        $this->connectionId = '';
        if ($connection !== null) {
            $this->connectionId = $connection->getId();
        }

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
            $this->mainMapping['entityUuid']
        );
        $this->mappingIds[] = $mapping['id'];

        $converted = [];
        $converted['id'] = $this->mainMapping['entityUuid'];

        /*
         * Set sales channel
         */
        $converted['salesChannelId'] = Defaults::SALES_CHANNEL;
        if (isset($data['store_id'])) {
            $salesChannelMapping = $this->mappingService->getMapping(
                $this->connectionId,
                MagentoDefaultEntities::STORE,
                $data['store_id'],
                $context
            );

            if ($salesChannelMapping !== null) {
                $this->mappingIds[] = $salesChannelMapping['id'];
                $converted['salesChannelId'] = $salesChannelMapping['entityUuid'];
            }

            $languageMapping = $this->mappingService->getMapping(
                $this->connectionId,
                MagentoDefaultEntities::STORE_LANGUAGE,
                $data['store_id'],
                $context
            );

            if ($languageMapping !== null) {
                $this->mappingIds[] = $languageMapping['id'];
                $converted['languageId'] = $languageMapping['entityUuid'];
            }
            unset($data['store_id']);
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
            $this->mainMapping['entityValue'] = $customerNumber;
        }
        $converted['customerNumber'] = $customerNumber;
        unset($data['increment_id']);

        /*
         * Set salutation
         */
        if (isset($data['gender'])) {
            $salutationUuid = $this->getSalutation($data['gender']);
        } else {
            $mapping = $this->mappingService->getMapping(
                $this->connectionId,
                DefaultEntities::SALUTATION,
                'default_salutation',
                $this->context
            );

            if ($mapping === null) {
                $this->loggingService->addLogEntry(new EmptyNecessaryFieldRunLog(
                    $this->runId,
                    DefaultEntities::CUSTOMER,
                    $this->oldIdentifier,
                    'salutation'
                ));

                return new ConvertStruct(null, $data);
            }
            $this->mappingIds[] = $mapping['id'];
            $salutationUuid = $mapping['entityUuid'];
        }

        if ($salutationUuid === null) {
            return new ConvertStruct(null, $this->originalData);
        }
        $converted['salutationId'] = $salutationUuid;
        unset($data['gender']);

        $mapping = $this->mappingService->getMapping(
            $this->connectionId,
            DefaultEntities::CUSTOMER_GROUP,
            $data['group_id'],
            $context
        );
        if ($mapping === null) {
            return new ConvertStruct(null, $this->originalData);
        }
        $converted['groupId'] = $mapping['entityUuid'];
        unset($data['group_id']);

        /*
         * Set payment method
         */
        $defaultPaymentMethodUuid = $this->getDefaultPaymentMethod();
        if ($defaultPaymentMethodUuid === null) {
            return new ConvertStruct(null, $this->originalData);
        }
        $converted['defaultPaymentMethodId'] = $defaultPaymentMethodUuid;

        /*
         * Set addresses
         */
        if (isset($data['addresses']) && !empty($data['addresses'])) {
            $this->getAddresses($data, $converted, $this->mainMapping['entityUuid']);
            unset($data['addresses']);
        }

        /*
         * Set default billing and shipping address
         */
        if (!isset($converted['defaultBillingAddressId'], $converted['defaultShippingAddressId'])) {
            $this->mappingService->deleteMapping($converted['id'], $this->connectionId, $this->context);

            $this->loggingService->addLogEntry(new EmptyNecessaryFieldRunLog(
                $this->runId,
                DefaultEntities::CUSTOMER,
                $this->oldIdentifier,
                'address data'
            ));

            return new ConvertStruct(null, $this->originalData);
        }
        unset($data['default_billing'], $data['default_shipping']);

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

        return new ConvertStruct($converted, $resultData, $this->mainMapping['id']);
    }

    protected function getAddresses(array &$originalData, array &$converted, string $customerUuid): void
    {
        $addresses = [];
        foreach ($originalData['addresses'] as $address) {
            $newAddress = [];

            $fields = $this->checkForEmptyRequiredDataFields($address, self::$requiredAddressDataFieldKeys);
            if (!empty($fields)) {
                $this->loggingService->addLogEntry(new EmptyNecessaryFieldRunLog(
                    $this->runId,
                    DefaultEntities::CUSTOMER_ADDRESS,
                    $address['entity_id'],
                    \implode(',', $fields)
                ));

                continue;
            }

            $addressMapping = $this->mappingService->getOrCreateMapping(
                $this->connectionId,
                DefaultEntities::CUSTOMER_ADDRESS,
                $address['entity_id'],
                $this->context
            );
            $newAddress['id'] = $addressMapping['entityUuid'];
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

            $countryUuid = $this->mappingService->getCountryUuid(
                $address['country_id'],
                $address['country_iso2'],
                $address['country_iso3'],
                $this->connectionId,
                $this->context
            );

            if ($countryUuid === null) {
                $this->loggingService->addLogEntry(
                    new UnknownEntityLog(
                        $this->runId,
                        DefaultEntities::COUNTRY,
                        $address['country_id'],
                        DefaultEntities::ORDER,
                        $this->oldIdentifier
                    )
                );

                continue;
            }

            $newAddress['countryId'] = $countryUuid;

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
        $this->checkUnsetDefaultShippingAndDefaultBillingAddress($originalData, $converted, $addresses);

        // No valid default shipping address was converted, but the default billing address is valid
        $this->checkUnsetDefaultShippingAddress($originalData, $converted);

        // No valid default billing address was converted, but the default shipping address is valid
        $this->checkUnsetDefaultBillingAddress($originalData, $converted);
    }

    protected function checkUnsetDefaultShippingAndDefaultBillingAddress(array &$originalData, array &$converted, array $addresses): void
    {
        if (!isset($converted['defaultBillingAddressId']) && !isset($converted['defaultShippingAddressId'])) {
            $converted['defaultBillingAddressId'] = $addresses[0]['id'];
            $converted['defaultShippingAddressId'] = $addresses[0]['id'];
            unset($originalData['default_billing_address_id'], $originalData['default_shipping_address_id']);

            $this->loggingService->addLogEntry(new FieldReassignedRunLog(
                $this->runId,
                DefaultEntities::CUSTOMER,
                $this->oldIdentifier,
                'default billing and shipping address',
                'first address'
            ));
        }
    }

    protected function checkUnsetDefaultShippingAddress(array &$originalData, array &$converted): void
    {
        if (!isset($converted['defaultShippingAddressId']) && isset($converted['defaultBillingAddressId'])) {
            $converted['defaultShippingAddressId'] = $converted['defaultBillingAddressId'];
            unset($originalData['default_shipping_address_id']);

            $this->loggingService->addLogEntry(new FieldReassignedRunLog(
                $this->runId,
                DefaultEntities::CUSTOMER,
                $this->oldIdentifier,
                'default shipping address',
                'default billing address'
            ));
        }
    }

    protected function checkUnsetDefaultBillingAddress(array &$originalData, array &$converted): void
    {
        if (!isset($converted['defaultBillingAddressId']) && isset($converted['defaultShippingAddressId'])) {
            $converted['defaultBillingAddressId'] = $converted['defaultShippingAddressId'];
            unset($originalData['default_billing_address_id']);

            $this->loggingService->addLogEntry(new FieldReassignedRunLog(
                $this->runId,
                DefaultEntities::CUSTOMER,
                $this->oldIdentifier,
                'default billing address',
                'default shipping address'
            ));
        }
    }

    protected function getSalutation(string $gender): ?string
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
                $this->loggingService->addLogEntry(new UnknownEntityLog(
                    $this->runId,
                    DefaultEntities::SALUTATION,
                    $gender,
                    DefaultEntities::CUSTOMER,
                    $this->oldIdentifier
                ));

                return null;
            }
        }
        $this->mappingIds[] = $mapping['id'];

        return $mapping['entityUuid'];
    }

    protected function getDefaultPaymentMethod(): ?string
    {
        $paymentMethodMapping = $this->mappingService->getMapping(
            $this->connectionId,
            PaymentMethodReader::getMappingName(),
            'default_payment_method',
            $this->context
        );

        if ($paymentMethodMapping === null) {
            $this->loggingService->addLogEntry(new UnknownEntityLog(
                $this->runId,
                DefaultEntities::PAYMENT_METHOD,
                'default_payment_method',
                DefaultEntities::CUSTOMER,
                $this->oldIdentifier
            ));

            return null;
        }
        $this->mappingIds[] = $paymentMethodMapping['id'];

        return $paymentMethodMapping['entityUuid'];
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
}
