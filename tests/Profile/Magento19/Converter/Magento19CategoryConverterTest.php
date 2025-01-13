<?php declare(strict_types=1);
/*
 * (c) shopware AG <info@shopware.com>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Swag\MigrationMagento\Test\Profile\Magento19\Converter;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Context\SystemSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Swag\MigrationMagento\Profile\Magento\DataSelection\DataSet\CategoryDataSet;
use Swag\MigrationMagento\Profile\Magento\DataSelection\DefaultEntities as MagentoDefaultEntities;
use Swag\MigrationMagento\Profile\Magento19\Converter\Magento19CategoryConverter;
use Swag\MigrationMagento\Profile\Magento19\Magento19Profile;
use Swag\MigrationMagento\Test\LookupHelperTrait;
use Swag\MigrationMagento\Test\Mock\Migration\Mapping\DummyMagentoMappingService;
use SwagMigrationAssistant\Exception\MigrationException;
use SwagMigrationAssistant\Migration\Connection\SwagMigrationConnectionEntity;
use SwagMigrationAssistant\Migration\DataSelection\DefaultEntities;
use SwagMigrationAssistant\Migration\Mapping\Lookup\DefaultCmsPageLookup;
use SwagMigrationAssistant\Migration\Mapping\Lookup\LanguageLookup;
use SwagMigrationAssistant\Migration\Mapping\Lookup\LowestRootCategoryLookup;
use SwagMigrationAssistant\Migration\Mapping\Lookup\MediaDefaultFolderLookup;
use SwagMigrationAssistant\Migration\MigrationContext;
use SwagMigrationAssistant\Migration\MigrationContextInterface;
use SwagMigrationAssistant\Test\Mock\Migration\Logging\DummyLoggingService;
use SwagMigrationAssistant\Test\Mock\Migration\Media\DummyMediaFileService;
use Symfony\Component\HttpFoundation\Response;

#[Package('fundamentals@after-sales')]
class Magento19CategoryConverterTest extends TestCase
{
    use LookupHelperTrait;

    private Magento19CategoryConverter $categoryConverter;

    private DummyLoggingService $loggingService;

    private string $runId;

    private SwagMigrationConnectionEntity $connection;

    private MigrationContextInterface $migrationContext;

    private string $languageUuid;

    protected function setUp(): void
    {
        $mediaFileService = new DummyMediaFileService();
        $mappingService = new DummyMagentoMappingService();
        $this->loggingService = new DummyLoggingService();

        $this->runId = Uuid::randomHex();
        $this->connection = new SwagMigrationConnectionEntity();
        $this->connection->setId(Uuid::randomHex());
        $this->connection->setProfileName(Magento19Profile::PROFILE_NAME);
        $this->connection->setName('shopware');

        $languageUuid = $this->getLanguageIdByLocaleCode('de-DE');
        static::assertIsString($languageUuid);
        $this->languageUuid = $languageUuid;

        $mappingService->createMapping(
            $this->connection->getId(),
            MagentoDefaultEntities::STORE_LANGUAGE,
            '1',
            null,
            null,
            $this->languageUuid
        );

        $this->migrationContext = new MigrationContext(
            new Magento19Profile(),
            $this->connection,
            $this->runId,
            new CategoryDataSet(),
            0,
            250
        );

        $context = Context::createDefaultContext();
        $mappingService->getOrCreateMapping($this->connection->getId(), DefaultEntities::LANGUAGE, 'de-DE', $context, null, null, DummyMagentoMappingService::DEFAULT_LANGUAGE_UUID);
        $mappingService->getOrCreateMapping($this->connection->getId(), MagentoDefaultEntities::ROOT_CATEGORY, '1', $context);
        $this->categoryConverter = new Magento19CategoryConverter(
            $mappingService,
            $this->loggingService,
            $mediaFileService,
            $this->getContainer()->get(MediaDefaultFolderLookup::class),
            $this->getContainer()->get(LowestRootCategoryLookup::class),
            $this->getContainer()->get(DefaultCmsPageLookup::class),
            $this->getContainer()->get(LanguageLookup::class),
        );
    }

    public function testSupports(): void
    {
        $supportsDefinition = $this->categoryConverter->supports($this->migrationContext);

        static::assertTrue($supportsDefinition);
    }

    public function testConvert(): void
    {
        $categoryData = require __DIR__ . '/../../../_fixtures/category_data.php';

        $context = new Context(
            new SystemSource(),
            [],
            Defaults::CURRENCY,
            [$this->languageUuid],
            Defaults::LIVE_VERSION,
            1.0,
            false
        );

        $convertResult = $this->categoryConverter->convert($categoryData[1], $context, $this->migrationContext);
        $converted = $convertResult->getConverted();

        static::assertNotNull($converted);
        static::assertNull($convertResult->getUnmapped());
        static::assertArrayHasKey('id', $converted);
        static::assertArrayHasKey($this->languageUuid, $converted['translations']);
        static::assertNotNull($convertResult->getMappingUuid());

        static::assertSame(
            $categoryData[1]['translations']['1']['name']['value'],
            $converted['translations'][$this->languageUuid]['name']
        );
        static::assertSame(
            \mb_substr($categoryData[1]['translations']['1']['meta_title']['value'], 0, 255),
            $converted['translations'][$this->languageUuid]['metaTitle']
        );
        static::assertSame(
            \mb_substr($categoryData[1]['translations']['1']['meta_description']['value'], 0, 255),
            $converted['translations'][$this->languageUuid]['metaDescription']
        );
        static::assertSame(
            \mb_substr($categoryData[1]['translations']['1']['meta_keywords']['value'], 0, 255),
            $converted['translations'][$this->languageUuid]['keywords']
        );

        static::assertArrayNotHasKey('name', $converted);
        static::assertArrayNotHasKey('metaDescription', $converted);
        static::assertArrayNotHasKey('metaTitle', $converted);
        static::assertArrayNotHasKey('keywords', $converted);
    }

    public function testConvertWithoutStandardTranslation(): void
    {
        $categoryData = require __DIR__ . '/../../../_fixtures/category_data.php';
        unset($categoryData[1]['translations']);

        $context = Context::createDefaultContext();
        $convertResult = $this->categoryConverter->convert($categoryData[1], $context, $this->migrationContext);
        $converted = $convertResult->getConverted();

        static::assertNotNull($converted);
        static::assertNull($convertResult->getUnmapped());
        static::assertArrayHasKey('id', $converted);
        static::assertArrayHasKey($this->languageUuid, $converted['translations']);
        static::assertNotNull($convertResult->getMappingUuid());

        static::assertSame($categoryData[1]['name'], $converted['name']);
        static::assertSame($categoryData[1]['meta_title'], $converted['metaTitle']);
        static::assertSame(\mb_substr($categoryData[1]['meta_description'], 0, 255), $converted['metaDescription']);
        static::assertSame(\mb_substr($categoryData[1]['meta_keywords'], 0, 255), $converted['keywords']);
    }

    public function testConvertWithParent(): void
    {
        $categoryData = require __DIR__ . '/../../../_fixtures/category_data.php';

        $context = Context::createDefaultContext();
        $this->categoryConverter->convert($categoryData[1], $context, $this->migrationContext);
        $convertResult = $this->categoryConverter->convert($categoryData[2], $context, $this->migrationContext);

        $converted = $convertResult->getConverted();

        static::assertNotNull($converted);
        static::assertNull($convertResult->getUnmapped());
        static::assertArrayHasKey('id', $converted);
        static::assertArrayHasKey('parentId', $converted);
        static::assertArrayHasKey($this->languageUuid, $converted['translations']);
    }

    public function testConvertWithParentButParentNotConverted(): void
    {
        $categoryData = require __DIR__ . '/../../../_fixtures/category_data.php';

        $context = Context::createDefaultContext();

        try {
            $this->categoryConverter->convert($categoryData[2], $context, $this->migrationContext);
        } catch (\Exception $e) {
            static::assertInstanceOf(MigrationException::class, $e);
            static::assertSame(Response::HTTP_NOT_FOUND, $e->getStatusCode());
        }
    }

    public function testConvertWithoutLocale(): void
    {
        $categoryData = require __DIR__ . '/../../../_fixtures/category_data.php';
        $categoryData = $categoryData[1];
        unset($categoryData['defaultLocale']);

        $context = Context::createDefaultContext();
        $convertResult = $this->categoryConverter->convert($categoryData, $context, $this->migrationContext);
        static::assertNull($convertResult->getConverted());

        $logs = $this->loggingService->getLoggingArray();
        $title = 'The category entity has one or more empty necessary fields';
        static::assertSame($title, $logs[0]['title']);
        static::assertCount(1, $logs);
    }
}
