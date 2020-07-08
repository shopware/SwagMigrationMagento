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
use Swag\MigrationMagento\Profile\Magento\DataSelection\DataSet\CountryDataSet;
use Swag\MigrationMagento\Profile\Magento23\Converter\Magento23CountryConverter;
use Swag\MigrationMagento\Profile\Magento23\Magento23Profile;
use Swag\MigrationMagento\Test\Mock\Migration\Mapping\DummyMagentoMappingService;
use SwagMigrationAssistant\Migration\Connection\SwagMigrationConnectionEntity;
use SwagMigrationAssistant\Migration\DataSelection\DefaultEntities;
use SwagMigrationAssistant\Migration\MigrationContext;
use SwagMigrationAssistant\Migration\MigrationContextInterface;
use SwagMigrationAssistant\Test\Mock\Migration\Logging\DummyLoggingService;

class Magento2CountryConverterTest extends TestCase
{
    /**
     * @var Magento23CountryConverter
     */
    private $countryConverter;

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
     * @var string
     */
    private $britainMappingUuid;

    protected function setUp(): void
    {
        $mappingService = new DummyMagentoMappingService();
        $this->loggingService = new DummyLoggingService();
        $this->countryConverter = new Magento23CountryConverter($mappingService, $this->loggingService);

        $this->runId = Uuid::randomHex();
        $this->connection = new SwagMigrationConnectionEntity();
        $this->connection->setId(Uuid::randomHex());
        $this->connection->setProfileName(Magento23Profile::PROFILE_NAME);
        $this->connection->setName('shopware');

        $this->migrationContext = new MigrationContext(
            new Magento23Profile(),
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

        $mappingService->getOrCreateMapping($this->connection->getId(), DefaultEntities::LANGUAGE, 'de-DE', $context, null, null, $mappingService::DEFAULT_LANGUAGE_UUID);
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

        static::assertNull($convertResult->getUnmapped());
        static::assertArrayHasKey('id', $converted);
        static::assertEquals($converted['id'], $this->britainMappingUuid);
        static::assertArrayHasKey(DummyMagentoMappingService::DEFAULT_LANGUAGE_UUID, $converted['translations']);
        static::assertNotNull($convertResult->getMappingUuid());
    }

    public function testConvertWithoutMappingButInRegistry(): void
    {
        $countryData = require __DIR__ . '/../../../_fixtures/country_data.php';

        $context = Context::createDefaultContext();
        $convertResult = $this->countryConverter->convert($countryData[0], $context, $this->migrationContext);

        $converted = $convertResult->getConverted();

        static::assertNull($convertResult->getUnmapped());
        static::assertArrayHasKey('id', $converted);
        static::assertArrayHasKey(DummyMagentoMappingService::DEFAULT_LANGUAGE_UUID, $converted['translations']);
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
