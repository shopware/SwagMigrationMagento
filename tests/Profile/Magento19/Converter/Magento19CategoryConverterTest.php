<?php declare(strict_types=1);
/*
 * (c) shopware AG <info@shopware.com>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Swag\MigrationMagento\Test\Profile\Magento\Converter;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Uuid\Uuid;
use Swag\MigrationMagento\Profile\Magento\DataSelection\DataSet\CategoryDataSet;
use Swag\MigrationMagento\Profile\Magento\DataSelection\DefaultEntities as MagentoDefaultEntities;
use Swag\MigrationMagento\Profile\Magento19\Converter\Magento19CategoryConverter;
use Swag\MigrationMagento\Profile\Magento19\Magento19Profile;
use Swag\MigrationMagento\Test\Mock\Migration\Mapping\DummyMagentoMappingService;
use SwagMigrationAssistant\Migration\Connection\SwagMigrationConnectionEntity;
use SwagMigrationAssistant\Migration\DataSelection\DefaultEntities;
use SwagMigrationAssistant\Migration\MigrationContext;
use SwagMigrationAssistant\Migration\MigrationContextInterface;
use SwagMigrationAssistant\Profile\Shopware\Exception\ParentEntityForChildNotFoundException;
use SwagMigrationAssistant\Test\Mock\Migration\Logging\DummyLoggingService;
use SwagMigrationAssistant\Test\Mock\Migration\Media\DummyMediaFileService;
use Symfony\Component\HttpFoundation\Response;

class Magento19CategoryConverterTest extends TestCase
{
    /**
     * @var Magento19CategoryConverter
     */
    private $categoryConverter;

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
    private $languageUuid;

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

        $this->languageUuid = DummyMagentoMappingService::DEFAULT_LANGUAGE_UUID;
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
        $mappingService->getOrCreateMapping($this->connection->getId(), DefaultEntities::LANGUAGE, 'de-DE', $context, null, null, $mappingService::DEFAULT_LANGUAGE_UUID);
        $mappingService->getOrCreateMapping($this->connection->getId(), MagentoDefaultEntities::ROOT_CATEGORY, '1', $context);
        $this->categoryConverter = new Magento19CategoryConverter($mappingService, $this->loggingService, $mediaFileService);
    }

    public function testSupports(): void
    {
        $supportsDefinition = $this->categoryConverter->supports($this->migrationContext);

        static::assertTrue($supportsDefinition);
    }

    public function testConvert(): void
    {
        $categoryData = require __DIR__ . '/../../../_fixtures/category_data.php';

        $context = Context::createDefaultContext();
        $convertResult = $this->categoryConverter->convert($categoryData[1], $context, $this->migrationContext);
        $converted = $convertResult->getConverted();

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
        static::assertNull($convertResult->getUnmapped());
        static::assertArrayHasKey('id', $converted);
        static::assertArrayHasKey('parentId', $converted);
        static::assertArrayHasKey(DummyMagentoMappingService::DEFAULT_LANGUAGE_UUID, $converted['translations']);
    }

    public function testConvertWithParentButParentNotConverted(): void
    {
        $categoryData = require __DIR__ . '/../../../_fixtures/category_data.php';

        $context = Context::createDefaultContext();

        try {
            $this->categoryConverter->convert($categoryData[2], $context, $this->migrationContext);
        } catch (\Exception $e) {
            /* @var ParentEntityForChildNotFoundException $e */
            static::assertInstanceOf(ParentEntityForChildNotFoundException::class, $e);
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
