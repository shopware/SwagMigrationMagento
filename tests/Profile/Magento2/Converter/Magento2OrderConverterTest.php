<?php declare(strict_types=1);
/*
 * (c) shopware AG <info@shopware.com>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Swag\MigrationMagento\Test\Profile\Magento2\Converter;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Cart\Tax\TaxCalculator;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
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
use SwagMigrationAssistant\Migration\Logging\Log\ConvertAssociationMissingLog;
use SwagMigrationAssistant\Migration\Mapping\Lookup\CountryLookup;
use SwagMigrationAssistant\Migration\Mapping\Lookup\CountryStateLookup;
use SwagMigrationAssistant\Migration\Mapping\Lookup\CurrencyLookup;
use SwagMigrationAssistant\Migration\Mapping\Lookup\LanguageLookup;
use SwagMigrationAssistant\Migration\Mapping\Lookup\StateMachineStateLookup;
use SwagMigrationAssistant\Migration\MigrationContext;
use SwagMigrationAssistant\Migration\MigrationContextInterface;
use SwagMigrationAssistant\Profile\Shopware\Premapping\OrderDeliveryStateReader;
use SwagMigrationAssistant\Test\Mock\Migration\Logging\DummyLoggingService;

/**
 * @internal
 */
#[Package('fundamentals@after-sales')]
class Magento2OrderConverterTest extends TestCase
{
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;

    private Magento23OrderConverter $orderConverter;

    private DummyLoggingService $loggingService;

    private string $runId;

    private SwagMigrationConnectionEntity $connection;

    private MigrationContextInterface $migrationContext;

    private DummyMagentoMappingService $mappingService;

    private string $defaultSalutation;

    private string $language;

    private string $customerGroup;

    private string $shippingMethod;

    private array $shippedDeliveryState;

    private string $storeUuid;

    protected function setUp(): void
    {
        $this->mappingService = new DummyMagentoMappingService();
        $this->loggingService = new DummyLoggingService();
        $taxCalculator = new TaxCalculator();

        $this->orderConverter = new Magento23OrderConverter(
            $this->mappingService,
            $this->loggingService,
            $taxCalculator,
            $this->getContainer()->get(NumberRangeValueGeneratorInterface::class),
            $this->getContainer()->get(CountryLookup::class),
            $this->getContainer()->get(CurrencyLookup::class),
            $this->getContainer()->get(CountryStateLookup::class),
            $this->getContainer()->get(StateMachineStateLookup::class),
            $this->getContainer()->get(LanguageLookup::class),
        );

        $this->runId = Uuid::randomHex();
        $this->connection = new SwagMigrationConnectionEntity();
        $this->connection->setId(Uuid::randomHex());
        $this->connection->setProfileName(Magento23Profile::PROFILE_NAME);
        $this->connection->setName('shopware');

        $this->migrationContext = new MigrationContext($this->connection, new Magento23Profile(), null, new OrderDataSet(), $this->runId, 0, 250);

        $context = Context::createDefaultContext();
        $this->storeUuid = Uuid::randomHex();
        $this->mappingService->getOrCreateMapping(
            $this->connection->getId(),
            MagentoDefaultEntities::STORE,
            '1',
            $context,
            null,
            null,
            $this->storeUuid
        );

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
        static::assertSame(1.0, $converted['currencyFactor']);
        static::assertNotNull($convertResult->getMappingUuid());
        static::assertSame($this->storeUuid, $converted['salesChannelId']);
        $price = $converted['price'];
        static::assertInstanceOf(CartPrice::class, $price);
        static::assertSame(\round((float) $orderData[0]['orders']['subtotal'] + (float) $orderData[0]['orders']['shipping_amount'], 2), $price->getNetPrice());
        static::assertSame(\round((float) $orderData[0]['orders']['grand_total'], 2), $price->getTotalPrice());
        static::assertSame($deliveryStateMapping['entityId'], $converted['deliveries'][0]['stateId']);
        static::assertSame($this->shippingMethod, $converted['deliveries'][0]['shippingMethodId']);
        static::assertNotNull($converted['itemRounding']);
        static::assertNotNull($converted['totalRounding']);
    }

    public function testConvertWithZeroCurrencyFactor(): void
    {
        $context = Context::createDefaultContext();
        $orderData = require __DIR__ . '/../../../_fixtures/order_data.php';
        $orderData[0]['orders']['store_to_order_rate'] = '0.0000';

        $convertResult = $this->orderConverter->convert($orderData[0], $context, $this->migrationContext);
        $converted = $convertResult->getConverted();

        static::assertNotNull($converted);
        static::assertSame(1.0, $converted['currencyFactor']);
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

        static::assertCount(0, $logs);
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
        static::assertSame($this->shippedDeliveryState['entityId'], $convertResult->getConverted()['deliveries'][0]['stateId']);
        static::assertSame($this->shippingMethod, $convertResult->getConverted()['deliveries'][0]['shippingMethodId']);
    }

    public function testConvertWithoutCustomer(): void
    {
        $orderData = require __DIR__ . '/../../../_fixtures/order_data.php';
        $order = $orderData[0];
        $order['orders']['customer_id'] = '5';

        $context = Context::createDefaultContext();

        $convertResult = $this->orderConverter->convert($order, $context, $this->migrationContext);
        $converted = $convertResult->getConverted();
        $logs = $this->loggingService->getLoggingArray();

        static::assertNotNull($convertResult->getUnmapped());
        static::assertNull($converted);
        static::assertCount(1, $logs);
        static::assertSame(ConvertAssociationMissingLog::getCode(), $logs[0]['code']);
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
        static::assertCount(0, $logs);

        static::assertNull($convertResult->getUnmapped());
        static::assertNotNull($convertResult->getConverted());
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
        static::assertCount(0, $logs);

        static::assertNull($convertResult->getUnmapped());
        static::assertNotNull($convertResult->getConverted());
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
        static::assertCount(0, $logs);

        static::assertNull($convertResult->getUnmapped());
        static::assertNotNull($convertResult->getConverted());
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
}
