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
use Swag\MigrationMagento\Profile\Magento\DataSelection\DataSet\ProductDataSet;
use Swag\MigrationMagento\Profile\Magento\DataSelection\DefaultEntities as MagentoDefaultEntities;
use Swag\MigrationMagento\Profile\Magento23\Converter\Magento23ProductConverter;
use Swag\MigrationMagento\Profile\Magento23\Magento23Profile;
use Swag\MigrationMagento\Profile\Magento23\Premapping\Magento23OrderStateReader;
use Swag\MigrationMagento\Test\Mock\Migration\Mapping\DummyMagentoMappingService;
use SwagMigrationAssistant\Migration\Connection\SwagMigrationConnectionEntity;
use SwagMigrationAssistant\Migration\DataSelection\DefaultEntities;
use SwagMigrationAssistant\Migration\MigrationContext;
use SwagMigrationAssistant\Migration\MigrationContextInterface;
use SwagMigrationAssistant\Profile\Shopware\Exception\ParentEntityForChildNotFoundException;
use SwagMigrationAssistant\Test\Mock\Migration\Logging\DummyLoggingService;
use SwagMigrationAssistant\Test\Mock\Migration\Media\DummyMediaFileService;

class Magento2ProductConverterTest extends TestCase
{
    /**
     * @var Magento23ProductConverter
     */
    private $productConverter;

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
    private $languageUuid;

    protected function setUp(): void
    {
        $mediaFileService = new DummyMediaFileService();
        $this->mappingService = new DummyMagentoMappingService();
        $this->loggingService = new DummyLoggingService();

        $this->runId = Uuid::randomHex();
        $this->connection = new SwagMigrationConnectionEntity();
        $this->connection->setId(Uuid::randomHex());
        $this->connection->setProfileName(Magento23Profile::PROFILE_NAME);
        $this->connection->setName('shopware');

        $this->migrationContext = new MigrationContext(
            new Magento23Profile(),
            $this->connection,
            $this->runId,
            new ProductDataSet(),
            0,
            250
        );

        $context = Context::createDefaultContext();
        $this->mappingService->getOrCreateMapping(
            $this->connection->getId(),
            DefaultEntities::TAX,
            '2',
            $context,
            null,
            null,
            Uuid::randomHex()
        );

        $this->mappingService->getOrCreateMapping(
            $this->connection->getId(),
            DefaultEntities::CURRENCY,
            'default_currency',
            $context,
            null,
            null,
            Uuid::randomHex()
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

        $this->languageUuid = DummyMagentoMappingService::DEFAULT_LANGUAGE_UUID;
        $this->mappingService->createMapping(
            $this->connection->getId(),
            MagentoDefaultEntities::STORE_LANGUAGE,
            '1',
            null,
            null,
            $this->languageUuid
        );

        $this->productConverter = new Magento23ProductConverter($this->mappingService, $this->loggingService, $mediaFileService);
    }

    public function testSupports(): void
    {
        $supportsDefinition = $this->productConverter->supports($this->migrationContext);

        static::assertTrue($supportsDefinition);
    }

    public function testConvert(): void
    {
        $productData = require __DIR__ . '/../../../_fixtures/product_data.php';

        $context = Context::createDefaultContext();
        $convertResult = $this->productConverter->convert($productData[0], $context, $this->migrationContext);

        $converted = $convertResult->getConverted();

        static::assertNull($convertResult->getUnmapped());
        static::assertArrayHasKey('id', $converted);
        static::assertNotNull($convertResult->getMappingUuid());

        static::assertSame(
            $productData[0]['translations']['1']['name']['value'],
            $converted['translations'][$this->languageUuid]['name']
        );
        static::assertSame(
            $productData[0]['translations']['1']['oneAttribute']['value'],
            $converted['translations'][$this->languageUuid]['customFields']['migration_attribute_13_oneAttribute_200']
        );
        static::assertSame(
            1.5,
            $converted['weight']
        );
        static::assertSame(
            $productData[0]['translations']['1']['meta_keyword']['value'],
            $converted['translations'][$this->languageUuid]['keywords']
        );
        static::assertSame((int) $productData[0]['minpurchase'], $converted['minPurchase']);
        static::assertArrayNotHasKey('keywords', $converted);
        static::assertArrayNotHasKey('name', $converted);
    }

    public function testConvertWithoutStandardTranslation(): void
    {
        $productData = require __DIR__ . '/../../../_fixtures/product_data.php';
        unset($productData[0]['translations']);

        $context = Context::createDefaultContext();
        $convertResult = $this->productConverter->convert($productData[0], $context, $this->migrationContext);

        $converted = $convertResult->getConverted();

        static::assertNull($convertResult->getUnmapped());
        static::assertArrayHasKey('id', $converted);
        static::assertNotNull($convertResult->getMappingUuid());

        static::assertSame(
            $productData[0]['name'],
            $converted['name']
        );
        static::assertSame(
            $productData['0']['meta_keyword'],
            $converted['keywords']
        );
        static::assertArrayNotHasKey('translations', $converted);
    }

    public function testConvertChildFirst(): void
    {
        $productData = require __DIR__ . '/../../../_fixtures/product_data.php';

        $context = Context::createDefaultContext();
        $this->expectException(ParentEntityForChildNotFoundException::class);
        $this->expectExceptionMessage('Parent entity for "product: 498" child not found.');
        $this->productConverter->convert($productData[1], $context, $this->migrationContext);
    }

    public function testConvertWithoutTax(): void
    {
        $productData = require __DIR__ . '/../../../_fixtures/product_data.php';
        $product = $productData[0];
        unset($product['tax_class_id']);

        $context = Context::createDefaultContext();
        $convertResult = $this->productConverter->convert($product, $context, $this->migrationContext);

        static::assertNotNull($convertResult->getUnmapped());
        static::assertNull($convertResult->getConverted());

        $logs = $this->loggingService->getLoggingArray();
        static::assertCount(1, $logs);

        static::assertSame($logs[0]['code'], 'SWAG_MIGRATION_EMPTY_NECESSARY_FIELD_PRODUCT');
        static::assertSame($logs[0]['parameters']['sourceId'], $product['entity_id']);
        static::assertSame($logs[0]['parameters']['emptyField'], 'tax class');
    }

    public function testConvertWithInvalidTax(): void
    {
        $productData = require __DIR__ . '/../../../_fixtures/product_data.php';
        $product = $productData[0];
        $product['tax_class_id'] = '99';

        $context = Context::createDefaultContext();
        $convertResult = $this->productConverter->convert($product, $context, $this->migrationContext);

        static::assertNotNull($convertResult->getUnmapped());
        static::assertNull($convertResult->getConverted());

        $logs = $this->loggingService->getLoggingArray();
        static::assertCount(1, $logs);

        static::assertSame($logs[0]['code'], 'SWAG_MIGRATION_TAX_ENTITY_UNKNOWN');
        static::assertSame($logs[0]['parameters']['sourceId'], $product['tax_class_id']);
    }

    public function testConvertSimpleProductWithoutPrice(): void
    {
        $productData = require __DIR__ . '/../../../_fixtures/product_data.php';
        $product = $productData[0];
        $product['type_id'] = 'simple';
        unset($product['price']);

        $context = Context::createDefaultContext();
        $convertResult = $this->productConverter->convert($product, $context, $this->migrationContext);

        static::assertNotNull($convertResult->getUnmapped());
        static::assertNull($convertResult->getConverted());

        $logs = $this->loggingService->getLoggingArray();
        static::assertCount(1, $logs);

        static::assertSame($logs[0]['code'], 'SWAG_MIGRATION_EMPTY_NECESSARY_FIELD_PRODUCT');
        static::assertSame($logs[0]['parameters']['sourceId'], $product['entity_id']);
        static::assertSame($logs[0]['parameters']['emptyField'], 'price');
    }

    public function testConvertConfigurableProductWithoutPrice(): void
    {
        $productData = require __DIR__ . '/../../../_fixtures/product_data.php';
        unset($productData[0]['price']);

        $context = Context::createDefaultContext();
        $convertResult = $this->productConverter->convert($productData[0], $context, $this->migrationContext);

        $converted = $convertResult->getConverted();

        static::assertSame(0.0, $converted['price'][0]['net']);
        static::assertSame(0.0, $converted['price'][0]['gross']);
    }

    public function testConvertWithoutDefaultCurrency(): void
    {
        $productData = require __DIR__ . '/../../../_fixtures/product_data.php';
        $product = $productData[0];

        $this->mappingService->deleteDummyMapping(DefaultEntities::CURRENCY, 'default_currency');

        $context = Context::createDefaultContext();
        $convertResult = $this->productConverter->convert($product, $context, $this->migrationContext);

        static::assertNotNull($convertResult->getUnmapped());
        static::assertNull($convertResult->getConverted());

        $logs = $this->loggingService->getLoggingArray();
        static::assertCount(1, $logs);

        static::assertSame($logs[0]['code'], 'SWAG_MIGRATION_EMPTY_NECESSARY_FIELD_PRODUCT');
        static::assertSame($logs[0]['parameters']['sourceId'], $product['entity_id']);
        static::assertSame($logs[0]['parameters']['emptyField'], 'currency');
    }

    public function testConvertWithPriceIsGross(): void
    {
        $productData = require __DIR__ . '/../../../_fixtures/product_data.php';

        $context = Context::createDefaultContext();
        $convertResult = $this->productConverter->convert($productData[0], $context, $this->migrationContext);

        $converted = $convertResult->getConverted();

        static::assertSame(190.0, $converted['price'][0]['net']);
    }

    public function testConvertWithPriceIsNet(): void
    {
        $productData = require __DIR__ . '/../../../_fixtures/product_data.php';
        $productData[0]['priceIsGross'] = true;

        $context = Context::createDefaultContext();
        $convertResult = $this->productConverter->convert($productData[0], $context, $this->migrationContext);

        $converted = $convertResult->getConverted();

        static::assertSame(159.66, $converted['price'][0]['net']);
    }
}
