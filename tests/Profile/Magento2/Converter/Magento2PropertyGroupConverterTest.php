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
use Swag\MigrationMagento\Profile\Magento\DataSelection\DataSet\PropertyGroupDataSet;
use Swag\MigrationMagento\Profile\Magento\DataSelection\DefaultEntities;
use Swag\MigrationMagento\Profile\Magento23\Converter\Magento23PropertyGroupConverter;
use Swag\MigrationMagento\Profile\Magento23\Magento23Profile;
use Swag\MigrationMagento\Test\LookupHelperTrait;
use Swag\MigrationMagento\Test\Mock\Migration\Mapping\DummyMagentoMappingService;
use SwagMigrationAssistant\Migration\Connection\SwagMigrationConnectionEntity;
use SwagMigrationAssistant\Migration\Mapping\Lookup\LanguageLookup;
use SwagMigrationAssistant\Migration\MigrationContext;
use SwagMigrationAssistant\Migration\MigrationContextInterface;
use SwagMigrationAssistant\Test\Mock\Migration\Logging\DummyLoggingService;

/**
 * @internal
 */
#[Package('fundamentals@after-sales')]
class Magento2PropertyGroupConverterTest extends TestCase
{
    use LookupHelperTrait;

    private Magento23PropertyGroupConverter $propertyGroupConverter;

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
        $this->connection->setProfileName(Magento23Profile::PROFILE_NAME);
        $this->connection->setName('shopware');

        $this->languageUuid = Uuid::randomHex();
        $mappingService->createMapping(
            $this->connection->getId(),
            DefaultEntities::STORE_LANGUAGE,
            '1',
            null,
            null,
            $this->languageUuid
        );

        $this->propertyGroupConverter = new Magento23PropertyGroupConverter(
            $mappingService,
            $this->loggingService,
            $this->getContainer()->get(LanguageLookup::class)
        );

        $this->migrationContext = new MigrationContext($this->connection, new Magento23Profile(), null, new PropertyGroupDataSet(), $this->runId, 0, 250);
    }

    public function testSupports(): void
    {
        $supportsDefinition = $this->propertyGroupConverter->supports($this->migrationContext);

        static::assertTrue($supportsDefinition);
    }

    public function testConvert(): void
    {
        $propertyGroupData = require __DIR__ . '/../../../_fixtures/property_group_data.php';

        $context = Context::createDefaultContext();
        $convertResult = $this->propertyGroupConverter->convert($propertyGroupData[0], $context, $this->migrationContext);

        $converted = $convertResult->getConverted();

        static::assertNotNull($converted);
        static::assertNull($convertResult->getUnmapped());
        static::assertArrayHasKey('id', $converted);
        static::assertNotNull($convertResult->getMappingUuid());
        static::assertCount(1, $converted['options'][0]['translations']);

        static::assertSame(
            $propertyGroupData[0]['options'][0]['translations']['1']['name']['value'],
            $converted['options'][0]['translations'][$this->languageUuid]['name']
        );

        static::assertSame(
            $propertyGroupData[0]['translations']['1']['name']['value'],
            $converted['translations'][$this->languageUuid]['name']
        );
    }

    public function testConvertWithoutName(): void
    {
        $propertyGroupData = require __DIR__ . '/../../../_fixtures/property_group_data.php';
        unset($propertyGroupData[0]['name']);

        $context = Context::createDefaultContext();
        $convertResult = $this->propertyGroupConverter->convert($propertyGroupData[0], $context, $this->migrationContext);

        static::assertNotNull($convertResult->getUnmapped());
        static::assertNull($convertResult->getConverted());

        $logs = $this->loggingService->getLoggingArray();
        static::assertCount(1, $logs);

        static::assertSame($logs[0]['code'], 'SWAG_MIGRATION_EMPTY_NECESSARY_FIELD_PROPERTY_GROUP');
        static::assertSame($logs[0]['parameters']['sourceId'], $propertyGroupData[0]['id']);
        static::assertSame($logs[0]['parameters']['emptyField'], 'group name');
    }
}
