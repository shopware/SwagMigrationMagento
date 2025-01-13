<?php declare(strict_types=1);
/*
 * (c) shopware AG <info@shopware.com>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Swag\MigrationMagento\Test\Profile\Magento19\Converter;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Swag\MigrationMagento\Profile\Magento\DataSelection\DataSet\ManufacturerDataSet;
use Swag\MigrationMagento\Profile\Magento\DataSelection\DefaultEntities as MagentoDefaultEntities;
use Swag\MigrationMagento\Profile\Magento19\Converter\Magento19ManufacturerConverter;
use Swag\MigrationMagento\Profile\Magento19\Magento19Profile;
use Swag\MigrationMagento\Test\LookupHelperTrait;
use Swag\MigrationMagento\Test\Mock\Migration\Mapping\DummyMagentoMappingService;
use SwagMigrationAssistant\Migration\Connection\SwagMigrationConnectionEntity;
use SwagMigrationAssistant\Migration\Mapping\Lookup\LanguageLookup;
use SwagMigrationAssistant\Migration\MigrationContext;
use SwagMigrationAssistant\Migration\MigrationContextInterface;
use SwagMigrationAssistant\Test\Mock\Migration\Logging\DummyLoggingService;

#[Package('fundamentals@after-sales')]
class Magento19ManufacturerConverterTest extends TestCase
{
    use LookupHelperTrait;

    private Magento19ManufacturerConverter $manufacturerConverter;

    private DummyLoggingService $loggingService;

    private string $runId;

    private SwagMigrationConnectionEntity $connection;

    private MigrationContextInterface $migrationContext;

    private string $languageUuid;

    protected function setUp(): void
    {
        $mappingService = new DummyMagentoMappingService();
        $this->loggingService = new DummyLoggingService();

        $this->runId = Uuid::randomHex();
        $this->connection = new SwagMigrationConnectionEntity();
        $this->connection->setId(Uuid::randomHex());
        $this->connection->setProfileName(Magento19Profile::PROFILE_NAME);
        $this->connection->setName('shopware');

        $this->migrationContext = new MigrationContext(
            new Magento19Profile(),
            $this->connection,
            $this->runId,
            new ManufacturerDataSet(),
            0,
            250
        );

        $this->languageUuid = DummyMagentoMappingService::DEFAULT_LANGUAGE_UUID;
        $mappingService->createMapping(
            $this->connection->getId(),
            MagentoDefaultEntities::STORE_LANGUAGE,
            '1',
            null,
            null,
            $this->languageUuid
        );

        $this->manufacturerConverter = new Magento19ManufacturerConverter(
            $mappingService,
            $this->loggingService,
            $this->getContainer()->get(LanguageLookup::class),
        );
    }

    public function testSupports(): void
    {
        $supportsDefinition = $this->manufacturerConverter->supports($this->migrationContext);

        static::assertTrue($supportsDefinition);
    }

    public function testConvertAAA(): void
    {
        $manufacturerData = require __DIR__ . '/../../../_fixtures/manufacturer_data.php';

        $context = Context::createDefaultContext();
        $convertResult = $this->manufacturerConverter->convert($manufacturerData[0], $context, $this->migrationContext);

        $converted = $convertResult->getConverted();

        static::assertNotNull($converted);
        static::assertNull($convertResult->getUnmapped());
        static::assertArrayHasKey('id', $converted);
        static::assertNotNull($convertResult->getMappingUuid());

        static::assertSame(
            $manufacturerData[0]['translations']['1']['name']['value'],
            $converted['translations'][$this->languageUuid]['name']
        );

        static::assertArrayHasKey('name', $converted);
    }

    public function testConvertWithoutTranslation(): void
    {
        $manufacturerData = require __DIR__ . '/../../../_fixtures/manufacturer_data.php';
        unset($manufacturerData[0]['translations']);

        $context = Context::createDefaultContext();
        $convertResult = $this->manufacturerConverter->convert($manufacturerData[0], $context, $this->migrationContext);

        $converted = $convertResult->getConverted();

        static::assertNotNull($converted);
        static::assertNull($convertResult->getUnmapped());
        static::assertArrayHasKey('id', $converted);
        static::assertNotNull($convertResult->getMappingUuid());
        static::assertSame($manufacturerData[0]['value'], $converted['name']);
        static::assertArrayNotHasKey('translations', $converted);
    }
}
