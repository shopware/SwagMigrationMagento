<?php declare(strict_types=1);
/*
 * (c) shopware AG <info@shopware.com>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Swag\MigrationMagento\Test\Profile\Magento\Converter;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Uuid\Uuid;
use Swag\MigrationMagento\Profile\Magento\DataSelection\DataSet\SalesChannelDataSet;
use Swag\MigrationMagento\Profile\Magento19\Converter\Magento19SalesChannelConverter;
use Swag\MigrationMagento\Profile\Magento19\Magento19Profile;
use Swag\MigrationMagento\Test\Mock\Migration\Mapping\DummyMagentoMappingService;
use SwagMigrationAssistant\Migration\Connection\SwagMigrationConnectionEntity;
use SwagMigrationAssistant\Migration\DataSelection\DefaultEntities;
use SwagMigrationAssistant\Migration\MigrationContext;
use SwagMigrationAssistant\Migration\MigrationContextInterface;
use SwagMigrationAssistant\Test\Mock\Migration\Logging\DummyLoggingService;

class Magento19SalesChannelConverterTest extends TestCase
{
    /**
     * @var Magento19SalesChannelConverter
     */
    private $salesChannelConverter;

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

    /**
     * @var DummyMagentoMappingService
     */
    private $mappingService;

    /**
     * @var string
     */
    private $defaultShippingMethodId;

    /**
     * @var string
     */
    private $defaultPaymentMethodId;

    protected function setUp(): void
    {
        $this->mappingService = new DummyMagentoMappingService();
        $this->loggingService = new DummyLoggingService();
        $this->salesChannelConverter = new Magento19SalesChannelConverter($this->mappingService, $this->loggingService);

        $this->runId = Uuid::randomHex();
        $this->connection = new SwagMigrationConnectionEntity();
        $this->connection->setId(Uuid::randomHex());
        $this->connection->setProfileName(Magento19Profile::PROFILE_NAME);
        $this->connection->setName('shopware');

        $this->migrationContext = new MigrationContext(
            new Magento19Profile(),
            $this->connection,
            $this->runId,
            new SalesChannelDataSet(),
            0,
            250
        );

        $this->defaultPaymentMethodId = Uuid::randomHex();
        $this->defaultShippingMethodId = Uuid::randomHex();

        $context = Context::createDefaultContext();
        $this->mappingService->getOrCreateMapping($this->connection->getId(), DefaultEntities::LANGUAGE, 'de-DE', $context, null, null, Uuid::randomHex());
        $this->mappingService->getOrCreateMapping($this->connection->getId(), DefaultEntities::CURRENCY, 'EUR', $context, null, null, Uuid::randomHex());
        $this->mappingService->getOrCreateMapping($this->connection->getId(), DefaultEntities::CATEGORY, '2', $context, null, null, Uuid::randomHex());
        $this->mappingService->getOrCreateMapping($this->connection->getId(), DefaultEntities::COUNTRY, 'US', $context, null, null, Uuid::randomHex());
        $this->mappingService->getOrCreateMapping($this->connection->getId(), DefaultEntities::PAYMENT_METHOD, 'cashondelivery', $context, null, null, Uuid::randomHex());
        $this->mappingService->getOrCreateMapping($this->connection->getId(), DefaultEntities::PAYMENT_METHOD, 'free', $context, null, null, Uuid::randomHex());
        $this->mappingService->getOrCreateMapping($this->connection->getId(), DefaultEntities::PAYMENT_METHOD, 'paypal_standard', $context, null, null, Uuid::randomHex());
        $this->mappingService->getOrCreateMapping($this->connection->getId(), DefaultEntities::PAYMENT_METHOD, 'default_payment_method', $context, null, null, $this->defaultPaymentMethodId);
        $this->mappingService->getOrCreateMapping($this->connection->getId(), DefaultEntities::SHIPPING_METHOD, 'dhlint', $context, null, null, Uuid::randomHex());
        $this->mappingService->getOrCreateMapping($this->connection->getId(), DefaultEntities::SHIPPING_METHOD, 'fedex', $context, null, null, Uuid::randomHex());
        $this->mappingService->getOrCreateMapping($this->connection->getId(), DefaultEntities::SHIPPING_METHOD, 'freeshipping', $context, null, null, Uuid::randomHex());
        $this->mappingService->getOrCreateMapping($this->connection->getId(), DefaultEntities::SHIPPING_METHOD, 'ups', $context, null, null, Uuid::randomHex());
        $this->mappingService->getOrCreateMapping($this->connection->getId(), DefaultEntities::SHIPPING_METHOD, 'usps', $context, null, null, Uuid::randomHex());
        $this->mappingService->getOrCreateMapping($this->connection->getId(), DefaultEntities::SHIPPING_METHOD, 'default_shipping_method', $context, null, null, $this->defaultShippingMethodId);
        $this->mappingService->getOrCreateMapping($this->connection->getId(), DefaultEntities::CUSTOMER_GROUP, 'default_customer_group', $context, null, null, Uuid::randomHex());
    }

    public function testSupports(): void
    {
        $supportsDefinition = $this->salesChannelConverter->supports($this->migrationContext);

        static::assertTrue($supportsDefinition);
    }

    public function testConvert(): void
    {
        $salesChannelData = require __DIR__ . '/../../../_fixtures/sales_channel_data.php';

        $context = Context::createDefaultContext();
        $convertResult = $this->salesChannelConverter->convert($salesChannelData[0], $context, $this->migrationContext);

        $converted = $convertResult->getConverted();

        $salesChannelMapping = $this->mappingService->getMapping(
            $this->connection->getId(),
            DefaultEntities::SALES_CHANNEL,
            $salesChannelData[0]['group_id'],
            $context
        );

        static::assertNull($convertResult->getUnmapped());
        static::assertArrayHasKey('id', $converted);
        static::assertCount(3, $converted['paymentMethods']);
        static::assertCount(5, $converted['shippingMethods']);
        static::assertNotNull($convertResult->getMappingUuid());
        static::assertNotNull($salesChannelMapping);
        static::assertSame($salesChannelMapping['entityUuid'], $converted['id']);
    }

    public function testConvertWithoutDefaultLanguage(): void
    {
        $salesChannelData = require __DIR__ . '/../../../_fixtures/sales_channel_data.php';

        $this->mappingService->deleteDummyMapping(DefaultEntities::LANGUAGE, 'de-DE');

        $context = Context::createDefaultContext();
        $convertResult = $this->salesChannelConverter->convert($salesChannelData[0], $context, $this->migrationContext);
        $converted = $convertResult->getConverted();

        static::assertNull($convertResult->getUnmapped());
        static::assertNotNull($converted);

        $logs = $this->loggingService->getLoggingArray();
        static::assertCount(1, $logs);

        static::assertSame('SWAG_MIGRATION_SALES_CHANNEL_ENTITY_FIELD_REASSIGNED', $logs[0]['code']);
        static::assertSame($salesChannelData[0]['group_id'], $logs[0]['parameters']['sourceId']);
        static::assertSame('defaultLocale', $logs[0]['parameters']['emptyField']);
        static::assertSame('system default language', $logs[0]['parameters']['replacementField']);
        static::assertSame($this->mappingService::DEFAULT_LANGUAGE_UUID, $converted['languageId']);
    }

    public function testConvertWithoutDefaultCurrency(): void
    {
        $salesChannelData = require __DIR__ . '/../../../_fixtures/sales_channel_data.php';

        $this->mappingService->deleteDummyMapping(DefaultEntities::CURRENCY, 'EUR');

        $context = Context::createDefaultContext();
        $convertResult = $this->salesChannelConverter->convert($salesChannelData[0], $context, $this->migrationContext);
        $converted = $convertResult->getConverted();

        static::assertNull($convertResult->getUnmapped());
        static::assertNotNull($converted);

        $logs = $this->loggingService->getLoggingArray();
        static::assertCount(1, $logs);

        static::assertSame('SWAG_MIGRATION_SALES_CHANNEL_ENTITY_FIELD_REASSIGNED', $logs[0]['code']);
        static::assertSame($salesChannelData[0]['group_id'], $logs[0]['parameters']['sourceId']);
        static::assertSame('defaultCurrency', $logs[0]['parameters']['emptyField']);
        static::assertSame('system default currency', $logs[0]['parameters']['replacementField']);
        static::assertSame(Defaults::CURRENCY, $converted['currencyId']);
    }

    public function testConvertWithoutDefaultCategory(): void
    {
        $salesChannelData = require __DIR__ . '/../../../_fixtures/sales_channel_data.php';

        $this->mappingService->deleteDummyMapping(DefaultEntities::CATEGORY, '2');

        $context = Context::createDefaultContext();
        $convertResult = $this->salesChannelConverter->convert($salesChannelData[0], $context, $this->migrationContext);

        static::assertNotNull($convertResult->getUnmapped());
        static::assertNull($convertResult->getConverted());

        $logs = $this->loggingService->getLoggingArray();
        static::assertCount(1, $logs);

        static::assertSame($logs[0]['code'], 'SWAG_MIGRATION__SHOPWARE_ASSOCIATION_REQUIRED_MISSING_CATEGORY');
        static::assertSame($logs[0]['parameters']['sourceId'], $salesChannelData[0]['root_category_id']);
    }

    public function testConvertMissingPaymentMethod(): void
    {
        $salesChannelData = require __DIR__ . '/../../../_fixtures/sales_channel_data.php';

        $this->mappingService->deleteDummyMapping(DefaultEntities::PAYMENT_METHOD, 'cashondelivery');

        $context = Context::createDefaultContext();
        $convertResult = $this->salesChannelConverter->convert($salesChannelData[0], $context, $this->migrationContext);
        $converted = $convertResult->getConverted();

        static::assertNull($convertResult->getUnmapped());
        static::assertArrayHasKey('id', $converted);
        static::assertCount(2, $converted['paymentMethods']);
        static::assertCount(5, $converted['shippingMethods']);
        static::assertNotNull($convertResult->getMappingUuid());

        $logs = $this->loggingService->getLoggingArray();
        static::assertCount(1, $logs);

        static::assertSame($logs[0]['code'], 'SWAG_MIGRATION__SHOPWARE_ASSOCIATION_REQUIRED_MISSING_PAYMENT_METHOD');
        static::assertSame($logs[0]['parameters']['sourceId'], 'cashondelivery');
    }

    public function testConvertMissingShippingMethod(): void
    {
        $salesChannelData = require __DIR__ . '/../../../_fixtures/sales_channel_data.php';

        $this->mappingService->deleteDummyMapping(DefaultEntities::SHIPPING_METHOD, 'ups');

        $context = Context::createDefaultContext();
        $convertResult = $this->salesChannelConverter->convert($salesChannelData[0], $context, $this->migrationContext);
        $converted = $convertResult->getConverted();

        static::assertNull($convertResult->getUnmapped());
        static::assertArrayHasKey('id', $converted);
        static::assertCount(3, $converted['paymentMethods']);
        static::assertCount(4, $converted['shippingMethods']);
        static::assertNotNull($convertResult->getMappingUuid());

        $logs = $this->loggingService->getLoggingArray();
        static::assertCount(1, $logs);

        static::assertSame($logs[0]['code'], 'SWAG_MIGRATION__SHOPWARE_ASSOCIATION_REQUIRED_MISSING_SHIPPING_METHOD');
        static::assertSame($logs[0]['parameters']['sourceId'], 'ups');
    }

    public function testConvertMissingWithoutPaymentMethods(): void
    {
        $salesChannelData = require __DIR__ . '/../../../_fixtures/sales_channel_data.php';

        $this->mappingService->deleteDummyMapping(DefaultEntities::PAYMENT_METHOD, 'cashondelivery');
        $this->mappingService->deleteDummyMapping(DefaultEntities::PAYMENT_METHOD, 'free');
        $this->mappingService->deleteDummyMapping(DefaultEntities::PAYMENT_METHOD, 'paypal_standard');

        $context = Context::createDefaultContext();
        $convertResult = $this->salesChannelConverter->convert($salesChannelData[0], $context, $this->migrationContext);

        static::assertNotNull($convertResult->getConverted());
        $converted = $convertResult->getConverted();

        static::assertArrayHasKey('paymentMethodId', $converted);
        static::assertSame($this->defaultPaymentMethodId, $converted['paymentMethodId']);

        static::assertArrayHasKey('shippingMethodId', $converted);
        static::assertNotEmpty($converted['shippingMethodId']);
    }

    public function testConvertMissingWithoutShippingMethods(): void
    {
        $salesChannelData = require __DIR__ . '/../../../_fixtures/sales_channel_data.php';

        $this->mappingService->deleteDummyMapping(DefaultEntities::SHIPPING_METHOD, 'dhlint');
        $this->mappingService->deleteDummyMapping(DefaultEntities::SHIPPING_METHOD, 'fedex');
        $this->mappingService->deleteDummyMapping(DefaultEntities::SHIPPING_METHOD, 'freeshipping');
        $this->mappingService->deleteDummyMapping(DefaultEntities::SHIPPING_METHOD, 'ups');
        $this->mappingService->deleteDummyMapping(DefaultEntities::SHIPPING_METHOD, 'usps');

        $context = Context::createDefaultContext();
        $convertResult = $this->salesChannelConverter->convert($salesChannelData[0], $context, $this->migrationContext);

        static::assertNotNull($convertResult->getConverted());
        $converted = $convertResult->getConverted();

        static::assertArrayHasKey('shippingMethodId', $converted);
        static::assertSame($this->defaultShippingMethodId, $converted['shippingMethodId']);

        static::assertArrayHasKey('paymentMethodId', $converted);
        static::assertNotEmpty($converted['paymentMethodId']);
    }

    public function testConvertWithoutDefaultCustomerGroup(): void
    {
        $salesChannelData = require __DIR__ . '/../../../_fixtures/sales_channel_data.php';

        $context = Context::createDefaultContext();
        $this->mappingService->deleteDummyMapping(DefaultEntities::CUSTOMER_GROUP, 'default_customer_group');
        $convertResult = $this->salesChannelConverter->convert($salesChannelData[0], $context, $this->migrationContext);
        $converted = $convertResult->getConverted();

        static::assertSame(Defaults::FALLBACK_CUSTOMER_GROUP, $converted['customerGroupId']);
    }
}
