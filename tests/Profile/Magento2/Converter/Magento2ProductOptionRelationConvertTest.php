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
use Swag\MigrationMagento\Profile\Magento\DataSelection\DataSet\ProductOptionRelationDataSet;
use Swag\MigrationMagento\Profile\Magento23\Converter\Magento23ProductOptionRelationConverter;
use Swag\MigrationMagento\Profile\Magento23\Magento23Profile;
use Swag\MigrationMagento\Test\Mock\Migration\Mapping\DummyMagentoMappingService;
use SwagMigrationAssistant\Migration\Connection\SwagMigrationConnectionEntity;
use SwagMigrationAssistant\Migration\DataSelection\DefaultEntities;
use SwagMigrationAssistant\Migration\MigrationContext;
use SwagMigrationAssistant\Test\Mock\Migration\Logging\DummyLoggingService;

class Magento2ProductOptionRelationConvertTest extends TestCase
{
    /**
     * @var MigrationContext
     */
    private $migrationContext;

    /**
     * @var SwagMigrationConnectionEntity
     */
    private $connection;

    /**
     * @var string
     */
    private $runId;

    /**
     * @var DummyLoggingService
     */
    private $loggingService;

    /**
     * @var Magento23ProductOptionRelationConverter
     */
    private $converter;

    /**
     * @var DummyMagentoMappingService
     */
    private $mappingService;

    protected function setUp(): void
    {
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
            new ProductOptionRelationDataSet(),
            0,
            250
        );

        $this->converter = new Magento23ProductOptionRelationConverter($this->mappingService, $this->loggingService);
    }

    public function testSupports(): void
    {
        $supportsDefinition = $this->converter->supports($this->migrationContext);

        static::assertTrue($supportsDefinition);
    }

    /**
     * @dataProvider getNormalDataProvider
     */
    public function testConvert(array $data): void
    {
        $relationUuid = $this->mappingService->createMapping($this->connection->getId(), DefaultEntities::PROPERTY_GROUP_OPTION, $data['option_id']);
        $productUuid = $this->mappingService->createMapping($this->connection->getId(), DefaultEntities::PRODUCT, $data['entity_id']);

        $context = Context::createDefaultContext();
        $convertResult = $this->converter->convert($data, $context, $this->migrationContext);
        $converted = $convertResult->getConverted();

        static::assertNotNull($converted);
        static::assertNull($convertResult->getUnmapped());
        static::assertSame($productUuid['entityUuid'], $converted['id']);
        static::assertSame($relationUuid['entityUuid'], $converted['options'][0]['id']);
    }

    public function getNormalDataProvider(): array
    {
        $data = require __DIR__ . '/../../../_fixtures/product_property_data.php';

        $returnData = [];
        foreach ($data as $value) {
            $returnData[] = [$value];
        }

        return $returnData;
    }

    /**
     * @dataProvider getWithoutMappingDataProvider
     */
    public function testConvertWithoutMapping(array $data, bool $withoutProductMapping, bool $withoutPropertyMapping): void
    {
        if (!$withoutProductMapping) {
            $this->mappingService->createMapping($this->connection->getId(), DefaultEntities::PRODUCT, $data['entity_id']);
        }

        if (!$withoutPropertyMapping) {
            $this->mappingService->createMapping($this->connection->getId(), DefaultEntities::PROPERTY_GROUP_OPTION, $data['option_id']);
        }

        $context = Context::createDefaultContext();
        $convertResult = $this->converter->convert($data, $context, $this->migrationContext);
        $converted = $convertResult->getConverted();

        static::assertNotNull($convertResult->getUnmapped());
        static::assertNull($converted);
    }

    public function getWithoutMappingDataProvider(): array
    {
        $data = require __DIR__ . '/../../../_fixtures/product_property_data.php';

        $returnData[] = [
            $data[0],
            true,
            false,
        ];

        $returnData[] = [
            $data[0],
            false,
            true,
        ];

        $returnData[] = [
            $data[0],
            true,
            true,
        ];

        return $returnData;
    }
}
