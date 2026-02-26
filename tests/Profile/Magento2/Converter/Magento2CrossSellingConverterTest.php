<?php declare(strict_types=1);
/*
 * (c) shopware AG <info@shopware.com>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Swag\MigrationMagento\Test\Profile\Magento2\Converter;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Swag\MigrationMagento\Profile\Magento\DataSelection\DataSet\CrossSellingDataSet;
use Swag\MigrationMagento\Profile\Magento23\Converter\Magento23CrossSellingConverter;
use Swag\MigrationMagento\Profile\Magento23\Magento23Profile;
use Swag\MigrationMagento\Test\Mock\Migration\Mapping\DummyMagentoMappingService;
use SwagMigrationAssistant\Migration\Connection\SwagMigrationConnectionEntity;
use SwagMigrationAssistant\Migration\Converter\ConvertStruct;
use SwagMigrationAssistant\Migration\DataSelection\DefaultEntities;
use SwagMigrationAssistant\Migration\Logging\Log\ConvertAssociationMissingLog;
use SwagMigrationAssistant\Migration\MigrationContext;
use SwagMigrationAssistant\Migration\MigrationContextInterface;
use SwagMigrationAssistant\Test\Mock\Migration\Logging\DummyLoggingService;

/**
 * @internal
 */
#[Package('fundamentals@after-sales')]
class Magento2CrossSellingConverterTest extends TestCase
{
    private Magento23CrossSellingConverter $crossSellingConverter;

    private DummyLoggingService $loggingService;

    private string $runId;

    private SwagMigrationConnectionEntity $connection;

    private MigrationContextInterface $migrationContext;

    private DummyMagentoMappingService $mappingService;

    /**
     * @var array<mixed>
     */
    private array $products = [];

    /**
     * @var list<string>
     */
    private array $type = [
        'Cross-sells',
        'Up-sells',
        'Related products',
    ];

    /**
     * @var array<string, mixed>
     */
    private array $compareProduct = [
        'id' => null,
        'name' => 'Cross-sells',
        'type' => 'productList',
        'active' => true,
        'productId' => null,
        'assignedProducts' => [
            0 => [
                'id' => null,
                'position' => '1',
                'productId' => null,
            ],
        ],
    ];

    protected function setUp(): void
    {
        $this->mappingService = new DummyMagentoMappingService();
        $this->loggingService = new DummyLoggingService();
        $this->crossSellingConverter = new Magento23CrossSellingConverter($this->mappingService, $this->loggingService);

        $this->runId = Uuid::randomHex();
        $this->connection = new SwagMigrationConnectionEntity();
        $this->connection->setId(Uuid::randomHex());
        $this->connection->setProfileName(Magento23Profile::PROFILE_NAME);
        $this->connection->setName('shopware');

        $this->migrationContext = new MigrationContext($this->connection, new Magento23Profile(), null, new CrossSellingDataSet(), $this->runId, 0, 250);

        $this->createMapping(['417', '807', '875', '877', '879', '882', '892', '895']);
    }

    public function testSupports(): void
    {
        $supportsDefinition = $this->crossSellingConverter->supports($this->migrationContext);

        static::assertTrue($supportsDefinition);
    }

    public function testConvert(): void
    {
        $data = require __DIR__ . '/../../../_fixtures/cross_selling_data.php';

        $context = Context::createDefaultContext();
        $convertResult = $this->crossSellingConverter->convert($data[0], $context, $this->migrationContext);
        $this->checkProduct('877', '892', $convertResult, $this->type[1], 16);
    }

    public function testConvertMultipleItems(): void
    {
        $data = require __DIR__ . '/../../../_fixtures/cross_selling_data.php';

        $context = Context::createDefaultContext();
        $convertResult = $this->crossSellingConverter->convert($data[1], $context, $this->migrationContext);
        $this->checkProduct('882', '807', $convertResult, $this->type[0], 17);

        $convertResult = $this->crossSellingConverter->convert($data[2], $context, $this->migrationContext);
        $this->checkProduct('877', '879', $convertResult, $this->type[0], 11);

        $convertResult = $this->crossSellingConverter->convert($data[3], $context, $this->migrationContext);
        $this->checkProduct('417', '892', $convertResult, $this->type[1], 6);

        $convertResult = $this->crossSellingConverter->convert($data[4], $context, $this->migrationContext);
        $this->checkProduct('877', '895', $convertResult, $this->type[2], 18);
    }

    public function testConvertMultipleTime(): void
    {
        $data = require __DIR__ . '/../../../_fixtures/cross_selling_data.php';

        $context = Context::createDefaultContext();
        $convertResult1 = $this->crossSellingConverter->convert($data[1], $context, $this->migrationContext);
        $converted1 = $convertResult1->getConverted();

        $convertResult2 = $this->crossSellingConverter->convert($data[1], $context, $this->migrationContext);
        $converted2 = $convertResult2->getConverted();

        static::assertNotNull($converted1);
        static::assertNotNull($converted2);
        static::assertSame($converted1['id'], $converted2['id']);
        static::assertSame($converted1['productId'], $converted2['productId']);
        static::assertSame($converted1['assignedProducts'][0]['id'], $converted2['assignedProducts']['0']['id']);
        static::assertSame($converted1['assignedProducts'][0]['position'], $converted2['assignedProducts']['0']['position']);
        static::assertSame($converted1['assignedProducts'][0]['productId'], $converted2['assignedProducts']['0']['productId']);
    }

    public function testConvertWithoutMapping(): void
    {
        $data = require __DIR__ . '/../../../_fixtures/cross_selling_data.php';
        $product = $data[0];
        $product['sourceProductId'] = '99';

        $context = Context::createDefaultContext();
        $convertResult = $this->crossSellingConverter->convert($product, $context, $this->migrationContext);

        static::assertNotNull($convertResult->getUnmapped());
        static::assertNull($convertResult->getConverted());

        $logs = $this->loggingService->getLoggingArray();
        static::assertCount(1, $logs);
        static::assertSame(ConvertAssociationMissingLog::getCode(), $logs[0]['code']);

        $this->loggingService->reset();
        $data[0]['linked_product_id'] = '80';
        $convertResult = $this->crossSellingConverter->convert($data[0], $context, $this->migrationContext);

        static::assertNotNull($convertResult->getUnmapped());
        static::assertNull($convertResult->getConverted());

        $logs = $this->loggingService->getLoggingArray();
        static::assertCount(1, $logs);
        static::assertSame(ConvertAssociationMissingLog::getCode(), $logs[0]['code']);
    }

    private function createMapping(array $identifiers): void
    {
        foreach ($identifiers as $identifier) {
            $this->products[$identifier] = $this->mappingService->getOrCreateMapping($this->connection->getId(), DefaultEntities::PRODUCT, $identifier, Context::createDefaultContext(), null, [], Uuid::randomHex());
        }
    }

    private function checkProduct(string $fromIndex, string $toIndex, ConvertStruct $convertStruct, string $type, int $position): void
    {
        $converted = $convertStruct->getConverted();

        static::assertNotNull($converted);

        $this->compareProduct['id'] = $converted['id'];
        $this->compareProduct['name'] = $type;
        $this->compareProduct['productId'] = $this->products[$fromIndex]['entityId'];
        $this->compareProduct['assignedProducts']['0']['id'] = $converted['assignedProducts']['0']['id'];
        $this->compareProduct['assignedProducts']['0']['productId'] = $this->products[$toIndex]['entityId'];
        $this->compareProduct['assignedProducts']['0']['position'] = $position;

        static::assertNull($convertStruct->getUnmapped());
        static::assertNotNull($convertStruct->getMappingUuid());
        static::assertSame($this->compareProduct, $converted);
    }
}
