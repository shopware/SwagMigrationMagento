<?php declare(strict_types=1);
/*
 * (c) shopware AG <info@shopware.com>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Swag\MigrationMagento\Profile\Magento\Converter;

use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\QuantityPriceDefinition;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTax;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRule;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\Cart\Tax\TaxCalculator;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStates;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\System\NumberRange\ValueGenerator\NumberRangeValueGeneratorInterface;
use Swag\MigrationMagento\Migration\Mapping\MagentoMappingServiceInterface;
use Swag\MigrationMagento\Profile\Magento\DataSelection\DefaultEntities as MagentoDefaultEntities;
use Swag\MigrationMagento\Profile\Magento\Premapping\OrderDeliveryStateReader as MagentoOrderDeliveryStateReader;
use Swag\MigrationMagento\Profile\Magento\Premapping\PaymentMethodReader;
use Swag\MigrationMagento\Profile\Magento19\Premapping\Magento19OrderStateReader;
use Swag\MigrationMagento\Profile\Magento19\Premapping\Magento19SalutationReader;
use SwagMigrationAssistant\Migration\Converter\ConvertStruct;
use SwagMigrationAssistant\Migration\DataSelection\DefaultEntities;
use SwagMigrationAssistant\Migration\Logging\Log\EmptyNecessaryFieldRunLog;
use SwagMigrationAssistant\Migration\Logging\Log\UnknownEntityLog;
use SwagMigrationAssistant\Migration\Logging\LoggingServiceInterface;
use SwagMigrationAssistant\Migration\MigrationContextInterface;
use SwagMigrationAssistant\Profile\Shopware\Exception\AssociationEntityRequiredMissingException;
use SwagMigrationAssistant\Profile\Shopware\Premapping\OrderDeliveryStateReader;

abstract class OrderConverter extends MagentoConverter
{
    /**
     * @var MagentoMappingServiceInterface
     */
    protected $mappingService;

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
     * @var string
     */
    protected $uuid;

    /**
     * @var string
     */
    protected $oldIdentifier;

    /**
     * @var TaxCalculator
     */
    protected $taxCalculator;

    /**
     * @var string
     */
    protected $salutationUuid;

    /**
     * @var NumberRangeValueGeneratorInterface
     */
    protected $numberRangeValueGenerator;

    /**
     * @var string[]
     */
    protected static $requiredDataFieldKeys = [
        'orders',
        'billingAddress',
        'shippingAddress',
        'items',
    ];

    /**
     * @var string[]
     */
    protected static $requiredCustomerDataFieldKeys = [
        'customer_email',
        'customer_firstname',
        'customer_lastname',
    ];

    public function __construct(
        MagentoMappingServiceInterface $mappingService,
        LoggingServiceInterface $loggingService,
        TaxCalculator $taxCalculator,
        NumberRangeValueGeneratorInterface $numberRangeValueGenerator
    ) {
        parent::__construct($mappingService, $loggingService);

        $this->taxCalculator = $taxCalculator;
        $this->numberRangeValueGenerator = $numberRangeValueGenerator;
    }

    public function getSourceIdentifier(array $data): string
    {
        return $data['orders']['entity_id'];
    }

    public function convert(array $data, Context $context, MigrationContextInterface $migrationContext): ConvertStruct
    {
        /*
         * Checking required fields and logging missing ones
         */
        $fields = $this->checkForEmptyRequiredDataFields($data, self::$requiredDataFieldKeys);
        if (!isset($data['orders']['entity_id'])) {
            $fields[] = 'entity_id';
        }

        if (!empty($fields)) {
            $this->loggingService->addLogEntry(new EmptyNecessaryFieldRunLog(
                $migrationContext->getRunUuid(),
                DefaultEntities::ORDER,
                $data['identifier'],
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
        $this->context = $context;
        $this->oldIdentifier = $data['orders']['entity_id'];

        $connection = $migrationContext->getConnection();
        $this->connectionId = '';
        if ($connection !== null) {
            $this->connectionId = $connection->getId();
        }

        if ($this->oldIdentifier === null) {
            $this->loggingService->addLogEntry(new EmptyNecessaryFieldRunLog(
                $migrationContext->getRunUuid(),
                DefaultEntities::ORDER,
                $data['identifier'],
                'entity_id'
            ));

            return new ConvertStruct(null, $data);
        }

        /*
         * Set main mapping
         */
        $this->mainMapping = $this->mappingService->getOrCreateMapping(
            $this->connectionId,
            DefaultEntities::ORDER,
            $this->oldIdentifier,
            $this->context,
            $this->checksum
        );

        $converted = [];
        $converted['id'] = $this->mainMapping['entityUuid'];
        unset($data['orders']['entity_id']);
        $this->uuid = $converted['id'];

        /*
         * Set sales channel
         */
        $converted['salesChannelId'] = Defaults::SALES_CHANNEL;
        if (isset($data['orders']['store_id'])) {
            $this->convertSalesChannel($converted, $data);
        }

        if (!$this->convertOrderCustomer($converted, $data)) {
            return new ConvertStruct(null, $this->originalData);
        }

        $this->convertValue($converted, 'orderNumber', $data['orders'], 'increment_id');
        $this->convertValue($converted, 'currencyFactor', $data['orders'], 'store_to_order_rate', self::TYPE_FLOAT);
        $this->convertValue($converted, 'orderDateTime', $data['orders'], 'created_at', self::TYPE_DATETIME);

        if (!$this->convertCurrency($converted, $data)) {
            return new ConvertStruct(null, $this->originalData);
        }

        $converted['itemRounding'] = [
            'decimals' => $context->getRounding()->getDecimals(),
            'interval' => 0.01,
            'roundForNet' => true,
        ];
        $converted['totalRounding'] = $converted['itemRounding'];

        if (!$this->convertOrderStatus($converted, $data)) {
            return new ConvertStruct(null, $this->originalData);
        }

        /*
         * Set line items, shipping costs and transactions
         */
        if (isset($data['items'])) {
            $this->convertOrderItems($converted, $data);
        }

        if (!$this->convertBillingAddress($converted, $data)) {
            return new ConvertStruct(null, $this->originalData);
        }

        /*
         * Set deliveries
         */
        if (isset($data['shipments'])) {
            $converted['deliveries'] = $this->getDeliveries($data, $converted);
        } else {
            $this->getDefaultDelivery($data, $converted);
        }
        unset($data['shippingAddress'], $data['items'], $data['billingAddress'], $data['shipments']);

        $converted['deepLinkCode'] = \md5($converted['id']);
        unset($data['orders'], $data['identifier']);

        $resultData = $data;
        if (empty($resultData)) {
            $resultData = null;
        }
        $this->updateMainMapping($migrationContext, $context);

        return new ConvertStruct($converted, $resultData, $this->mainMapping['id']);
    }

    protected function getSalutation(string $salutation): ?string
    {
        $salutationMapping = $this->mappingService->getMapping(
            $this->connectionId,
            Magento19SalutationReader::getMappingName(),
            $salutation,
            $this->context
        );

        if ($salutationMapping === null) {
            $salutationMapping = $this->mappingService->getMapping(
                $this->connectionId,
                DefaultEntities::SALUTATION,
                'default_salutation',
                $this->context
            );

            if ($salutationMapping === null) {
                $this->loggingService->addLogEntry(new UnknownEntityLog(
                    $this->runId,
                    DefaultEntities::SALUTATION,
                    $salutation,
                    DefaultEntities::CUSTOMER,
                    $this->oldIdentifier
                ));

                return null;
            }
        }
        $this->mappingIds[] = $salutationMapping['id'];

        return $salutationMapping['entityUuid'];
    }

    protected function getTaxRules(array $originalData): TaxRuleCollection
    {
        $taxRates = [];
        foreach ($originalData['items'] as $item) {
            if (!isset($item['parentItem']['tax_percent'])) {
                $taxRates[] = $item['tax_percent'];

                continue;
            }

            $taxRates[] = $item['parentItem']['tax_percent'];
        }
        $taxRates = \array_unique($taxRates);

        $taxRules = [];
        foreach ($taxRates as $taxRate) {
            $taxRules[] = new TaxRule((float) $taxRate);
        }

        return new TaxRuleCollection($taxRules);
    }

    protected function getLineItems(array $originalData, Context $context, CalculatedTaxCollection $taxCollection): array
    {
        $lineItems = [];

        foreach ($originalData as $originalLineItem) {
            $taxPercent = (float) $originalLineItem['tax_percent'];
            if (isset($originalLineItem['parentItem']['tax_percent'])) {
                $taxPercent = (float) $originalLineItem['parentItem']['tax_percent'];
            }

            $taxRules = new TaxRuleCollection([
                new TaxRule($taxPercent),
            ]);

            $isProduct = (bool) $originalLineItem['is_virtual'] === true || (isset($originalLineItem['product_id']) && $originalLineItem['product_id'] !== null);

            $mapping = $this->mappingService->getOrCreateMapping(
                $this->connectionId,
                DefaultEntities::ORDER_LINE_ITEM,
                $originalLineItem['item_id'],
                $this->context
            );
            $this->mappingIds[] = $mapping['id'];

            $lineItem = [
                'id' => $mapping['entityUuid'],
            ];

            $this->convertValue($lineItem, 'identifier', $originalLineItem, 'sku', self::TYPE_STRING, false);

            if ($isProduct) {
                $mapping = $this->mappingService->getMapping(
                    $this->connectionId,
                    DefaultEntities::PRODUCT,
                    $originalLineItem['product_id'],
                    $this->context
                );

                if ($mapping !== null) {
                    $lineItem['referencedId'] = $mapping['entityUuid'];
                    $lineItem['productId'] = $mapping['entityUuid'];
                    $lineItem['identifier'] = $mapping['entityUuid'];
                    $lineItem['payload']['productNumber'] = $originalLineItem['sku'] ?? '';
                }

                $lineItem['payload']['options'] = [];
                $lineItem['type'] = LineItem::PRODUCT_LINE_ITEM_TYPE;
            } else {
                $lineItem['type'] = LineItem::CREDIT_LINE_ITEM_TYPE;
            }

            $this->convertValue($lineItem, 'quantity', $originalLineItem, 'qty_ordered', self::TYPE_INTEGER);
            $this->convertValue($lineItem, 'label', $originalLineItem, 'name');

            $lineItemPrice = $originalLineItem['price'];
            if (isset($originalLineItem['parentItem']['price'])) {
                $lineItemPrice = $originalLineItem['parentItem']['price'];
            }

            $taxAmount = $originalLineItem['tax_amount'];
            if (isset($originalLineItem['parentItem']['tax_amount'])) {
                $taxAmount = $originalLineItem['parentItem']['tax_amount'];
            }

            $totalPrice = $lineItem['quantity'] * $lineItemPrice;
            $calculatedTax = new CalculatedTaxCollection([new CalculatedTax((float) $taxAmount, $taxPercent, $totalPrice)]);

            $lineItem['price'] = new CalculatedPrice(
                (float) $lineItemPrice,
                $totalPrice,
                $calculatedTax,
                $taxRules,
                (int) $lineItem['quantity']
            );

            $lineItem['priceDefinition'] = new QuantityPriceDefinition(
                (float) $lineItemPrice,
                $taxRules
            );

            foreach ($calculatedTax->getElements() as $tax) {
                $currentValue = $taxCollection->get((string) $tax->getTaxRate());

                if ($currentValue !== null) {
                    $currentValue->setPrice($tax->getPrice() + $currentValue->getPrice());
                    $currentValue->setTax($tax->getTax() + $currentValue->getTax());
                } else {
                    $taxCollection->add($tax);
                }
            }

            if (!isset($lineItem['identifier'])) {
                $this->loggingService->addLogEntry(new EmptyNecessaryFieldRunLog(
                    $this->runId,
                    DefaultEntities::ORDER_LINE_ITEM,
                    $originalLineItem['item_id'],
                    'identifier'
                ));

                continue;
            }

            $lineItems[] = $lineItem;
        }

        return $lineItems;
    }

    protected function getDeliveries(array $data, array $converted): array
    {
        $taxRules = $this->getTaxRules($data);
        $shippingCosts = $this->getShippingCosts((float) $data['orders']['shipping_amount']);

        $deliveries = [];
        foreach ($data['shipments'] as $shipment) {
            $mapping = $this->mappingService->getOrCreateMapping(
                $this->connectionId,
                DefaultEntities::ORDER_DELIVERY,
                $shipment['entity_id'],
                $this->context
            );
            $this->mappingIds[] = $mapping['id'];

            $delivery = [];
            $delivery['id'] = $mapping['entityUuid'];

            $deliveryStateMapping = $this->mappingService->getMapping(
                $this->connectionId,
                OrderDeliveryStateReader::getMappingName(),
                MagentoOrderDeliveryStateReader::DEFAULT_SHIPPED_STATUS,
                $this->context
            );

            if ($deliveryStateMapping === null) {
                continue;
            }

            $this->mappingIds[] = $deliveryStateMapping['id'];
            $delivery['stateId'] = $deliveryStateMapping['entityUuid'];

            $delivery['shippingDateEarliest'] = $converted['orderDateTime'];
            $delivery['shippingDateLatest'] = $converted['orderDateTime'];

            if (isset($data['orders']['shipping_method'])) {
                $delivery['shippingMethodId'] = $this->getShippingMethod($data['orders']['shipping_method']);
            }

            if (!isset($delivery['shippingMethodId'])) {
                continue;
            }

            if (isset($data['shippingaddress']['id'])) {
                $delivery['shippingOrderAddress'] = $this->getAddress($data['shippingAddress']);
            }

            if (!isset($delivery['shippingOrderAddress'])) {
                $delivery['shippingOrderAddress'] = $this->getAddress($data['billingAddress']);
            }

            if (isset($shipment['items'])) {
                $positions = [];
                foreach ($shipment['items'] as $item) {
                    $mapping = $this->mappingService->getOrCreateMapping(
                        $this->connectionId,
                        DefaultEntities::ORDER_DELIVERY_POSITION,
                        $item['entity_id'],
                        $this->context
                    );
                    $this->mappingIds[] = $mapping['id'];

                    $lineItemMapping = $this->mappingService->getMapping(
                        $this->connectionId,
                        DefaultEntities::ORDER_LINE_ITEM,
                        $item['order_item_id'],
                        $this->context
                    );

                    if (isset($item['child_item_id']) && $lineItemMapping === null) {
                        $lineItemMapping = $this->mappingService->getMapping(
                            $this->connectionId,
                            DefaultEntities::ORDER_LINE_ITEM,
                            $item['child_item_id'],
                            $this->context
                        );
                    }

                    if ($lineItemMapping === null) {
                        continue;
                    }

                    $this->mappingIds[] = $lineItemMapping['id'];

                    $totalPrice = $item['qty'] * $item['price'];
                    $calculatedTax = $this->taxCalculator->calculateGrossTaxes($totalPrice, $taxRules);

                    $positions[] = [
                        'id' => $mapping['entityUuid'],
                        'orderLineItemId' => $lineItemMapping['entityUuid'],
                        'price' => new CalculatedPrice(
                            (float) $item['price'],
                            $totalPrice,
                            $calculatedTax,
                            $taxRules,
                            (int) $item['qty']
                        ),
                    ];
                }

                $delivery['positions'] = $positions;
            }
            $delivery['shippingCosts'] = $shippingCosts;

            $deliveries[] = $delivery;
        }

        return $deliveries;
    }

    protected function getShippingMethod(string $shippingMethodId): ?string
    {
        if (\mb_strpos($shippingMethodId, '_') !== false) {
            \preg_match('/^([^_]*)_/', $shippingMethodId, $matches);

            if (isset($matches[1])) {
                $shippingMethodId = $matches[1];
            }
        }

        $shippingMethodMapping = $this->mappingService->getMapping(
            $this->connectionId,
            DefaultEntities::SHIPPING_METHOD,
            $shippingMethodId,
            $this->context
        );

        if ($shippingMethodMapping === null) {
            $this->loggingService->addLogEntry(new UnknownEntityLog(
                $this->runId,
                DefaultEntities::SHIPPING_METHOD,
                $shippingMethodId,
                DefaultEntities::ORDER,
                $this->oldIdentifier
            ));

            return null;
        }
        $this->mappingIds[] = $shippingMethodMapping['id'];

        return $shippingMethodMapping['entityUuid'];
    }

    protected function getAddress(array $originalData, string $entityName = DefaultEntities::ORDER_ADDRESS): array
    {
        $identifier = $originalData['entity_id'];
        if ($entityName === DefaultEntities::CUSTOMER_ADDRESS) {
            $identifier .= '_guest'; // If the address is for a guest order
        }

        $address = [];
        $mapping = $this->mappingService->getOrCreateMapping(
            $this->connectionId,
            $entityName,
            $identifier,
            $this->context
        );
        $this->mappingIds[] = $mapping['id'];
        $address['id'] = $mapping['entityUuid'];

        $mapping = $this->mappingService->getMapping(
            $this->connectionId,
            DefaultEntities::COUNTRY,
            $originalData['country_id'],
            $this->context
        );

        if ($mapping === null) {
            $this->loggingService->addLogEntry(
                new UnknownEntityLog(
                    $this->runId,
                    DefaultEntities::COUNTRY,
                    $originalData['country_id'],
                    DefaultEntities::ORDER,
                    $this->oldIdentifier
                )
            );

            return [];
        }
        $this->mappingIds[] = $mapping['id'];
        $address['countryId'] = $mapping['entityUuid'];

        if (isset($originalData['stateID'])) {
            $mapping = $this->mappingService->getMapping(
                $this->connectionId,
                DefaultEntities::COUNTRY_STATE,
                $originalData['region_id'],
                $this->context
            );

            if ($mapping !== null) {
                $this->mappingIds[] = $mapping['id'];
                $address['countryStateId'] = $mapping['entityUuid'];
            }
        }

        $address['salutationId'] = $this->salutationUuid;
        $this->convertValue($address, 'firstName', $originalData, 'firstname');
        $this->convertValue($address, 'lastName', $originalData, 'lastname');
        $this->convertValue($address, 'zipcode', $originalData, 'postcode');
        $this->convertValue($address, 'city', $originalData, 'city');
        $this->convertValue($address, 'company', $originalData, 'company');
        $this->convertValue($address, 'street', $originalData, 'street');
        $this->convertValue($address, 'title', $originalData, 'prefix');
        if (isset($originalData['vat_id'])) {
            $this->convertValue($address, 'vatId', $originalData, 'vat_id');
        }
        $this->convertValue($address, 'phoneNumber', $originalData, 'telephone');

        return $address;
    }

    protected function getTransactions(array $data, array &$converted): void
    {
        $converted['transactions'] = [];

        /** @var CartPrice $cartPrice */
        $cartPrice = $converted['price'];

        $stateName = OrderTransactionStates::STATE_OPEN;
        if (isset($data['orders']['total_invoiced'], $data['orders']['total_paid'])) {
            if ($data['orders']['total_invoiced'] === $data['orders']['total_paid']) {
                $stateName = OrderTransactionStates::STATE_PAID;
            } else {
                if ($data['orders']['total_paid'] > 0) {
                    $stateName = OrderTransactionStates::STATE_PARTIALLY_PAID;
                }
            }
        }

        $stateId = $this->mappingService->getTransactionStateUuid($stateName, $this->context);
        if ($stateId === null) {
            return;
        }

        $paymentMethodUuid = $this->getPaymentMethod($data);
        if ($paymentMethodUuid === null) {
            return;
        }

        $mapping = $this->mappingService->getOrCreateMapping(
            $this->connectionId,
            DefaultEntities::ORDER_TRANSACTION,
            $this->oldIdentifier,
            $this->context
        );
        $id = $mapping['entityUuid'];
        $this->mappingIds[] = $mapping['id'];

        $transactions = [
            [
                'id' => $id,
                'paymentMethodId' => $paymentMethodUuid,
                'stateId' => $stateId,
                'amount' => new CalculatedPrice(
                    $cartPrice->getTotalPrice(),
                    $cartPrice->getTotalPrice(),
                    $cartPrice->getCalculatedTaxes(),
                    $cartPrice->getTaxRules()
                ),
            ],
        ];

        $converted['transactions'] = $transactions;
    }

    protected function getPaymentMethod(array $originalData): ?string
    {
        if (!isset($originalData['orders']['payment']['method'])) {
            return null;
        }

        $paymentMethodMapping = $this->mappingService->getMapping(
            $this->connectionId,
            PaymentMethodReader::getMappingName(),
            $originalData['orders']['payment']['method'],
            $this->context
        );

        if ($paymentMethodMapping === null) {
            $this->loggingService->addLogEntry(new UnknownEntityLog(
                $this->runId,
                'payment_method',
                $originalData['orders']['payment']['method'],
                DefaultEntities::ORDER_TRANSACTION,
                $this->oldIdentifier
            ));

            return null;
        }

        $this->mappingIds[] = $paymentMethodMapping['id'];

        return $paymentMethodMapping['entityUuid'];
    }

    /**
     * @throws AssociationEntityRequiredMissingException
     */
    protected function convertOrderCustomer(array &$converted, array &$data): bool
    {
        $guestOrder = false;
        $fields = $this->checkForEmptyRequiredDataFields($data['orders'], self::$requiredCustomerDataFieldKeys);

        if (!empty($fields)) {
            $this->loggingService->addLogEntry(new EmptyNecessaryFieldRunLog(
                $this->runId,
                DefaultEntities::ORDER,
                $data['identifier'],
                \implode(',', $fields)
            ));

            return false;
        }

        if (isset($data['orders']['customer_id']) && $data['orders']['customer_is_guest'] === '0') {
            $customerMapping = $this->mappingService->getMapping(
                $this->connectionId,
                DefaultEntities::CUSTOMER,
                $data['orders']['customer_id'],
                $this->context
            );

            if ($customerMapping === null) {
                throw new AssociationEntityRequiredMissingException(
                    DefaultEntities::ORDER,
                    DefaultEntities::CUSTOMER
                );
            }
            $converted['orderCustomer'] = [
                'customerId' => $customerMapping['entityUuid'],
            ];
            $this->mappingIds[] = $customerMapping['id'];
            unset($customerMapping);
        } else {
            $guestOrder = true;
            $guestCustomerMapping = $this->mappingService->getOrCreateMapping(
                $this->connectionId,
                MagentoDefaultEntities::GUEST_CUSTOMER,
                $data['orders']['customer_email'],
                $this->context
            );
            $converted['orderCustomer'] = [
                'customerId' => $guestCustomerMapping['entityUuid'],
                'customer' => [
                    'id' => $guestCustomerMapping['entityUuid'],
                ],
            ];
            $this->convertValue($converted['orderCustomer']['customer'], 'email', $data['orders'], 'customer_email', self::TYPE_STRING, false);
            $this->convertValue($converted['orderCustomer']['customer'], 'firstName', $data['orders'], 'customer_firstname', self::TYPE_STRING, false);
            $this->convertValue($converted['orderCustomer']['customer'], 'lastName', $data['orders'], 'customer_lastname', self::TYPE_STRING, false);
            $converted['orderCustomer']['customer']['guest'] = true;
        }
        $this->convertValue($converted['orderCustomer'], 'email', $data['orders'], 'customer_email');
        $this->convertValue($converted['orderCustomer'], 'firstName', $data['orders'], 'customer_firstname');
        $this->convertValue($converted['orderCustomer'], 'lastName', $data['orders'], 'customer_lastname');

        /*
         * Set salutation
         */
        if (isset($data['orders']['customer_salutation'])) {
            $salutationUuid = $this->getSalutation($data['orders']['customer_salutation']);

            if ($salutationUuid !== null) {
                $this->salutationUuid = $salutationUuid;
            }
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
                    DefaultEntities::ORDER,
                    $this->oldIdentifier,
                    'salutation'
                ));

                return false;
            }
            $this->mappingIds[] = $mapping['id'];
            $this->salutationUuid = $mapping['entityUuid'];
        }

        if ($this->salutationUuid === null) {
            return false;
        }
        $converted['orderCustomer']['salutationId'] = $this->salutationUuid;

        if ($guestOrder === true) {
            $converted['orderCustomer']['customer']['salutationId'] = $this->salutationUuid;
            $customerGroupMapping = $this->mappingService->getMapping(
                $this->connectionId,
                DefaultEntities::CUSTOMER_GROUP,
                $data['orders']['customer_group_id'],
                $this->context
            );

            if ($customerGroupMapping === null) {
                $this->loggingService->addLogEntry(new EmptyNecessaryFieldRunLog(
                    $this->runId,
                    DefaultEntities::ORDER,
                    $this->oldIdentifier,
                    'customer_group_id'
                ));

                return false;
            }

            $languageMapping = $this->mappingService->getMapping(
                $this->connectionId,
                MagentoDefaultEntities::STORE_LANGUAGE,
                $data['orders']['store_id'],
                $this->context
            );
            if ($languageMapping === null) {
                return false;
            }

            $paymentMethodUuid = $this->getPaymentMethod($data);
            if ($paymentMethodUuid === null) {
                return false;
            }

            $this->mappingIds[] = $languageMapping['id'];
            $this->mappingIds[] = $customerGroupMapping['id'];
            $converted['orderCustomer']['customer']['groupId'] = $customerGroupMapping['entityUuid'];
            $converted['orderCustomer']['customer']['salesChannelId'] = $converted['salesChannelId'];
            $converted['orderCustomer']['customer']['languageId'] = $languageMapping['entityUuid'];
            $converted['orderCustomer']['customer']['defaultPaymentMethodId'] = $paymentMethodUuid;
            $converted['orderCustomer']['customer']['customerNumber'] = $this->numberRangeValueGenerator->getValue('customer', $this->context, null);

            $billingAddress = $this->getAddress($data['billingAddress'], DefaultEntities::CUSTOMER_ADDRESS);
            if (empty($billingAddress)) {
                $this->loggingService->addLogEntry(new EmptyNecessaryFieldRunLog(
                    $this->runId,
                    DefaultEntities::ORDER,
                    $this->oldIdentifier,
                    'billingAddress'
                ));

                return false;
            }
            $converted['orderCustomer']['customer']['addresses'][] = $billingAddress;
            $converted['orderCustomer']['customer']['defaultBillingAddressId'] = $billingAddress['id'];
            $shippingAddress = $this->getAddress($data['shippingAddress'], DefaultEntities::CUSTOMER_ADDRESS);
            if (empty($shippingAddress)) {
                $shippingAddress = $billingAddress;
            } else {
                $converted['orderCustomer']['customer']['addresses'][] = $shippingAddress;
            }

            $converted['orderCustomer']['customer']['defaultShippingAddressId'] = $shippingAddress['id'];
        }
        unset($data['customerSalutation']);

        return true;
    }

    protected function convertSalesChannel(array &$converted, array &$data): void
    {
        $salesChannelMapping = $this->mappingService->getMapping(
            $this->connectionId,
            MagentoDefaultEntities::STORE,
            $data['orders']['store_id'],
            $this->context
        );

        if ($salesChannelMapping !== null) {
            $this->mappingIds[] = $salesChannelMapping['id'];
            $converted['salesChannelId'] = $salesChannelMapping['entityUuid'];
        }
    }

    protected function convertCurrency(array &$converted, array &$data): bool
    {
        $currencyUuid = null;
        if (isset($data['orders']['order_currency_code'])) {
            $currencyUuid = $this->mappingService->getCurrencyUuid(
                $this->connectionId,
                $data['orders']['order_currency_code'],
                $this->context
            );
        }
        if ($currencyUuid === null) {
            $this->loggingService->addLogEntry(new EmptyNecessaryFieldRunLog(
                $this->runId,
                DefaultEntities::ORDER,
                $this->oldIdentifier,
                'currency'
            ));

            return false;
        }
        $converted['currencyId'] = $currencyUuid;

        return true;
    }

    protected function convertOrderStatus(array &$converted, array &$data): bool
    {
        $stateMapping = $this->mappingService->getMapping(
            $this->connectionId,
            Magento19OrderStateReader::getMappingName(),
            (string) $data['orders']['status'],
            $this->context
        );

        if ($stateMapping === null) {
            $this->loggingService->addLogEntry(new UnknownEntityLog(
                $this->runId,
                'order_state',
                (string) $data['orders']['status'],
                DefaultEntities::ORDER,
                $this->oldIdentifier
            ));

            return false;
        }
        $converted['stateId'] = $stateMapping['entityUuid'];
        $this->mappingIds[] = $stateMapping['id'];

        return true;
    }

    protected function convertOrderItems(array &$converted, array &$data): void
    {
        $shippingCosts = $this->getShippingCosts((float) $data['orders']['shipping_amount']);
        $taxRules = $this->getTaxRules($data);
        $taxCollection = new CalculatedTaxCollection([]);

        $converted['lineItems'] = $this->getLineItems($data['items'], $this->context, $taxCollection);

        $discount = 0.0;
        if (isset($data['orders']['discount_amount']) && (float) $data['orders']['discount_amount'] < 0) {
            $mapping = $this->mappingService->getOrCreateMapping(
                $this->connectionId,
                DefaultEntities::ORDER_LINE_ITEM . '_credit',
                $this->oldIdentifier,
                $this->context
            );
            $this->mappingIds[] = $mapping['id'];

            $discount = (float) $data['orders']['discount_amount'];
            $label = $data['orders']['discount_description'] ?? 'Discount';
            $calculatedTax = new CalculatedTaxCollection([new CalculatedTax(0.0, 0.0, $discount)]);
            $taxRule = new TaxRuleCollection([new TaxRule(0.0)]);

            $converted['lineItems'][] = [
                'id' => $mapping['entityUuid'],
                'type' => LineItem::CREDIT_LINE_ITEM_TYPE,
                'quantity' => 1,
                'label' => $label,
                'identifier' => $mapping['entityUuid'],
                'price' => new CalculatedPrice(
                    $discount,
                    $discount,
                    $calculatedTax,
                    $taxRule,
                    1
                ),
                'priceDefinition' => new QuantityPriceDefinition(
                    $discount,
                    $taxRule
                ),
            ];
        }

        $converted['price'] = new CartPrice(
            (float) $data['orders']['subtotal'] + (float) $data['orders']['shipping_amount'] + $discount,
            (float) $data['orders']['grand_total'],
            (float) $data['orders']['subtotal'] + $discount,
            $taxCollection,
            $taxRules,
            CartPrice::TAX_STATE_NET
        );

        $converted['shippingCosts'] = $shippingCosts;

        $this->getTransactions($data, $converted);
        unset($data['orders']['payment']);
    }

    protected function convertBillingAddress(array &$converted, array &$data): bool
    {
        $billingAddress = $this->getAddress($data['billingAddress']);
        if (empty($billingAddress)) {
            $this->loggingService->addLogEntry(new EmptyNecessaryFieldRunLog(
                $this->runId,
                DefaultEntities::ORDER,
                $this->oldIdentifier,
                'billingAddress'
            ));

            return false;
        }
        $converted['billingAddressId'] = $billingAddress['id'];
        $converted['addresses'][] = $billingAddress;

        return true;
    }

    private function getShippingCosts(float $amount): CalculatedPrice
    {
        return new CalculatedPrice(
            $amount,
            $amount,
            new CalculatedTaxCollection(),
            new TaxRuleCollection()
        );
    }

    private function getDefaultDelivery(array &$data, array &$converted): void
    {
        $deliveryMapping = $this->mappingService->getOrCreateMapping(
            $this->connectionId,
            DefaultEntities::ORDER_DELIVERY,
            'order_' . $this->oldIdentifier,
            $this->context
        );
        $this->mappingIds[] = $deliveryMapping['id'];

        $shippingOrderAddress = null;
        if (isset($data['shippingAddress'])) {
            $shippingOrderAddress = $this->getAddress($data['shippingAddress']);
        }

        if ($shippingOrderAddress === null) {
            $shippingOrderAddress = $this->getAddress($data['billingAddress']);
        }

        $deliveryStateMapping = $this->mappingService->getMapping(
            $this->connectionId,
            OrderDeliveryStateReader::getMappingName(),
            MagentoOrderDeliveryStateReader::DEFAULT_OPEN_STATUS,
            $this->context
        );

        $shippingMethodId = $this->getShippingMethod($data['orders']['shipping_method']);

        if ($deliveryStateMapping === null || $shippingMethodId === null) {
            return;
        }

        $shippingAmount = (float) ($data['orders']['shipping_amount'] ?? 0.0);
        $converted['deliveries'] = [
            [
                'id' => $deliveryMapping['entityUuid'],
                'shippingMethodId' => $shippingMethodId,
                'shippingOrderAddress' => $shippingOrderAddress,
                'shippingCosts' => new CalculatedPrice(
                    $shippingAmount,
                    $shippingAmount,
                    new CalculatedTaxCollection(),
                    new TaxRuleCollection()
                ),
                'stateId' => $deliveryStateMapping['entityUuid'],
                'shippingDateEarliest' => $converted['orderDateTime'],
                'shippingDateLatest' => $converted['orderDateTime'],
            ],
        ];
    }
}
