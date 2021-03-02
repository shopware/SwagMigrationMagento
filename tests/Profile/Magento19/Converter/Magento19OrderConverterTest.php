<?php declare(strict_types=1);
/*
 * (c) shopware AG <info@shopware.com>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Swag\MigrationMagento\Test\Profile\Magento\Converter;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Tax\TaxCalculator;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\NumberRange\ValueGenerator\NumberRangeValueGeneratorInterface;
use Swag\MigrationMagento\Profile\Magento\DataSelection\DataSet\OrderDataSet;
use Swag\MigrationMagento\Profile\Magento\DataSelection\DefaultEntities as MagentoDefaultEntities;
use Swag\MigrationMagento\Profile\Magento19\Converter\Magento19OrderConverter;
use Swag\MigrationMagento\Profile\Magento19\Magento19Profile;
use Swag\MigrationMagento\Profile\Magento19\Premapping\Magento19OrderStateReader;
use Swag\MigrationMagento\Test\Mock\Migration\Mapping\DummyMagentoMappingService;
use SwagMigrationAssistant\Migration\Connection\SwagMigrationConnectionEntity;
use SwagMigrationAssistant\Migration\DataSelection\DefaultEntities;
use SwagMigrationAssistant\Migration\MigrationContext;
use SwagMigrationAssistant\Migration\MigrationContextInterface;
use SwagMigrationAssistant\Profile\Shopware\Exception\AssociationEntityRequiredMissingException;
use SwagMigrationAssistant\Test\Mock\Migration\Logging\DummyLoggingService;

class Magento19OrderConverterTest extends TestCase
{
    use KernelTestBehaviour;
    use DatabaseTransactionBehaviour;

    /**
     * @var Magento19OrderConverter
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

    /**
     * @var string
     */
    private $connection;

    /**
     * @var MigrationContextInterface
     */
    private $migrationContext;

    private $mappingService;

    /**
     * @var string
     */
    private $defaultSalutation;

    /**
     * @var array
     */
    private $billingAddressId;

    /**
     * @var string
     */
    private $shippingAddressId;

    protected function setUp(): void
    {
        $this->mappingService = new DummyMagentoMappingService();
        $this->loggingService = new DummyLoggingService();

        $taxCalculator = new TaxCalculator();
        $this->orderConverter = new Magento19OrderConverter($this->mappingService, $this->loggingService, $taxCalculator, $this->getContainer()->get(NumberRangeValueGeneratorInterface::class));

        $this->runId = Uuid::randomHex();
        $this->connection = new SwagMigrationConnectionEntity();
        $this->connection->setId(Uuid::randomHex());
        $this->connection->setProfileName(Magento19Profile::PROFILE_NAME);
        $this->connection->setName('shopware');

        $this->migrationContext = new MigrationContext(
            new Magento19Profile(),
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

        static::assertNull($convertResult->getUnmapped());
        static::assertArrayHasKey('id', $converted);
        static::assertNotNull($convertResult->getMappingUuid());
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

        static::assertSame($logs[0]['code'], 'SWAG_MIGRATION_SALUTATION_ENTITY_UNKNOWN');
        static::assertSame($logs[0]['parameters']['sourceId'], $order['orders']['customer_salutation']);
        static::assertSame($logs[0]['parameters']['requiredForSourceId'], $order['orders']['entity_id']);
    }

    public function testConvertWithoutSalutation(): void
    {
        $orderData = require __DIR__ . '/../../../_fixtures/order_data.php';
        $order = $orderData[0];
        unset($order['orders']['customer_salutation']);

        $context = Context::createDefaultContext();
        $convertResult = $this->orderConverter->convert($order, $context, $this->migrationContext);

        $converted = $convertResult->getConverted();

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

    public function testConvertWithRepeatedGuestMigration(): void
    {
        $orderData = require __DIR__ . '/../../../_fixtures/order_data.php';

        $context = Context::createDefaultContext();

        $orderData[0]['orders']['customer_id'] = null;
        $orderData[0]['orders']['customer_is_guest'] = '1';
        $orderData[0]['orders']['customer_group_id'] = '0';

        $convertResult = $this->orderConverter->convert($orderData[0], $context, $this->migrationContext);
        $converted = $convertResult->getConverted();

        static::assertArrayHasKey('customerId', $converted['orderCustomer']);
        static::assertCount(2, $converted['orderCustomer']['customer']['addresses']);
        static::assertSame($this->billingAddressId, $converted['orderCustomer']['customer']['defaultBillingAddressId']);
        static::assertSame($this->shippingAddressId, $converted['orderCustomer']['customer']['defaultShippingAddressId']);

        $secondConvertResult = $this->orderConverter->convert($orderData[0], $context, $this->migrationContext);
        $convertedSecond = $secondConvertResult->getConverted();

        static::assertArrayHasKey('customerId', $convertedSecond['orderCustomer']);
        static::assertSame($converted['orderCustomer']['customerId'], $convertedSecond['orderCustomer']['customerId']);
        static::assertCount(2, $converted['orderCustomer']['customer']['addresses']);
        static::assertSame($this->billingAddressId, $convertedSecond['orderCustomer']['customer']['defaultBillingAddressId']);
        static::assertSame($this->shippingAddressId, $convertedSecond['orderCustomer']['customer']['defaultShippingAddressId']);
    }
}
