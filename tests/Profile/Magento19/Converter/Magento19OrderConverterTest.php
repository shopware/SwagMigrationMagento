<?php declare(strict_types=1);
/*
 * (c) shopware AG <info@shopware.com>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Swag\MigrationMagento\Test\Profile\Magento19\Converter;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
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
use Swag\MigrationMagento\Profile\Magento19\Converter\Magento19OrderConverter;
use Swag\MigrationMagento\Profile\Magento19\Magento19Profile;
use Swag\MigrationMagento\Profile\Magento19\Premapping\Magento19OrderStateReader;
use Swag\MigrationMagento\Test\LookupHelperTrait;
use Swag\MigrationMagento\Test\Mock\Migration\Mapping\DummyMagentoMappingService;
use SwagMigrationAssistant\Exception\AssociationEntityRequiredMissingException;
use SwagMigrationAssistant\Migration\Connection\SwagMigrationConnectionEntity;
use SwagMigrationAssistant\Migration\DataSelection\DefaultEntities;
use SwagMigrationAssistant\Migration\Mapping\Lookup\CountryLookup;
use SwagMigrationAssistant\Migration\Mapping\Lookup\CountryStateLookup;
use SwagMigrationAssistant\Migration\Mapping\Lookup\CurrencyLookup;
use SwagMigrationAssistant\Migration\Mapping\Lookup\StateMachineStateLookup;
use SwagMigrationAssistant\Migration\MigrationContext;
use SwagMigrationAssistant\Migration\MigrationContextInterface;
use SwagMigrationAssistant\Profile\Shopware\Premapping\OrderDeliveryStateReader;
use SwagMigrationAssistant\Test\Mock\Migration\Logging\DummyLoggingService;

/**
 * @internal
 */
#[Package('fundamentals@after-sales')]
class Magento19OrderConverterTest extends TestCase
{
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;
    use LookupHelperTrait;

    private Magento19OrderConverter $orderConverter;

    private DummyLoggingService $loggingService;

    private string $runId;

    private SwagMigrationConnectionEntity $connection;

    private MigrationContextInterface $migrationContext;

    private DummyMagentoMappingService $mappingService;

    private string $defaultSalutation;

    private string $billingAddressId;

    private string $shippingAddressId;

    private string $shippingMethodId;

    private string $storeUuid;

    private string $countryStateMappingUuid;

    protected function setUp(): void
    {
        $this->mappingService = new DummyMagentoMappingService();
        $this->loggingService = new DummyLoggingService();

        $taxCalculator = new TaxCalculator();
        $this->orderConverter = new Magento19OrderConverter(
            $this->mappingService,
            $this->loggingService,
            $taxCalculator,
            $this->getContainer()->get(NumberRangeValueGeneratorInterface::class),
            $this->getContainer()->get(CountryLookup::class),
            $this->getContainer()->get(CurrencyLookup::class),
            $this->getContainer()->get(CountryStateLookup::class),
            $this->getContainer()->get(StateMachineStateLookup::class),
        );

        $this->runId = Uuid::randomHex();
        $this->connection = new SwagMigrationConnectionEntity();
        $this->connection->setId(Uuid::randomHex());
        $this->connection->setProfileName(Magento19Profile::PROFILE_NAME);
        $this->connection->setName('shopware');

        $this->migrationContext = new MigrationContext(
            $this->connection,
            new Magento19Profile(),
            null,
            new OrderDataSet(),
            $this->runId,
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
            Magento19OrderStateReader::getMappingName(),
            'pending',
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
            DefaultEntities::COUNTRY,
            'US',
            $context,
            null,
            null,
            Uuid::randomHex()
        );

        $customerGroupUuid = Uuid::randomHex();
        $this->mappingService->getOrCreateMapping(
            $this->connection->getId(),
            DefaultEntities::CUSTOMER_GROUP,
            '0',
            $context,
            null,
            null,
            $customerGroupUuid
        );

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
            MagentoDefaultEntities::STORE_LANGUAGE,
            '1',
            $context
        );

        $this->billingAddressId = Uuid::randomHex();
        $this->mappingService->getOrCreateMapping(
            $this->connection->getId(),
            DefaultEntities::CUSTOMER_ADDRESS,
            '83_guest',
            $context,
            null,
            null,
            $this->billingAddressId
        );

        $this->shippingAddressId = Uuid::randomHex();
        $this->mappingService->getOrCreateMapping(
            $this->connection->getId(),
            DefaultEntities::CUSTOMER_ADDRESS,
            '84_guest',
            $context,
            null,
            null,
            $this->shippingAddressId
        );

        $this->shippingMethodId = Uuid::randomHex();
        $this->mappingService->getOrCreateMapping(
            $this->connection->getId(),
            DefaultEntities::SHIPPING_METHOD,
            'ups',
            $context,
            null,
            null,
            $this->shippingMethodId
        );

        $this->mappingService->getOrCreateMapping(
            $this->connection->getId(),
            OrderDeliveryStateReader::getMappingName(),
            MagentoOrderDeliveryStateReader::DEFAULT_OPEN_STATUS,
            $context,
            null,
            null,
            Uuid::randomHex()
        );

        $countryStateMappingUuid = $this->getCountryStateIdByCode('US-MA');
        static::assertNotNull($countryStateMappingUuid);
        $this->countryStateMappingUuid = $countryStateMappingUuid;

        $this->mappingService->getOrCreateMapping(
            $this->connection->getId(),
            DefaultEntities::COUNTRY_STATE,
            '32',
            $context,
            null,
            null,
            $this->countryStateMappingUuid
        );
    }

    public function testSupports(): void
    {
        $supportsDefinition = $this->orderConverter->supports($this->migrationContext);

        static::assertTrue($supportsDefinition);
    }

    public function testConvert(): void
    {
        $orderData = require __DIR__ . '/../../../_fixtures/order_data.php';

        $context = Context::createDefaultContext();
        $convertResult = $this->orderConverter->convert($orderData[0], $context, $this->migrationContext);

        $converted = $convertResult->getConverted();

        static::assertNotNull($converted);
        static::assertNull($convertResult->getUnmapped());
        static::assertArrayHasKey('id', $converted);
        static::assertSame($this->storeUuid, $converted['salesChannelId']);
        static::assertNotNull($convertResult->getMappingUuid());
    }

    public function testConvertWithShippingMethod(): void
    {
        $orderData = require __DIR__ . '/../../../_fixtures/order_data.php';
        $order = $orderData[0];

        $context = Context::createDefaultContext();
        $convertResult = $this->orderConverter->convert($order, $context, $this->migrationContext);

        $converted = $convertResult->getConverted();
        static::assertNull($convertResult->getUnmapped());
        static::assertNotNull($converted);
        static::assertArrayHasKey('deliveries', $converted);
        static::assertArrayHasKey('shippingMethodId', $converted['deliveries'][0]);
        static::assertSame($this->shippingMethodId, $converted['deliveries'][0]['shippingMethodId']);
    }

    public function testConvertWithoutShippingMethod(): void
    {
        $orderData = require __DIR__ . '/../../../_fixtures/order_data.php';
        $order = $orderData[0];
        $order['orders']['shipping_method'] = null;

        $context = Context::createDefaultContext();
        $convertResult = $this->orderConverter->convert($order, $context, $this->migrationContext);

        $converted = $convertResult->getConverted();
        static::assertNull($convertResult->getUnmapped());
        static::assertNotNull($converted);
        static::assertArrayNotHasKey('deliveries', $converted);
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

        static::assertSame($logs[0]['code'], 'SWAG_MIGRATION_ENTITY_UNKNOWN');
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

        static::assertSame($logs[0]['code'], 'SWAG_MIGRATION_EMPTY_NECESSARY_FIELD');
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

        static::assertSame($logs[0]['code'], 'SWAG_MIGRATION_ENTITY_UNKNOWN');
    }

    public static function requiredProperties(): array
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

    #[DataProvider('requiredProperties')]
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

        static::assertSame($logs[0]['code'], 'SWAG_MIGRATION_EMPTY_NECESSARY_FIELD');
    }

    public function testConvertWithRepeatedGuestMigration(): void
    {
        $orderData = require __DIR__ . '/../../../_fixtures/order_data.php';

        $context = Context::createDefaultContext();

        $orderData[0]['orders']['customer_id'] = null;
        $orderData[0]['orders']['customer_is_guest'] = '1';
        $orderData[0]['orders']['customer_group_id'] = '0';

        $convertResult = $this->orderConverter->convert($orderData[0], $context, $this->migrationContext);
        $converted = $convertResult->getConverted();

        static::assertNotNull($converted);
        static::assertArrayHasKey('customerId', $converted['orderCustomer']);
        static::assertCount(2, $converted['orderCustomer']['customer']['addresses']);
        static::assertSame($this->billingAddressId, $converted['orderCustomer']['customer']['defaultBillingAddressId']);
        static::assertSame($this->shippingAddressId, $converted['orderCustomer']['customer']['defaultShippingAddressId']);

        $secondConvertResult = $this->orderConverter->convert($orderData[0], $context, $this->migrationContext);
        $convertedSecond = $secondConvertResult->getConverted();

        static::assertNotNull($convertedSecond);
        static::assertArrayHasKey('customerId', $convertedSecond['orderCustomer']);
        static::assertSame($converted['orderCustomer']['customerId'], $convertedSecond['orderCustomer']['customerId']);
        static::assertCount(2, $converted['orderCustomer']['customer']['addresses']);
        static::assertSame($this->billingAddressId, $convertedSecond['orderCustomer']['customer']['defaultBillingAddressId']);
        static::assertSame($this->shippingAddressId, $convertedSecond['orderCustomer']['customer']['defaultShippingAddressId']);
    }

    public function testConvertCountryStateWithMapping(): void
    {
        $orderData = require __DIR__ . '/../../../_fixtures/order_data.php';
        $orderData = $orderData[0];

        $context = Context::createDefaultContext();
        $convertResult = $this->orderConverter->convert(
            $orderData,
            $context,
            $this->migrationContext
        );

        $converted = $convertResult->getConverted();

        static::assertNotNull($converted);
        static::assertArrayHasKey('id', $converted);

        static::assertArrayHasKey('addresses', $converted);
        static::assertArrayHasKey('countryStateId', $converted['addresses'][0]);
        static::assertSame($this->countryStateMappingUuid, $converted['addresses'][0]['countryStateId']);

        static::assertArrayHasKey('deliveries', $converted);
        static::assertArrayHasKey('shippingOrderAddress', $converted['deliveries'][0]);
        static::assertArrayHasKey('countryStateId', $converted['deliveries'][0]['shippingOrderAddress']);
        static::assertSame($this->countryStateMappingUuid, $converted['deliveries'][0]['shippingOrderAddress']['countryStateId']);
    }

    public function testConvertExistingCountryStateWithoutMapping(): void
    {
        $orderData = require __DIR__ . '/../../../_fixtures/order_data.php';
        $orderData = $orderData[0];
        $orderData['billingAddress']['region_id'] = '9999';
        $orderData['billingAddress']['region_code'] = 'XY';

        $context = Context::createDefaultContext();
        $convertResult = $this->orderConverter->convert(
            $orderData,
            $context,
            $this->migrationContext
        );

        $converted = $convertResult->getConverted();

        static::assertNotNull($converted);
        static::assertArrayHasKey('id', $converted);
        static::assertArrayHasKey('addresses', $converted);
        static::assertArrayHasKey('countryState', $converted['addresses'][0]);
        static::assertArrayHasKey('id', $converted['addresses'][0]['countryState']);
        static::assertSame('Massachusetts', $converted['addresses'][0]['countryState']['name']);
        static::assertSame('XY', $converted['addresses'][0]['countryState']['shortCode']);
    }

    public function testConvertNotExistingCountryStateWithoutMapping(): void
    {
        $orderData = require __DIR__ . '/../../../_fixtures/order_data.php';
        $orderData = $orderData[0];
        $orderData['billingAddress']['region_id'] = '9999';
        $orderData['billingAddress']['region_code'] = 'XY';
        $orderData['billingAddress']['region'] = null;

        $context = Context::createDefaultContext();
        $convertResult = $this->orderConverter->convert(
            $orderData,
            $context,
            $this->migrationContext
        );

        $converted = $convertResult->getConverted();

        static::assertNotNull($converted);
        static::assertArrayHasKey('id', $converted);
        static::assertArrayHasKey('addresses', $converted);
        static::assertArrayNotHasKey('countryStateId', $converted['addresses'][0]);

        $logs = $this->loggingService->getLoggingArray();

        static::assertCount(1, $logs);

        static::assertSame($logs[0]['code'], 'SWAG_MIGRATION_ENTITY_UNKNOWN');
    }
}
