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
use Swag\MigrationMagento\Profile\Magento\DataSelection\DataSet\ProductCustomFieldDataSet;
use Swag\MigrationMagento\Profile\Magento23\Converter\Magento23ProductCustomFieldConverter;
use Swag\MigrationMagento\Profile\Magento23\Magento23Profile;
use Swag\MigrationMagento\Test\Mock\Migration\Mapping\DummyMagentoMappingService;
use SwagMigrationAssistant\Migration\Connection\SwagMigrationConnectionEntity;
use SwagMigrationAssistant\Migration\DataSelection\DefaultEntities;
use SwagMigrationAssistant\Migration\MigrationContext;
use SwagMigrationAssistant\Migration\MigrationContextInterface;
use SwagMigrationAssistant\Test\Mock\Migration\Logging\DummyLoggingService;

class Magento2ProductCustomFieldConverterTest extends TestCase
{
    /**
     * @var Magento23ProductCustomFieldConverter
     */
    private $productCustomFieldConverter;

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

    protected function setUp(): void
    {
        $mappingService = new DummyMagentoMappingService();
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
            new ProductCustomFieldDataSet(),
            0,
            250
        );

        $mappingService->pushValueMapping(
            $this->connection->getId(),
            DefaultEntities::LOCALE,
            'global_default',
            'de-DE'
        );

        $this->productCustomFieldConverter = new Magento23ProductCustomFieldConverter($mappingService, $this->loggingService);
    }

    public function testSupports(): void
    {
        $supportsDefinition = $this->productCustomFieldConverter->supports($this->migrationContext);

        static::assertTrue($supportsDefinition);
    }

    public function testConvertSelectField(): void
    {
        $customFieldData = require __DIR__ . '/../../../_fixtures/product_custom_field_data.php';

        $context = Context::createDefaultContext();
        $convertResult = $this->productCustomFieldConverter->convert($customFieldData[0], $context, $this->migrationContext);

        $converted = $convertResult->getConverted();
        $technicalName = 'migration_attribute_' . $customFieldData[0]['setId'] . '_' . $customFieldData[0]['attribute_code'] . '_' . $customFieldData[0]['attribute_id'];

        static::assertNull($convertResult->getUnmapped());
        static::assertArrayHasKey('id', $converted);
        static::assertArrayHasKey('customFields', $converted);
        static::assertArrayHasKey('config', $converted['customFields'][0]);
        static::assertArrayHasKey('name', $converted);
        static::assertSame('migration_set_16', $converted['name']);
        static::assertArrayHasKey('options', $converted['customFields'][0]['config']);
        static::assertArrayHasKey('name', $converted['customFields'][0]);
        static::assertSame($technicalName, $converted['customFields'][0]['name']);
        static::assertCount(5, $converted['customFields'][0]['config']['options']);
        static::assertNotNull($convertResult->getMappingUuid());
    }

    public function testConvertTextarea(): void
    {
        $customFieldData = require __DIR__ . '/../../../_fixtures/product_custom_field_data.php';

        $context = Context::createDefaultContext();
        $convertResult = $this->productCustomFieldConverter->convert($customFieldData[1], $context, $this->migrationContext);

        $converted = $convertResult->getConverted();

        static::assertNull($convertResult->getUnmapped());
        static::assertArrayHasKey('id', $converted);
        static::assertArrayHasKey('customFields', $converted);
        static::assertArrayHasKey('name', $converted);
        static::assertSame('migration_set_11', $converted['name']);
        static::assertArrayHasKey('config', $converted['customFields'][0]);
        static::assertSame('html', $converted['customFields'][0]['type']);
        static::assertNotNull($convertResult->getMappingUuid());
    }

    public function testConvertBool(): void
    {
        $customFieldData = require __DIR__ . '/../../../_fixtures/product_custom_field_data.php';

        $context = Context::createDefaultContext();
        $convertResult = $this->productCustomFieldConverter->convert($customFieldData[2], $context, $this->migrationContext);

        $converted = $convertResult->getConverted();

        static::assertNull($convertResult->getUnmapped());
        static::assertArrayHasKey('id', $converted);
        static::assertArrayHasKey('customFields', $converted);
        static::assertArrayHasKey('name', $converted);
        static::assertSame('migration_set_1', $converted['name']);
        static::assertArrayHasKey('config', $converted['customFields'][0]);
        static::assertSame('bool', $converted['customFields'][0]['type']);
        static::assertNotNull($convertResult->getMappingUuid());
    }

    public function testConvertText(): void
    {
        $customFieldData = require __DIR__ . '/../../../_fixtures/product_custom_field_data.php';

        $context = Context::createDefaultContext();
        $convertResult = $this->productCustomFieldConverter->convert($customFieldData[3], $context, $this->migrationContext);

        $converted = $convertResult->getConverted();

        static::assertNull($convertResult->getUnmapped());
        static::assertArrayHasKey('id', $converted);
        static::assertArrayHasKey('customFields', $converted);
        static::assertArrayHasKey('config', $converted['customFields'][0]);
        static::assertSame('text', $converted['customFields'][0]['type']);
        static::assertNotNull($convertResult->getMappingUuid());
    }

    public function testConvertDate(): void
    {
        $customFieldData = require __DIR__ . '/../../../_fixtures/product_custom_field_data.php';

        $context = Context::createDefaultContext();
        $convertResult = $this->productCustomFieldConverter->convert($customFieldData[4], $context, $this->migrationContext);

        $converted = $convertResult->getConverted();

        static::assertNull($convertResult->getUnmapped());
        static::assertArrayHasKey('id', $converted);
        static::assertArrayHasKey('customFields', $converted);
        static::assertArrayHasKey('config', $converted['customFields'][0]);
        static::assertSame('datetime', $converted['customFields'][0]['type']);
        static::assertNotNull($convertResult->getMappingUuid());
    }
}
