<?php declare(strict_types=1);
/*
 * (c) shopware AG <info@shopware.com>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Swag\MigrationMagento\Test\Profile\Magento2\Converter;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Uuid\Uuid;
use Swag\MigrationMagento\Profile\Magento\DataSelection\DefaultEntities as MagentoDefaultEntities;
use Swag\MigrationMagento\Profile\Magento23\Converter\Magento23SeoUrlConverter;
use Swag\MigrationMagento\Profile\Magento23\Magento23Profile;
use Swag\MigrationMagento\Test\Mock\Migration\Mapping\DummyMagentoMappingService;
use SwagMigrationAssistant\Migration\Connection\SwagMigrationConnectionEntity;
use SwagMigrationAssistant\Migration\DataSelection\DefaultEntities;
use SwagMigrationAssistant\Migration\MigrationContext;
use SwagMigrationAssistant\Profile\Shopware\DataSelection\DataSet\SeoUrlDataSet;
use SwagMigrationAssistant\Test\Mock\Migration\Logging\DummyLoggingService;

class Magento2SeoUrlConverterTest extends TestCase
{
    /**
     * @var DummyMagentoMappingService
     */
    private $mappingService;

    /**
     * @var DummyLoggingService
     */
    private $loggingService;

    /**
     * @var Magento23SeoUrlConverter
     */
    private $seoUrlConverter;

    /**
     * @var string
     */
    private $runId;

    /**
     * @var SwagMigrationConnectionEntity
     */
    private $connection;

    /**
     * @var MigrationContext
     */
    private $migrationContext;

    /**
     * @var Context
     */
    private $context;

    /**
     * @var string
     */
    private $languageId;

    /**
     * @var string
     */
    private $salesChannelId;

    /**
     * @var string
     */
    private $productId;

    /**
     * @var string
     */
    private $categoryId;

    protected function setUp(): void
    {
        $this->mappingService = new DummyMagentoMappingService();
        $this->loggingService = new DummyLoggingService();
        $this->seoUrlConverter = new Magento23SeoUrlConverter($this->mappingService, $this->loggingService);

        $this->runId = Uuid::randomHex();
        $this->connection = new SwagMigrationConnectionEntity();
        $this->connection->setId(Uuid::randomHex());
        $this->connection->setProfileName(Magento23Profile::PROFILE_NAME);
        $this->connection->setName('shopware');

        $this->migrationContext = new MigrationContext(
            new Magento23Profile(),
            $this->connection,
            $this->runId,
            new SeoUrlDataSet(),
            0,
            250
        );

        $this->context = Context::createDefaultContext();

        $this->languageId = Uuid::randomHex();
        $this->salesChannelId = Uuid::randomHex();
        $this->productId = Uuid::randomHex();
        $this->categoryId = Uuid::randomHex();
        $this->mappingService->getOrCreateMapping($this->connection->getId(), DefaultEntities::PRODUCT, '10', $this->context, null, null, $this->productId);
        $this->mappingService->getOrCreateMapping($this->connection->getId(), MagentoDefaultEntities::STORE_LANGUAGE, '1', $this->context, null, null, $this->languageId);
        $this->mappingService->getOrCreateMapping($this->connection->getId(), MagentoDefaultEntities::STORE, '1', $this->context, null, null, $this->salesChannelId);
        $this->mappingService->getOrCreateMapping($this->connection->getId(), DefaultEntities::CATEGORY, '17', $this->context, null, null, $this->categoryId);
    }

    public function testSupports(): void
    {
        $supportsDefinition = $this->seoUrlConverter->supports($this->migrationContext);

        static::assertTrue($supportsDefinition);
    }

    public function testConvertWithInvalidSalesChannel(): void
    {
        $seoUrlData = require __DIR__ . '/../../../_fixtures/seo_url_data.php';
        $seoUrlData[0]['store_id'] = '42';
        $convertResult = $this->seoUrlConverter->convert($seoUrlData[0], $this->context, $this->migrationContext);
        $converted = $convertResult->getConverted();
        $logs = $this->loggingService->getLoggingArray();

        static::assertNotNull($convertResult->getUnmapped());
        static::assertNull($converted);
        static::assertCount(1, $logs);
        static::assertSame('SWAG_MIGRATION__SHOPWARE_ASSOCIATION_REQUIRED_MISSING_SALES_CHANNEL', $logs[0]['code']);
        static::assertSame($seoUrlData[0]['store_id'], $logs[0]['parameters']['sourceId']);
    }

    public function testConvertWithInvalidLanguage(): void
    {
        $seoUrlData = require __DIR__ . '/../../../_fixtures/seo_url_data.php';
        $this->mappingService->deleteMapping($this->languageId, $this->connection->getId(), $this->context);
        $convertResult = $this->seoUrlConverter->convert($seoUrlData[0], $this->context, $this->migrationContext);
        $converted = $convertResult->getConverted();
        $logs = $this->loggingService->getLoggingArray();

        static::assertNotNull($convertResult->getUnmapped());
        static::assertNull($converted);
        static::assertCount(1, $logs);
        static::assertSame('SWAG_MIGRATION__SHOPWARE_ASSOCIATION_REQUIRED_MISSING_STORE_LANGUAGE', $logs[0]['code']);
        static::assertSame($seoUrlData[0]['store_id'], $logs[0]['parameters']['sourceId']);
    }

    public function testConvertWithInvalidProduct(): void
    {
        $seoUrlData = require __DIR__ . '/../../../_fixtures/seo_url_data.php';
        $this->mappingService->deleteMapping($this->productId, $this->connection->getId(), $this->context);
        $convertResult = $this->seoUrlConverter->convert($seoUrlData[0], $this->context, $this->migrationContext);
        $converted = $convertResult->getConverted();
        $logs = $this->loggingService->getLoggingArray();

        static::assertNotNull($convertResult->getUnmapped());
        static::assertNull($converted);
        static::assertCount(1, $logs);
        static::assertSame('SWAG_MIGRATION__SHOPWARE_ASSOCIATION_REQUIRED_MISSING_PRODUCT', $logs[0]['code']);
        static::assertSame($seoUrlData[0]['product_id'], $logs[0]['parameters']['sourceId']);
    }

    public function testConvertWithInvalidCategory(): void
    {
        $seoUrlData = require __DIR__ . '/../../../_fixtures/seo_url_data.php';
        $this->mappingService->deleteMapping($this->categoryId, $this->connection->getId(), $this->context);
        $convertResult = $this->seoUrlConverter->convert($seoUrlData[2], $this->context, $this->migrationContext);
        $converted = $convertResult->getConverted();
        $logs = $this->loggingService->getLoggingArray();

        static::assertNotNull($convertResult->getUnmapped());
        static::assertNull($converted);
        static::assertCount(1, $logs);
        static::assertSame('SWAG_MIGRATION__SHOPWARE_ASSOCIATION_REQUIRED_MISSING_CATEGORY', $logs[0]['code']);
        static::assertSame($seoUrlData[0]['category_id'], $logs[0]['parameters']['sourceId']);
    }

    public function testConvertWithoutProductOrCategory(): void
    {
        $seoUrlData = require __DIR__ . '/../../../_fixtures/seo_url_data.php';
        unset($seoUrlData[2]['category_id']);
        $convertResult = $this->seoUrlConverter->convert($seoUrlData[2], $this->context, $this->migrationContext);
        $converted = $convertResult->getConverted();
        $logs = $this->loggingService->getLoggingArray();

        static::assertNotNull($convertResult->getUnmapped());
        static::assertNull($converted);
        static::assertCount(1, $logs);
        static::assertSame('SWAG_MIGRATION_EMPTY_NECESSARY_FIELD_SEO_URL', $logs[0]['code']);
        static::assertSame($seoUrlData[2]['url_rewrite_id'], $logs[0]['parameters']['sourceId']);
        static::assertSame('category_id, product_id', $logs[0]['parameters']['emptyField']);
    }

    public function testConvertProductAndCategorySeoUrl(): void
    {
        $seoUrlData = require __DIR__ . '/../../../_fixtures/seo_url_data.php';
        $convertResult = $this->seoUrlConverter->convert($seoUrlData[0], $this->context, $this->migrationContext);
        $converted = $convertResult->getConverted();

        static::assertNull($convertResult->getUnmapped());
        static::assertArrayHasKey('id', $converted);
        static::assertSame($this->productId, $converted['foreignKey']);
        static::assertSame($this->salesChannelId, $converted['salesChannelId']);
        static::assertSame($this->languageId, $converted['languageId']);
        static::assertSame($seoUrlData[0]['request_path'], $converted['seoPathInfo']);
        static::assertSame('/detail/' . $this->productId, $converted['pathInfo']);
        static::assertSame('frontend.detail.page', $converted['routeName']);
        static::assertNotNull($convertResult->getMappingUuid());
    }

    public function testConvertProductSeoUrl(): void
    {
        $seoUrlData = require __DIR__ . '/../../../_fixtures/seo_url_data.php';
        $convertResult = $this->seoUrlConverter->convert($seoUrlData[1], $this->context, $this->migrationContext);
        $converted = $convertResult->getConverted();

        static::assertNull($convertResult->getUnmapped());
        static::assertArrayHasKey('id', $converted);
        static::assertSame($this->productId, $converted['foreignKey']);
        static::assertSame($this->salesChannelId, $converted['salesChannelId']);
        static::assertSame($this->languageId, $converted['languageId']);
        static::assertSame($seoUrlData[1]['request_path'], $converted['seoPathInfo']);
        static::assertSame('/detail/' . $this->productId, $converted['pathInfo']);
        static::assertSame('frontend.detail.page', $converted['routeName']);
        static::assertNotNull($convertResult->getMappingUuid());
    }

    public function testConvertProductSeoUrlWithDuplicateHash(): void
    {
        $seoUrlData = require __DIR__ . '/../../../_fixtures/seo_url_data.php';
        $hash = \hash('sha256', $this->languageId . '_' . $this->salesChannelId . '_' . $this->productId . '_frontend.detail.page_not_canonical');
        $this->mappingService->getOrCreateMapping(
            $this->connection->getId(),
            DefaultEntities::SEO_URL,
            $hash,
            $this->context,
            null,
            null,
            Uuid::randomHex()
        );
        $convertResult = $this->seoUrlConverter->convert($seoUrlData[0], $this->context, $this->migrationContext);
        $converted = $convertResult->getConverted();

        static::assertNotNull($convertResult->getUnmapped());
        static::assertNull($converted);
        static::assertCount(0, $this->loggingService->getLoggingArray());
    }

    public function testConvertCategorySeoUrl(): void
    {
        $seoUrlData = require __DIR__ . '/../../../_fixtures/seo_url_data.php';
        $convertResult = $this->seoUrlConverter->convert($seoUrlData[2], $this->context, $this->migrationContext);
        $converted = $convertResult->getConverted();

        static::assertNull($convertResult->getUnmapped());
        static::assertArrayHasKey('id', $converted);
        static::assertSame($this->categoryId, $converted['foreignKey']);
        static::assertSame($this->salesChannelId, $converted['salesChannelId']);
        static::assertSame($this->languageId, $converted['languageId']);
        static::assertSame($seoUrlData[2]['request_path'], $converted['seoPathInfo']);
        static::assertSame('/navigation/' . $this->categoryId, $converted['pathInfo']);
        static::assertSame('frontend.navigation.page', $converted['routeName']);
        static::assertNotNull($convertResult->getMappingUuid());
    }
}
