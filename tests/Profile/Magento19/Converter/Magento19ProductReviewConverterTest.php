<?php declare(strict_types=1);
/*
 * (c) shopware AG <info@shopware.com>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Swag\MigrationMagento\Test\Profile\Magento\Converter;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Uuid\Uuid;
use Swag\MigrationMagento\Profile\Magento\DataSelection\DataSet\ProductReviewDataSet;
use Swag\MigrationMagento\Profile\Magento\DataSelection\DefaultEntities as MagentoDefaultEntities;
use Swag\MigrationMagento\Profile\Magento19\Converter\Magento19ProductReviewConverter;
use Swag\MigrationMagento\Profile\Magento19\Magento19Profile;
use Swag\MigrationMagento\Test\Mock\Migration\Mapping\DummyMagentoMappingService;
use SwagMigrationAssistant\Migration\Connection\SwagMigrationConnectionEntity;
use SwagMigrationAssistant\Migration\DataSelection\DefaultEntities;
use SwagMigrationAssistant\Migration\MigrationContext;
use SwagMigrationAssistant\Migration\MigrationContextInterface;
use SwagMigrationAssistant\Test\Mock\Migration\Logging\DummyLoggingService;

class Magento19ProductReviewConverterTest extends TestCase
{
    /**
     * @var Magento19ProductReviewConverter
     */
    private $productReviewConverter;

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

    protected function setUp(): void
    {
        $this->mappingService = new DummyMagentoMappingService();
        $this->loggingService = new DummyLoggingService();
        $this->productReviewConverter = new Magento19ProductReviewConverter($this->mappingService, $this->loggingService);

        $this->runId = Uuid::randomHex();
        $this->connection = new SwagMigrationConnectionEntity();
        $this->connection->setId(Uuid::randomHex());
        $this->connection->setProfileName(Magento19Profile::PROFILE_NAME);
        $this->connection->setName('shopware');

        $this->migrationContext = new MigrationContext(
            new Magento19Profile(),
            $this->connection,
            $this->runId,
            new ProductReviewDataSet(),
            0,
            250
        );

        $context = Context::createDefaultContext();
        $this->mappingService->getOrCreateMapping(
            $this->connection->getId(),
            DefaultEntities::PRODUCT,
            '337',
            $context,
            null,
            null,
            Uuid::randomHex()
        );

        $this->mappingService->getOrCreateMapping(
            $this->connection->getId(),
            DefaultEntities::CUSTOMER,
            '136',
            $context,
            null,
            null,
            Uuid::randomHex()
        );

        $this->mappingService->getOrCreateMapping(
            $this->connection->getId(),
            MagentoDefaultEntities::STORE,
            '1',
            $context,
            null,
            null,
            Uuid::randomHex()
        );

        $this->mappingService->getOrCreateMapping(
            $this->connection->getId(),
            MagentoDefaultEntities::STORE_LANGUAGE,
            '1',
            $context,
            null,
            null,
            Uuid::randomHex()
        );
    }

    public function testSupports(): void
    {
        $supportsDefinition = $this->productReviewConverter->supports($this->migrationContext);

        static::assertTrue($supportsDefinition);
    }

    public function testConvert(): void
    {
        $productReviewData = require __DIR__ . '/../../../_fixtures/product_review_data.php';

        $context = Context::createDefaultContext();
        $convertResult = $this->productReviewConverter->convert($productReviewData[0], $context, $this->migrationContext);

        $converted = $convertResult->getConverted();

        static::assertNull($convertResult->getUnmapped());
        static::assertArrayHasKey('id', $converted);
        static::assertNotNull($convertResult->getMappingUuid());
    }

    public function testConvertWithoutProductMapping(): void
    {
        $productReviewData = require __DIR__ . '/../../../_fixtures/product_review_data.php';

        $this->mappingService->deleteDummyMapping(DefaultEntities::PRODUCT, '337');

        $context = Context::createDefaultContext();
        $convertResult = $this->productReviewConverter->convert($productReviewData[0], $context, $this->migrationContext);

        static::assertNotNull($convertResult->getUnmapped());
        static::assertNull($convertResult->getConverted());

        $logs = $this->loggingService->getLoggingArray();
        static::assertCount(1, $logs);

        static::assertSame($logs[0]['code'], 'SWAG_MIGRATION__SHOPWARE_ASSOCIATION_REQUIRED_MISSING_PRODUCT');
        static::assertSame($logs[0]['parameters']['sourceId'], $productReviewData[0]['productId']);
    }

    public function testConvertWithoutCustomerMapping(): void
    {
        $productReviewData = require __DIR__ . '/../../../_fixtures/product_review_data.php';

        $this->mappingService->deleteDummyMapping(DefaultEntities::CUSTOMER, '136');

        $context = Context::createDefaultContext();
        $convertResult = $this->productReviewConverter->convert($productReviewData[0], $context, $this->migrationContext);
        $converted = $convertResult->getConverted();

        static::assertNull($convertResult->getUnmapped());
        static::assertNotNull($converted);
        static::assertSame($productReviewData[0]['nickname'], $converted['externalUser']);
    }

    public function testConvertWithoutSalesChannelMapping(): void
    {
        $productReviewData = require __DIR__ . '/../../../_fixtures/product_review_data.php';

        $this->mappingService->deleteDummyMapping(MagentoDefaultEntities::STORE, '1');

        $context = Context::createDefaultContext();
        $convertResult = $this->productReviewConverter->convert($productReviewData[0], $context, $this->migrationContext);

        static::assertNotNull($convertResult->getUnmapped());
        static::assertNull($convertResult->getConverted());

        $logs = $this->loggingService->getLoggingArray();
        static::assertCount(1, $logs);

        static::assertSame($logs[0]['code'], 'SWAG_MIGRATION__SHOPWARE_ASSOCIATION_REQUIRED_MISSING_SALES_CHANNEL');
        static::assertSame($logs[0]['parameters']['sourceId'], $productReviewData[0]['store_id']);
    }

    public function testConvertWithoutLanguageMapping(): void
    {
        $productReviewData = require __DIR__ . '/../../../_fixtures/product_review_data.php';

        $this->mappingService->deleteDummyMapping(MagentoDefaultEntities::STORE_LANGUAGE, '1');

        $context = Context::createDefaultContext();
        $convertResult = $this->productReviewConverter->convert($productReviewData[0], $context, $this->migrationContext);

        static::assertNotNull($convertResult->getUnmapped());
        static::assertNull($convertResult->getConverted());

        $logs = $this->loggingService->getLoggingArray();
        static::assertCount(1, $logs);

        static::assertSame($logs[0]['code'], 'SWAG_MIGRATION__SHOPWARE_ASSOCIATION_REQUIRED_MISSING_STORE_LANGUAGE');
        static::assertSame($logs[0]['parameters']['sourceId'], $productReviewData[0]['store_id']);
    }
}
