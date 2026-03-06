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
use Swag\MigrationMagento\Profile\Magento\DataSelection\DataSet\CurrencyDataSet;
use Swag\MigrationMagento\Profile\Magento23\Magento23Profile;
use Swag\MigrationMagento\Profile\Magento24\Converter\Magento24CurrencyConverter;
use Swag\MigrationMagento\Profile\Magento24\Magento24Profile;
use Swag\MigrationMagento\Test\LookupHelperTrait;
use Swag\MigrationMagento\Test\Mock\Migration\Mapping\DummyMagentoMappingService;
use SwagMigrationAssistant\Migration\Connection\SwagMigrationConnectionEntity;
use SwagMigrationAssistant\Migration\DataSelection\DefaultEntities;
use SwagMigrationAssistant\Migration\Mapping\Lookup\CurrencyLookup;
use SwagMigrationAssistant\Migration\Mapping\Lookup\LanguageLookup;
use SwagMigrationAssistant\Migration\MigrationContext;
use SwagMigrationAssistant\Migration\MigrationContextInterface;
use SwagMigrationAssistant\Test\Mock\Migration\Logging\DummyLoggingService;

/**
 * @internal
 */
#[Package('fundamentals@after-sales')]
class Magento2CurrencyConverterTest extends TestCase
{
    use LookupHelperTrait;

    private Magento24CurrencyConverter $currencyConverter;

    private DummyLoggingService $loggingService;

    private string $runId;

    private SwagMigrationConnectionEntity $connection;

    private MigrationContextInterface $migrationContext;

    private string $euroMappingUuid;

    private string $expectedDefaultLanguageId;

    protected function setUp(): void
    {
        $mappingService = new DummyMagentoMappingService();
        $this->loggingService = new DummyLoggingService();
        $this->currencyConverter = new Magento24CurrencyConverter(
            $mappingService,
            $this->loggingService,
            $this->getContainer()->get(CurrencyLookup::class),
            $this->getContainer()->get(LanguageLookup::class),
        );

        $this->runId = Uuid::randomHex();
        $this->connection = new SwagMigrationConnectionEntity();
        $this->connection->setId(Uuid::randomHex());
        $this->connection->setProfileName(Magento24Profile::PROFILE_NAME);
        $this->connection->setName('shopware');

        $this->migrationContext = new MigrationContext($this->connection, new Magento23Profile(), null, new CurrencyDataSet(), $this->runId, 0, 250);

        $context = Context::createDefaultContext();

        $euroMappingUuid = $this->getCurrencyIdByCode('EUR');
        static::assertNotNull($euroMappingUuid);
        $this->euroMappingUuid = $euroMappingUuid;

        $mappingService->getOrCreateMapping(
            $this->connection->getId(),
            DefaultEntities::CURRENCY,
            'EUR',
            $context,
            null,
            null,
            $this->euroMappingUuid
        );

        $expectedDefaultLanguageId = $this->getLanguageIdByLocaleCode('de-DE');
        static::assertIsString($expectedDefaultLanguageId);
        $this->expectedDefaultLanguageId = $expectedDefaultLanguageId;

        $mappingService->getOrCreateMapping($this->connection->getId(), DefaultEntities::LANGUAGE, 'de-DE', $context, null, null, $this->expectedDefaultLanguageId);
    }

    public function testSupports(): void
    {
        $supportsDefinition = $this->currencyConverter->supports($this->migrationContext);

        static::assertTrue($supportsDefinition);
    }

    public function testConvertWithMapping(): void
    {
        $currencyData = require __DIR__ . '/../../../_fixtures/currency_data.php';

        $context = Context::createDefaultContext();
        $convertResult = $this->currencyConverter->convert($currencyData[0], $context, $this->migrationContext);

        $converted = $convertResult->getConverted();

        static::assertNotNull($converted);
        static::assertNull($convertResult->getUnmapped());
        static::assertArrayHasKey('id', $converted);
        static::assertSame($converted['id'], $this->euroMappingUuid);
        static::assertArrayHasKey($this->expectedDefaultLanguageId, $converted['translations']);
        static::assertNotNull($convertResult->getMappingUuid());
    }

    public function testConvertWithoutMappingButInRegistry(): void
    {
        $countryData = require __DIR__ . '/../../../_fixtures/currency_data.php';

        $context = Context::createDefaultContext();
        $convertResult = $this->currencyConverter->convert($countryData[1], $context, $this->migrationContext);

        $converted = $convertResult->getConverted();

        static::assertNotNull($converted);
        static::assertNull($convertResult->getUnmapped());
        static::assertArrayHasKey('id', $converted);
        static::assertArrayHasKey($this->expectedDefaultLanguageId, $converted['translations']);
        static::assertNotNull($convertResult->getMappingUuid());
    }

    public function testConvertWithoutMappingAndWithoutRegistry(): void
    {
        $countryData = require __DIR__ . '/../../../_fixtures/currency_data.php';

        $context = Context::createDefaultContext();
        $convertResult = $this->currencyConverter->convert($countryData[2], $context, $this->migrationContext);

        $converted = $convertResult->getConverted();

        static::assertNull($converted);
        static::assertNotNull($convertResult->getUnmapped());
        static::assertArrayHasKey('isoCode', $convertResult->getUnmapped());
    }
}
