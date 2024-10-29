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
use Swag\MigrationMagento\Profile\Magento\DataSelection\DataSet\CountryDataSet;
use Swag\MigrationMagento\Profile\Magento19\Converter\Magento19CountryConverter;
use Swag\MigrationMagento\Profile\Magento19\Magento19Profile;
use Swag\MigrationMagento\Test\LookupHelperTrait;
use Swag\MigrationMagento\Test\Mock\Migration\Mapping\DummyMagentoMappingService;
use SwagMigrationAssistant\Migration\Connection\SwagMigrationConnectionEntity;
use SwagMigrationAssistant\Migration\DataSelection\DefaultEntities;
use SwagMigrationAssistant\Migration\Mapping\Lookup\CountryLookup;
use SwagMigrationAssistant\Migration\Mapping\Lookup\LanguageLookup;
use SwagMigrationAssistant\Migration\MigrationContext;
use SwagMigrationAssistant\Migration\MigrationContextInterface;
use SwagMigrationAssistant\Test\Mock\Migration\Logging\DummyLoggingService;

#[Package('services-settings')]
class Magento19CountryConverterTest extends TestCase
{
    use LookupHelperTrait;

    private Magento19CountryConverter $countryConverter;

    private DummyLoggingService $loggingService;

    private string $runId;

    private SwagMigrationConnectionEntity $connection;

    private MigrationContextInterface $migrationContext;

    private string $britainMappingUuid;

    private string $expectedLanguageUuid;

    protected function setUp(): void
    {
        $expectedLanguageUuid = $this->getLanguageIdByLocaleCode('de-DE');
        static::assertIsString($expectedLanguageUuid);
        $this->expectedLanguageUuid = $expectedLanguageUuid;

        $mappingService = new DummyMagentoMappingService();
        $this->loggingService = new DummyLoggingService();
        $this->countryConverter = new Magento19CountryConverter(
            $mappingService,
            $this->loggingService,
            $this->getContainer()->get(LanguageLookup::class),
            $this->getContainer()->get(CountryLookup::class),
        );

        $this->runId = Uuid::randomHex();
        $this->connection = new SwagMigrationConnectionEntity();
        $this->connection->setId(Uuid::randomHex());
        $this->connection->setProfileName(Magento19Profile::PROFILE_NAME);
        $this->connection->setName('shopware');

        $this->migrationContext = new MigrationContext(
            new Magento19Profile(),
            $this->connection,
            $this->runId,
            new CountryDataSet(),
            0,
            250
        );

        $context = Context::createDefaultContext();
        $this->britainMappingUuid = Uuid::randomHex();
        $mappingService->getOrCreateMapping(
            $this->connection->getId(),
            DefaultEntities::COUNTRY,
            'GB',
            $context,
            null,
            null,
            $this->britainMappingUuid
        );

        $mappingService->getOrCreateMapping($this->connection->getId(), DefaultEntities::LANGUAGE, 'de-DE', $context, null, null, DummyMagentoMappingService::DEFAULT_LANGUAGE_UUID);
    }

    public function testSupports(): void
    {
        $supportsDefinition = $this->countryConverter->supports($this->migrationContext);

        static::assertTrue($supportsDefinition);
    }

    public function testConvertWithMapping(): void
    {
        $countryData = require __DIR__ . '/../../../_fixtures/country_data.php';

        $context = Context::createDefaultContext();
        $convertResult = $this->countryConverter->convert($countryData[1], $context, $this->migrationContext);

        $converted = $convertResult->getConverted();

        static::assertNotNull($converted);
        static::assertNull($convertResult->getUnmapped());
        static::assertArrayHasKey('id', $converted);
        static::assertSame($converted['id'], $this->britainMappingUuid);
        static::assertArrayHasKey($this->expectedLanguageUuid, $converted['translations']);
        static::assertNotNull($convertResult->getMappingUuid());
    }

    public function testConvertWithoutMappingButInRegistry(): void
    {
        $countryData = require __DIR__ . '/../../../_fixtures/country_data.php';

        $context = Context::createDefaultContext();
        $convertResult = $this->countryConverter->convert($countryData[0], $context, $this->migrationContext);

        $converted = $convertResult->getConverted();

        static::assertNotNull($converted);
        static::assertNull($convertResult->getUnmapped());
        static::assertArrayHasKey('id', $converted);
        static::assertArrayHasKey($this->expectedLanguageUuid, $converted['translations']);
        static::assertNotNull($convertResult->getMappingUuid());
    }

    public function testConvertWithoutMappingAndWithoutRegistry(): void
    {
        $countryData = require __DIR__ . '/../../../_fixtures/country_data.php';

        $context = Context::createDefaultContext();
        $convertResult = $this->countryConverter->convert($countryData[4], $context, $this->migrationContext);

        $converted = $convertResult->getConverted();

        static::assertNull($converted);
        static::assertNotNull($convertResult->getUnmapped());
        static::assertArrayHasKey('isoCode', $convertResult->getUnmapped());
    }
}
