<?php declare(strict_types=1);
/*
 * (c) shopware AG <info@shopware.com>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Swag\MigrationMagento\Test\Profile\Magento2\Converter;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Tax\TaxCalculator;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\NumberRange\ValueGenerator\NumberRangeValueGeneratorInterface;
use Swag\MigrationMagento\Profile\Magento\DataSelection\DataSet\OrderDataSet;
use Swag\MigrationMagento\Profile\Magento\DataSelection\DefaultEntities as MagentoDefaultEntities;
use Swag\MigrationMagento\Profile\Magento\Premapping\OrderDeliveryStateReader as MagentoOrderDeliveryStateReader;
use Swag\MigrationMagento\Profile\Magento23\Converter\Magento23OrderConverter;
use Swag\MigrationMagento\Profile\Magento23\Magento23Profile;
use Swag\MigrationMagento\Profile\Magento23\Premapping\Magento23OrderStateReader;
use Swag\MigrationMagento\Test\Mock\Migration\Mapping\DummyMagentoMappingService;
use SwagMigrationAssistant\Migration\Connection\SwagMigrationConnectionEntity;
use SwagMigrationAssistant\Migration\DataSelection\DefaultEntities;
use SwagMigrationAssistant\Migration\MigrationContext;
use SwagMigrationAssistant\Migration\MigrationContextInterface;
use SwagMigrationAssistant\Profile\Shopware\Exception\AssociationEntityRequiredMissingException;
use SwagMigrationAssistant\Profile\Shopware\Premapping\OrderDeliveryStateReader;
use SwagMigrationAssistant\Test\Mock\Migration\Logging\DummyLoggingService;

class Magento2OrderConverterTest extends TestCase
{
    use KernelTestBehaviour;
    use DatabaseTransactionBehaviour;

    /**
     * @var Magento23OrderConverter
     */
    private $orderConverter;

    /**
     * @var DummyLoggingService
     */
    private $loggingService;

    /**
     * @var string
     */
    private $runId;

    private SwagMigrationConnectionEntity $connection;

    /**
     * @var MigrationContextInterface
     */
    private $migrationContext;

    private DummyMagentoMappingService $mappingService;

    /**
     * @var string
     */
    private $defaultSalutation;

    /**
     * @var string
     */
    private $language;

    /**
     * @var string
     */
    private $customerGroup;

    /**
     * @var string
     */
    private $shippingMethod;

    /**
     * @var array
     */
    private $shippedDeliveryState;

    protected function setUp(): void
    {
        $this->mappingService = new DummyMagentoMappingService();
        $this->loggingService = new DummyLoggingService();
        $taxCalculator = new TaxCalculator();

        $this->orderConverter = new Magento23OrderConverter($this->mappingService, $this->loggingService, $taxCalculator, $this->getContainer()->get(NumberRangeValueGeneratorInterface::class));

        $this->runId = Uuid::randomHex();
        $this->connection = new SwagMigrationConnectionEntity();
        $this->connection->setId(Uuid::randomHex());
        $this->connection->setProfileName(Magento23Profile::PROFILE_NAME);
        $this->connection->setName('shopware');

        $this->migrationContext = new MigrationContext(
            new Magento23Profile(),
            $this->connection,
            $this->runId,
            new OrderDataSet(),
            0,
            250
        );

        $context = Context::createDefaultContext();
        $this->mappingService->getOrCreateMapping(
            $this->connection->getId(),
            DefaultEntities::CUSTOMER,
            '28',
            $context,
            null,
            null,
            Uuid::randomHex()
        );

        $this->mappingService->getOrCreateMapping(
            $this->connection->getId(),
            DefaultEntities::SALUTATION,
            '1',
            $context,
            null,
            null,
            Uuid::randomHex()
        );

        $this->defaultSalutation = Uuid::randomHex();
        $this->mappingService->getOrCreateMapping(
            $this->connection->getId(),
            DefaultEntities::SALUTATION,
            'default_salutation',
            $context,
            null,
            null,
            $this->defaultSalutation
        );

        $this->mappingService->getOrCreateMapping(
            $this->connection->getId(),
            DefaultEntities::CURRENCY,
            'USD',
            $context,
            null,
            null,
            Uuid::randomHex()
        );

        $this->mappingService->getOrCreateMapping(
            $this->connection->getId(),
            Magento23OrderStateReader::getMappingName(),
            'pending',
            $context,
            null,
            null,
            Uuid::randomHex()
        );

        $this->mappingService->getOrCreateMapping(
            $this->connection->getId(),
            Magento23OrderStateReader::getMappingName(),
            'processing',
            $context,
            null,
            null,
            Uuid::randomHex()
        );

        $this->mappingService->getOrCreateMapping(
            $this->connection->getId(),
            DefaultEntities::PAYMENT_METHOD,
            'checkmo',
            $context,
            null,
            null,
            Uuid::randomHex()
        );

        $this->mappingService->getOrCreateMapping(
            $this->connection->getId(),
            DefaultEntities::PRODUCT,
            '387',
            $context,
            null,
            null,
            Uuid::randomHex()
        );

        $this->mappingService->getOrCreateMapping(
            $this->connection->getId(),
            DefaultEntities::PRODUCT,
            '388',
            $context,
            null,
            null,
            Uuid::randomHex()
        );

        $this->mappingService->getOrCreateMapping(
            $this->connection->getId(),
            DefaultEntities::COUNTRY,
            'US',
            $context,
            null,
            null,
            Uuid::randomHex()
        );

        $this->customerGroup = Uuid::randomHex();
        $this->mappingService->getOrCreateMapping(
            $this->connection->getId(),
            DefaultEntities::CUSTOMER_GROUP,
            '4',
            $context,
            null,
            null,
            $this->customerGroup
        );

        $this->language = Uuid::randomHex();
        $this->mappingService->getOrCreateMapping(
            $this->connection->getId(),
            MagentoDefaultEntities::STORE_LANGUAGE,
            '1',
            $context,
            null,
            null,
            $this->language
        );

        $this->shippingMethod = Uuid::randomHex();
        $this->mappingService->getOrCreateMapping(
            $this->connection->getId(),
            DefaultEntities::SHIPPING_METHOD,
            'ups',
            $context,
            null,
            null,
            $this->shippingMethod
        );

        $this->shippedDeliveryState = $this->mappingService->getOrCreateMapping(
            $this->connection->getId(),
            OrderDeliveryStateReader::getMappingName(),
            MagentoOrderDeliveryStateReader::DEFAULT_SHIPPED_STATUS,
            $context
        );
    }

    public function testSupports(): void
    {
        $supportsDefinition = $this->orderConverter->supports($this->migrationContext);

        static::assertTrue($supportsDefinition);
    }

    public function testConvert(): void
    {
        $context = Context::createDefaultContext();
        $deliveryStateMapping = $this->mappingService->getOrCreateMapping(
            $this->connection->getId(),
            OrderDeliveryStateReader::getMappingName(),
            MagentoOrderDeliveryStateReader::DEFAULT_OPEN_STATUS,
            $context
        );

        $orderData = require __DIR__ . '/../../../_fixtures/order_data.php';
        $orderData[0]['orders']['shipping_amount'] = 0;
        $orderData[0]['items'][0]['tax_percent'] = 19;

        $convertResult = $this->orderConverter->convert($orderData[0], $context, $this->migrationContext);

        $converted = $convertResult->getConverted();

        static::assertNotNull($converted);
        static::assertNull($convertResult->getUnmapped());
        static::assertArrayHasKey('id', $converted);
        static::assertNotNull($convertResult->getMappingUuid());
        static::assertSame(\round((float) $orderData[0]['orders']['subtotal'] + (float) $orderData[0]['orders']['shipping_amount'], 2), $converted['price']->getNetPrice());
        static::assertSame(\round((float) $orderData[0]['orders']['grand_total'], 2), $converted['price']->getTotalPrice());
        static::assertSame($deliveryStateMapping['entityUuid'], $converted['deliveries'][0]['stateId']);
        static::assertSame($this->shippingMethod, $converted['deliveries'][0]['shippingMethodId']);
        static::assertNotNull($converted['itemRounding']);
        static::assertNotNull($converted['totalRounding']);
    }

    public function testConvertWithInvalidShippingMethod(): void
    {
        $context = Context::createDefaultContext();

        $orderData = require __DIR__ . '/../../../_fixtures/order_data.php';
        $orderData[0]['orders']['shipping_method'] = 'invalid';

        $convertResult = $this->orderConverter->convert($orderData[0], $context, $this->migrationContext);

        $logs = $this->loggingService->getLoggingArray();

        static::assertNotNull($convertResult->getConverted());
        static::assertNull($convertResult->getUnmapped());
        static::assertArrayNotHasKey('deliveries', $convertResult->getConverted());

        static::assertCount(1, $logs);
        static::assertSame('SWAG_MIGRATION_SHIPPING_METHOD_ENTITY_UNKNOWN', $logs[0]['code']);
        static::assertSame($orderData[0]['orders']['shipping_method'], $logs[0]['parameters']['sourceId']);
        static::assertSame($orderData[0]['orders']['entity_id'], $logs[0]['parameters']['requiredForSourceId']);
    }

    public function testConvertWithoutOpenDeliveryStatusMapping(): void
    {
        $context = Context::createDefaultContext();

        $orderData = require __DIR__ . '/../../../_fixtures/order_data.php';
        $convertResult = $this->orderConverter->convert($orderData[0], $context, $this->migrationContext);

        static::assertNotNull($convertResult->getConverted());
        static::assertNull($convertResult->getUnmapped());
        static::assertArrayNotHasKey('deliveries', $convertResult->getConverted());
    }

    public function testConvertWithDelivery(): void
    {
        $context = Context::createDefaultContext();

        $orderData = require __DIR__ . '/../../../_fixtures/order_data.php';
        $convertResult = $this->orderConverter->convert($orderData[1], $context, $this->migrationContext);

        static::assertNotNull($convertResult->getConverted());
        static::assertNull($convertResult->getUnmapped());
        static::assertSame($this->shippedDeliveryState['entityUuid'], $convertResult->getConverted()['deliveries'][0]['stateId']);
        static::assertSame($this->shippingMethod, $convertResult->getConverted()['deliveries'][0]['shippingMethodId']);
    }

    public function testConvertWithoutShippedStatusMapping(): void
    {
        $context = Context::createDefaultContext();

        $this->mappingService->deleteMapping($this->shippedDeliveryState['entityUuid'], $this->connection->getId(), $context);
        $orderData = require __DIR__ . '/../../../_fixtures/order_data.php';
        $convertResult = $this->orderConverter->convert($orderData[1], $context, $this->migrationContext);

        static::assertNotNull($convertResult->getConverted());
        static::assertNull($convertResult->getUnmapped());
        static::assertEmpty($convertResult->getConverted()['deliveries']);
    }

    public function testConvertWithoutCustomer(): void
    {
        $orderData = require __DIR__ . '/../../../_fixtures/order_data.php';
        $order = $orderData[0];
        $order['orders']['customer_id'] = '5';

        $context = Context::createDefaultContext();
        $this->expectException(AssociationEntityRequiredMissingException::class);
        $this->expectExceptionMessage('Mapping of "customer" is missing, but it is a required association for "order". Import "customer" first.');
        $this->orderConverter->convert($order, $context, $this->migrationContext);
    }

    public function testConvertWithInvalidSalutation(): void
    {
        $context = Context::createDefaultContext();
        $this->mappingService->deleteMapping($this->defaultSalutation, $this->connection->getId(), $context);
        $orderData = require __DIR__ . '/../../../_fixtures/order_data.php';
        $order = $orderData[0];
        $order['orders']['customer_salutation'] = 'mrs';

        $convertResult = $this->orderConverter->convert($order, $context, $this->migrationContext);

        static::assertNotNull($convertResult->getUnmapped());
        static::assertNull($convertResult->getConverted());

        $logs = $this->loggingService->getLoggingArray();
        static::assertCount(1, $logs);

        static::assertSame('SWAG_MIGRATION_SALUTATION_ENTITY_UNKNOWN', $logs[0]['code']);
        static::assertSame($order['orders']['customer_salutation'], $logs[0]['parameters']['sourceId']);
        static::assertSame($order['orders']['entity_id'], $logs[0]['parameters']['requiredForSourceId']);

        $this->loggingService->resetLogging();
        unset($order['orders']['customer_salutation']);
        $convertResult = $this->orderConverter->convert($order, $context, $this->migrationContext);

        static::assertNotNull($convertResult->getUnmapped());
        static::assertNull($convertResult->getConverted());

        $logs = $this->loggingService->getLoggingArray();
        static::assertCount(1, $logs);

        static::assertSame('SWAG_MIGRATION_EMPTY_NECESSARY_FIELD_ORDER', $logs[0]['code']);
        static::assertSame($order['orders']['entity_id'], $logs[0]['parameters']['sourceId']);
        static::assertSame('salutation', $logs[0]['parameters']['emptyField']);
    }

    public function testConvertWithInvalidBillingAddress(): void
    {
        $context = Context::createDefaultContext();
        $orderData = require __DIR__ . '/../../../_fixtures/order_data.php';
        $order = $orderData[0];

        $order['billingAddress']['country_id'] = 'foobar';

        $convertResult = $this->orderConverter->convert($order, $context, $this->migrationContext);

        $logs = $this->loggingService->getLoggingArray();
        static::assertCount(2, $logs);

        static::assertNotNull($convertResult->getUnmapped());
        static::assertNull($convertResult->getConverted());

        static::assertSame('SWAG_MIGRATION_COUNTRY_ENTITY_UNKNOWN', $logs[0]['code']);
        static::assertSame($order['billingAddress']['country_id'], $logs[0]['parameters']['sourceId']);
        static::assertSame($order['orders']['entity_id'], $logs[0]['parameters']['requiredForSourceId']);

        static::assertSame('SWAG_MIGRATION_EMPTY_NECESSARY_FIELD_ORDER', $logs[1]['code']);
        static::assertSame($order['orders']['entity_id'], $logs[1]['parameters']['sourceId']);
        static::assertSame('billingAddress', $logs[1]['parameters']['emptyField']);
    }

    public function testConvertAsGuestCustomer(): void
    {
        $context = Context::createDefaultContext();
        $orderData = require __DIR__ . '/../../../_fixtures/order_data.php';
        $order = $orderData[0];

        $order['orders']['customer_is_guest'] = '1';
        unset($order['orders']['customer_lastname']);
        unset($order['orders']['customer_firstname']);

        $convertResult = $this->orderConverter->convert($order, $context, $this->migrationContext);

        $logs = $this->loggingService->getLoggingArray();
        static::assertCount(0, $logs);

        static::assertNull($convertResult->getUnmapped());
        static::assertNotNull($convertResult->getConverted());

        $converted = $convertResult->getConverted();
        static::assertSame($converted['orderCustomer']['firstName'], $order['billingAddress']['firstname']);
        static::assertSame($converted['orderCustomer']['lastName'], $order['billingAddress']['lastname']);
        static::assertSame($converted['orderCustomer']['customer']['firstName'], $order['billingAddress']['firstname']);
        static::assertSame($converted['orderCustomer']['customer']['lastName'], $order['billingAddress']['lastname']);
    }

    public function testConvertAsGuestCustomerWithoutShippingAddress(): void
    {
        $context = Context::createDefaultContext();
        $orderData = require __DIR__ . '/../../../_fixtures/order_data.php';
        $order = $orderData[0];

        $order['orders']['customer_is_guest'] = '1';
        unset($order['orders']['customer_lastname']);
        unset($order['orders']['customer_firstname']);
        $order['shippingAddress']['country_id'] = 'foobar';
        $order['billingAddress']['firstname'] = 'Foo';
        $order['billingAddress']['lastname'] = 'bar';

        $convertResult = $this->orderConverter->convert($order, $context, $this->migrationContext);

        $logs = $this->loggingService->getLoggingArray();
        static::assertCount(2, $logs);

        static::assertSame('SWAG_MIGRATION_COUNTRY_ENTITY_UNKNOWN', $logs[0]['code']);
        static::assertSame($order['shippingAddress']['country_id'], $logs[0]['parameters']['sourceId']);
        static::assertSame($order['orders']['entity_id'], $logs[0]['parameters']['requiredForSourceId']);

        static::assertSame('SWAG_MIGRATION_COUNTRY_ENTITY_UNKNOWN', $logs[1]['code']);
        static::assertSame($order['shippingAddress']['country_id'], $logs[1]['parameters']['sourceId']);
        static::assertSame($order['orders']['entity_id'], $logs[1]['parameters']['requiredForSourceId']);

        static::assertNull($convertResult->getUnmapped());
        static::assertNotNull($convertResult->getConverted());

        $converted = $convertResult->getConverted();
        static::assertSame($converted['orderCustomer']['firstName'], $order['billingAddress']['firstname']);
        static::assertSame($converted['orderCustomer']['lastName'], $order['billingAddress']['lastname']);
        static::assertSame($converted['orderCustomer']['customer']['firstName'], $order['billingAddress']['firstname']);
        static::assertSame($converted['orderCustomer']['customer']['lastName'], $order['billingAddress']['lastname']);
        static::assertSame($converted['orderCustomer']['customer']['defaultBillingAddressId'], $converted['orderCustomer']['customer']['defaultShippingAddressId']);
    }

    public function testConvertAsGuestCustomerWithInvalidBillingAddress(): void
    {
        $context = Context::createDefaultContext();
        $orderData = require __DIR__ . '/../../../_fixtures/order_data.php';
        $order = $orderData[0];

        $order['orders']['customer_is_guest'] = '1';
        unset($order['orders']['customer_lastname']);
        unset($order['orders']['customer_firstname']);
        $order['billingAddress']['country_id'] = 'foobar';

        $convertResult = $this->orderConverter->convert($order, $context, $this->migrationContext);

        $logs = $this->loggingService->getLoggingArray();
        static::assertCount(2, $logs);

        static::assertNotNull($convertResult->getUnmapped());
        static::assertNull($convertResult->getConverted());

        static::assertSame('SWAG_MIGRATION_COUNTRY_ENTITY_UNKNOWN', $logs[0]['code']);
        static::assertSame($order['billingAddress']['country_id'], $logs[0]['parameters']['sourceId']);
        static::assertSame($order['orders']['entity_id'], $logs[0]['parameters']['requiredForSourceId']);

        static::assertSame('SWAG_MIGRATION_EMPTY_NECESSARY_FIELD_ORDER', $logs[1]['code']);
        static::assertSame($order['orders']['entity_id'], $logs[1]['parameters']['sourceId']);
        static::assertSame('billingAddress', $logs[1]['parameters']['emptyField']);
    }

    public function testConvertAsGuestCustomerWithoutPaymentMethod(): void
    {
        $context = Context::createDefaultContext();
        $orderData = require __DIR__ . '/../../../_fixtures/order_data.php';
        $order = $orderData[0];

        $order['orders']['customer_is_guest'] = '1';
        unset($order['orders']['customer_lastname']);
        unset($order['orders']['customer_firstname']);
        unset($order['orders']['payment']['method']);

        $convertResult = $this->orderConverter->convert($order, $context, $this->migrationContext);

        $logs = $this->loggingService->getLoggingArray();
        static::assertCount(1, $logs);

        static::assertNotNull($convertResult->getUnmapped());
        static::assertNull($convertResult->getConverted());

        static::assertSame('SWAG_MIGRATION_EMPTY_NECESSARY_FIELD_ORDER', $logs[0]['code']);
        static::assertSame($order['orders']['entity_id'], $logs[0]['parameters']['sourceId']);
        static::assertSame('payment_method', $logs[0]['parameters']['emptyField']);
    }

    public function testConvertAsGuestCustomerWithWithInvalidLanguage(): void
    {
        $context = Context::createDefaultContext();
        $this->mappingService->deleteMapping($this->language, $this->connection->getId(), $context);
        $orderData = require __DIR__ . '/../../../_fixtures/order_data.php';
        $order = $orderData[0];

        $order['orders']['customer_is_guest'] = '1';
        unset($order['orders']['customer_lastname']);
        unset($order['orders']['customer_firstname']);

        $convertResult = $this->orderConverter->convert($order, $context, $this->migrationContext);

        $logs = $this->loggingService->getLoggingArray();
        static::assertCount(1, $logs);

        static::assertNotNull($convertResult->getUnmapped());
        static::assertNull($convertResult->getConverted());

        static::assertSame('SWAG_MIGRATION_EMPTY_NECESSARY_FIELD_ORDER', $logs[0]['code']);
        static::assertSame($order['orders']['entity_id'], $logs[0]['parameters']['sourceId']);
        static::assertSame('language', $logs[0]['parameters']['emptyField']);
    }

    public function testConvertAsGuestCustomerWithWithInvalidCustomerGroup(): void
    {
        $context = Context::createDefaultContext();
        $this->mappingService->deleteMapping($this->customerGroup, $this->connection->getId(), $context);
        $orderData = require __DIR__ . '/../../../_fixtures/order_data.php';
        $order = $orderData[0];

        $order['orders']['customer_is_guest'] = '1';
        unset($order['orders']['customer_lastname']);
        unset($order['orders']['customer_firstname']);

        $convertResult = $this->orderConverter->convert($order, $context, $this->migrationContext);

        $logs = $this->loggingService->getLoggingArray();
        static::assertCount(1, $logs);

        static::assertNotNull($convertResult->getUnmapped());
        static::assertNull($convertResult->getConverted());

        static::assertSame('SWAG_MIGRATION_EMPTY_NECESSARY_FIELD_ORDER', $logs[0]['code']);
        static::assertSame($order['orders']['entity_id'], $logs[0]['parameters']['sourceId']);
        static::assertSame('customer_group_id', $logs[0]['parameters']['emptyField']);
    }

    public function testConvertWithoutSalutation(): void
    {
        $orderData = require __DIR__ . '/../../../_fixtures/order_data.php';
        $order = $orderData[0];
        unset($order['orders']['customer_salutation']);

        $context = Context::createDefaultContext();
        $convertResult = $this->orderConverter->convert($order, $context, $this->migrationContext);

        $converted = $convertResult->getConverted();

        static::assertNotNull($converted);
        static::assertNull($convertResult->getUnmapped());
        static::assertArrayHasKey('id', $converted);
        static::assertNotNull($convertResult->getMappingUuid());
    }

    public function testConvertWithInvalidCurrency(): void
    {
        $orderData = require __DIR__ . '/../../../_fixtures/order_data.php';
        $order = $orderData[0];
        $order['orders']['order_currency_code'] = 'JPY';

        $context = Context::createDefaultContext();
        $convertResult = $this->orderConverter->convert($order, $context, $this->migrationContext);

        static::assertNotNull($convertResult->getUnmapped());
        static::assertNull($convertResult->getConverted());

        $logs = $this->loggingService->getLoggingArray();
        static::assertCount(1, $logs);

        static::assertSame($logs[0]['code'], 'SWAG_MIGRATION_EMPTY_NECESSARY_FIELD_ORDER');
        static::assertSame($logs[0]['parameters']['sourceId'], $order['orders']['entity_id']);
        static::assertSame($logs[0]['parameters']['emptyField'], 'currency');
    }

    public function testConvertWithInvalidOrderState(): void
    {
        $orderData = require __DIR__ . '/../../../_fixtures/order_data.php';
        $order = $orderData[0];
        $order['orders']['status'] = 'invalid';

        $context = Context::createDefaultContext();
        $convertResult = $this->orderConverter->convert($order, $context, $this->migrationContext);

        static::assertNotNull($convertResult->getUnmapped());
        static::assertNull($convertResult->getConverted());

        $logs = $this->loggingService->getLoggingArray();
        static::assertCount(1, $logs);

        static::assertSame($logs[0]['code'], 'SWAG_MIGRATION_ORDER_STATE_ENTITY_UNKNOWN');
        static::assertSame($logs[0]['parameters']['sourceId'], $order['orders']['status']);
    }

    public function requiredProperties(): array
    {
        return [
            ['orders', null],
            ['orders', ''],
            ['billingAddress', null],
            ['billingAddress', ''],
            ['shippingAddress', null],
            ['shippingAddress', ''],
            ['items', null],
            ['items', ''],
        ];
    }

    /**
     * @dataProvider requiredProperties
     */
    public function testConvertWithoutRequiredProperties(string $property, ?string $value): void
    {
        $orderData = require __DIR__ . '/../../../_fixtures/order_data.php';
        $orderData = $orderData[0];
        $orderData[$property] = $value;

        $context = Context::createDefaultContext();
        $convertResult = $this->orderConverter->convert($orderData, $context, $this->migrationContext);
        static::assertNull($convertResult->getConverted());

        $logs = $this->loggingService->getLoggingArray();
        static::assertCount(1, $logs);

        static::assertSame($logs[0]['code'], 'SWAG_MIGRATION_EMPTY_NECESSARY_FIELD_ORDER');

        if ($property === 'orders') {
            static::assertSame($logs[0]['parameters']['emptyField'], 'orders,entity_id');
        } else {
            static::assertSame($logs[0]['parameters']['emptyField'], $property);
        }
    }
}
