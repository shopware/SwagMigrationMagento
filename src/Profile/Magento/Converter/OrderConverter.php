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
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRule;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\Cart\Tax\TaxCalculator;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStates;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Swag\MigrationMagento\Migration\Mapping\MagentoMappingServiceInterface;
use Swag\MigrationMagento\Profile\Magento\DataSelection\DataSet\OrderDataSet;
use Swag\MigrationMagento\Profile\Magento\DataSelection\DefaultEntities as MagentoDefaultEntities;
use Swag\MigrationMagento\Profile\Magento\Magento19Profile;
use Swag\MigrationMagento\Profile\Magento\Premapping\OrderStateReader;
use Swag\MigrationMagento\Profile\Magento\Premapping\PaymentMethodReader;
use Swag\MigrationMagento\Profile\Magento\Premapping\SalutationReader;
use SwagMigrationAssistant\Migration\Converter\ConvertStruct;
use SwagMigrationAssistant\Migration\DataSelection\DefaultEntities;
use SwagMigrationAssistant\Migration\Logging\Log\EmptyNecessaryFieldRunLog;
use SwagMigrationAssistant\Migration\Logging\Log\UnknownEntityLog;
use SwagMigrationAssistant\Migration\Logging\LoggingServiceInterface;
use SwagMigrationAssistant\Migration\MigrationContextInterface;
use SwagMigrationAssistant\Profile\Shopware\Exception\AssociationEntityRequiredMissingException;
use SwagMigrationAssistant\Profile\Shopware\Premapping\OrderDeliveryStateReader;

class OrderConverter extends MagentoConverter
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
     * @var string[]
     */
    protected static $requiredDataFieldKeys = [
        'orders',
        'billingAddress',
        'shippingAddress',
        'items',
    ];

    public function __construct(
        MagentoMappingServiceInterface $mappingService,
        LoggingServiceInterface $loggingService,
        TaxCalculator $taxCalculator
    ) {
        parent::__construct($mappingService, $loggingService);

        $this->taxCalculator = $taxCalculator;
    }

    public function supports(MigrationContextInterface $migrationContext): bool
    {
        return $migrationContext->getProfile()->getName() === Magento19Profile::PROFILE_NAME
            && $migrationContext->getDataSet()::getEntity() === OrderDataSet::getEntity();
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
        if (!isset($data['orders']['customer_id'])) {
            $fields[] = 'customer_id';
        }

        if (!isset($data['orders']['entity_id'])) {
            $fields[] = 'entity_id';
        }

        if (!empty($fields)) {
            $this->loggingService->addLogEntry(new EmptyNecessaryFieldRunLog(
                $migrationContext->getRunUuid(),
                DefaultEntities::ORDER,
                $data['identifier'],
                implode(',', $fields)
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

        $this->connectionId = $migrationContext->getConnection()->getId();
        $this->context = $context;
        $this->oldIdentifier = $data['orders']['entity_id'];

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
        $converted['id'] = $this->mainMapping['entityUuid'];
        unset($data['orders']['entity_id']);
        $this->uuid = $converted['id'];

        /*
         * Set customer
         */
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

        /*
         * Set salutation
         */
        if (isset($data['orders']['customer_salutation'])) {
            $this->salutationUuid = $this->getSalutation($data['orders']['customer_salutation']);
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

                return new ConvertStruct(null, $data);
            }
            $this->mappingIds[] = $mapping['id'];
            $this->salutationUuid = $mapping['entityUuid'];
        }

        if ($this->salutationUuid === null) {
            return new ConvertStruct(null, $this->originalData);
        }
        $converted['orderCustomer']['salutationId'] = $this->salutationUuid;
        unset($data['customerSalutation']);

        $this->convertValue($converted['orderCustomer'], 'email', $data['orders'], 'customer_email');
        $this->convertValue($converted['orderCustomer'], 'firstName', $data['orders'], 'customer_firstname');
        $this->convertValue($converted['orderCustomer'], 'lastName', $data['orders'], 'customer_lastname');

        $this->convertValue($converted, 'orderNumber', $data['orders'], 'increment_id');
        $this->convertValue($converted, 'currencyFactor', $data['orders'], 'store_to_order_rate', self::TYPE_FLOAT);
        $this->convertValue($converted, 'orderDateTime', $data['orders'], 'created_at', self::TYPE_DATETIME);

        /*
         * Set currency
         */
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

            return new ConvertStruct(null, $this->originalData);
        }

        $converted['currencyId'] = $currencyUuid;

        /*
         * Set order state
         */
        $stateMapping = $this->mappingService->getMapping(
            $this->connectionId,
            OrderStateReader::getMappingName(),
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

            return new ConvertStruct(null, $this->originalData);
        }
        $converted['stateId'] = $stateMapping['entityUuid'];
        $this->mappingIds[] = $stateMapping['id'];

        $shippingCosts = new CalculatedPrice(
            (float) $data['orders']['shipping_amount'],
            (float) $data['orders']['shipping_amount'],
            new CalculatedTaxCollection(),
            new TaxRuleCollection()
        );

        /*
         * Set line items, shipping costs and transactions
         */
        if (isset($data['items'])) {
            $taxRules = $this->getTaxRules($data);
            $taxStatus = CartPrice::TAX_STATE_GROSS;

            $converted['lineItems'] = $this->getLineItems($data['items'], $taxRules, $taxStatus, $context);

            $converted['price'] = new CartPrice(
                (float) $data['orders']['grand_total'],
                (float) $data['orders']['subtotal_incl_tax'],
                (float) $data['orders']['subtotal_incl_tax'] - (float) $data['orders']['shipping_amount'],
                new CalculatedTaxCollection([]),
                $taxRules,
                $taxStatus
            );

            $converted['shippingCosts'] = $shippingCosts;

            $this->getTransactions($data, $converted);
            unset($data['items'], $data['orders']['payment']);
        }

        /*
         * Set deliveries
         */
        if (isset($data['shipments'])) {
            $converted['deliveries'] = $this->getDeliveries($data, $converted, $shippingCosts);
        }
        unset($data['shippingAddress']);

        /*
         * Set billing address
         */
        $billingAddress = $this->getAddress($data['billingAddress']);
        if (empty($billingAddress)) {
            $this->loggingService->addLogEntry(new EmptyNecessaryFieldRunLog(
                $this->runId,
                DefaultEntities::ORDER,
                $this->oldIdentifier,
                'billingAddress'
            ));

            return new ConvertStruct(null, $this->originalData);
        }
        $converted['billingAddressId'] = $billingAddress['id'];
        $converted['addresses'][] = $billingAddress;
        unset($data['billingAddress']);

        $converted['deepLinkCode'] = md5($converted['id']);

        /*
         * Set sales channel
         */
        $converted['salesChannelId'] = Defaults::SALES_CHANNEL;
        if (isset($data['orders']['store_id'])) {
            $salesChannelMapping = $this->mappingService->getMapping(
                $this->connectionId,
                MagentoDefaultEntities::STORE,
                $data['orders']['store_id'],
                $context
            );

            if ($salesChannelMapping !== null) {
                $this->mappingIds[] = $salesChannelMapping['id'];
                $converted['salesChannelId'] = $salesChannelMapping['entityUuid'];
            }
        }
        unset($data['orders'], $data['identifier']);

        if (empty($data)) {
            $data = null;
        }

        $this->updateMainMapping($migrationContext, $context);

        return new ConvertStruct($converted, $data, $this->mainMapping['id']);
    }

    protected function getSalutation(string $salutation): ?string
    {
        $salutationMapping = $this->mappingService->getMapping(
            $this->connectionId,
            SalutationReader::getMappingName(),
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
        $taxRates = array_unique(array_column($originalData['items'], 'tax_percent'));

        $taxRules = [];
        foreach ($taxRates as $taxRate) {
            $taxRules[] = new TaxRule((float) $taxRate);
        }

        return new TaxRuleCollection($taxRules);
    }

    protected function getLineItems(array $originalData, TaxRuleCollection $taxRules, string $taxStatus, Context $context): array
    {
        $lineItems = [];

        foreach ($originalData as $originalLineItem) {
            $isProduct = (bool) $originalLineItem['is_virtual'] === true;

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

            $this->convertValue($lineItem, 'identifier', $originalLineItem, 'sku');

            if ($isProduct) {
                $lineItem['type'] = LineItem::PRODUCT_LINE_ITEM_TYPE;
            } else {
                $lineItem['type'] = LineItem::CREDIT_LINE_ITEM_TYPE;
            }

            $this->convertValue($lineItem, 'quantity', $originalLineItem, 'qty_ordered', self::TYPE_INTEGER);
            $this->convertValue($lineItem, 'label', $originalLineItem, 'name');

            $calculatedTax = null;
            $totalPrice = $lineItem['quantity'] * $originalLineItem['price'];
            if ($taxStatus === CartPrice::TAX_STATE_NET) {
                $calculatedTax = $this->taxCalculator->calculateNetTaxes($totalPrice, $taxRules);
            }

            if ($taxStatus === CartPrice::TAX_STATE_GROSS) {
                $calculatedTax = $this->taxCalculator->calculateGrossTaxes($totalPrice, $taxRules);
            }

            if ($calculatedTax !== null) {
                $lineItem['price'] = new CalculatedPrice(
                    (float) $originalLineItem['price'],
                    (float) $totalPrice,
                    $calculatedTax,
                    $taxRules,
                    (int) $lineItem['quantity']
                );

                $lineItem['priceDefinition'] = new QuantityPriceDefinition(
                    (float) $originalLineItem['price'],
                    $taxRules,
                    $context->getCurrencyPrecision()
                );
            }

            if (!isset($lineItem['identifier'])) {
                $this->loggingService->addLogEntry(new EmptyNecessaryFieldRunLog(
                    $this->runId,
                    DefaultEntities::ORDER_LINE_ITEM,
                    $originalLineItem['id'],
                    'identifier'
                ));

                continue;
            }

            $lineItems[] = $lineItem;
        }

        return $lineItems;
    }

    protected function getDeliveries(array $data, array $converted, CalculatedPrice $shippingCosts): array
    {
        $deliveries = [];
        foreach ($data['shipments'] as $shipment) {
            $mapping = $this->mappingService->getOrCreateMapping(
                $this->connectionId,
                DefaultEntities::ORDER_DELIVERY,
                $shipment['entity_id'],
                $this->context
            );
            $this->mappingIds[] = $mapping['id'];
            $delivery['id'] = $mapping['entityUuid'];

            $deliveryStateMapping = $this->mappingService->getMapping(
                $this->connectionId,
                OrderDeliveryStateReader::getMappingName(),
                (string) $shipment['shipment_status'],
                $this->context
            );

            if ($deliveryStateMapping === null) {
                continue;
            }

            $this->mappingIds[] = $deliveryStateMapping['id'];
            $delivery['stateId'] = $deliveryStateMapping['entityUuid'];

            $delivery['shippingDateEarliest'] = $converted['orderDateTime'];
            $delivery['shippingDateLatest'] = $converted['orderDateTime'];

            if (isset($data['shipping_method'])) {
                $delivery['shippingMethodId'] = $this->getShippingMethod($data['shipping_method']);
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

                    if ($lineItemMapping === null) {
                        continue;
                    }

                    $this->mappingIds[] = $lineItemMapping['id'];

                    $positions[] = [
                        'id' => $mapping['entityUuid'],
                        'orderLineItemId' => $lineItemMapping['entityUuid'],
                        'price' => $item['price'],
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

    protected function getAddress(array $originalData): array
    {
        $address = [];
        $mapping = $this->mappingService->getOrCreateMapping(
            $this->connectionId,
            DefaultEntities::ORDER_ADDRESS,
            $originalData['entity_id'],
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
}
