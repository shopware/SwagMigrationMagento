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
use Swag\MigrationMagento\Profile\Magento\DataSelection\DataSet\CategoryDataSet;
use Swag\MigrationMagento\Profile\Magento\DataSelection\DefaultEntities as MagentoDefaultEntities;
use Swag\MigrationMagento\Profile\Magento20\Converter\Magento20CategoryConverter;
use Swag\MigrationMagento\Profile\Magento20\Magento20Profile;
use Swag\MigrationMagento\Profile\Magento21\Converter\Magento21CategoryConverter;
use Swag\MigrationMagento\Profile\Magento21\Magento21Profile;
use Swag\MigrationMagento\Profile\Magento22\Converter\Magento22CategoryConverter;
use Swag\MigrationMagento\Profile\Magento22\Magento22Profile;
use Swag\MigrationMagento\Profile\Magento23\Converter\Magento23CategoryConverter;
use Swag\MigrationMagento\Profile\Magento23\Magento23Profile;
use Swag\MigrationMagento\Test\Mock\Migration\Mapping\DummyMagentoMappingService;
use SwagMigrationAssistant\Migration\Connection\SwagMigrationConnectionEntity;
use SwagMigrationAssistant\Migration\DataSelection\DefaultEntities;
use SwagMigrationAssistant\Migration\MigrationContext;
use SwagMigrationAssistant\Migration\MigrationContextInterface;
use SwagMigrationAssistant\Profile\Shopware\Exception\ParentEntityForChildNotFoundException;
use SwagMigrationAssistant\Test\Mock\Migration\Logging\DummyLoggingService;
use SwagMigrationAssistant\Test\Mock\Migration\Media\DummyMediaFileService;
use Symfony\Component\HttpFoundation\Response;

class Magento2CategoryConverterTest extends TestCase
{
    /**
     * @var Magento20CategoryConverter
     */
    private $categoryConverter20;

    /**
     * @var Magento21CategoryConverter
     */
    private $categoryConverter21;

    /**
     * @var Magento22CategoryConverter
     */
    private $categoryConverter22;

    /**
     * @var Magento23CategoryConverter
     */
    private $categoryConverter23;

    /**
     * @var DummyLoggingService
     */
    private $loggingService;

    /**
     * @var string
     */
    private $runId;

    /**
     * @var SwagMigrationConnectionEntity
     */
    private $connection20;

    /**
     * @var SwagMigrationConnectionEntity
     */
    private $connection21;

    /**
     * @var SwagMigrationConnectionEntity
     */
    private $connection22;

    /**
     * @var SwagMigrationConnectionEntity
     */
    private $connection23;

    /**
     * @var MigrationContextInterface
     */
    private $migrationContext20;

    /**
     * @var MigrationContextInterface
     */
    private $migrationContext21;

    /**
     * @var MigrationContextInterface
     */
    private $migrationContext22;

    /**
     * @var MigrationContextInterface
     */
    private $migrationContext23;

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

        $this->connection20 = new SwagMigrationConnectionEntity();
        $this->connection20->setId(Uuid::randomHex());
        $this->connection20->setProfileName(Magento20Profile::PROFILE_NAME);
        $this->connection20->setName('magento20');

        $this->connection21 = new SwagMigrationConnectionEntity();
        $this->connection21->setId(Uuid::randomHex());
        $this->connection21->setProfileName(Magento21Profile::PROFILE_NAME);
        $this->connection21->setName('magento21');

        $this->connection22 = new SwagMigrationConnectionEntity();
        $this->connection22->setId(Uuid::randomHex());
        $this->connection22->setProfileName(Magento22Profile::PROFILE_NAME);
        $this->connection22->setName('magento22');

        $this->connection23 = new SwagMigrationConnectionEntity();
        $this->connection23->setId(Uuid::randomHex());
        $this->connection23->setProfileName(Magento23Profile::PROFILE_NAME);
        $this->connection23->setName('magento23');

        $this->languageUuid = Uuid::randomHex();

        $mappingService->createMapping(
            $this->connection20->getId(),
            MagentoDefaultEntities::STORE_LANGUAGE,
            '1',
            null,
            null,
            $this->languageUuid
        );
        $mappingService->createMapping(
            $this->connection21->getId(),
            MagentoDefaultEntities::STORE_LANGUAGE,
            '1',
            null,
            null,
            $this->languageUuid
        );
        $mappingService->createMapping(
            $this->connection22->getId(),
            MagentoDefaultEntities::STORE_LANGUAGE,
            '1',
            null,
            null,
            $this->languageUuid
        );
        $mappingService->createMapping(
            $this->connection23->getId(),
            MagentoDefaultEntities::STORE_LANGUAGE,
            '1',
            null,
            null,
            $this->languageUuid
        );

        $this->migrationContext20 = new MigrationContext(
            new Magento20Profile(),
            $this->connection20,
            $this->runId,
            new CategoryDataSet(),
            0,
            250
        );
        $this->migrationContext21 = new MigrationContext(
            new Magento21Profile(),
            $this->connection21,
            $this->runId,
            new CategoryDataSet(),
            0,
            250
        );
        $this->migrationContext22 = new MigrationContext(
            new Magento22Profile(),
            $this->connection22,
            $this->runId,
            new CategoryDataSet(),
            0,
            250
        );
        $this->migrationContext23 = new MigrationContext(
            new Magento23Profile(),
            $this->connection23,
            $this->runId,
            new CategoryDataSet(),
            0,
            250
        );

        $context = Context::createDefaultContext();
        $mappingService->getOrCreateMapping($this->connection20->getId(), DefaultEntities::LANGUAGE, 'de-DE', $context, null, null, $mappingService::DEFAULT_LANGUAGE_UUID);
        $mappingService->getOrCreateMapping($this->connection21->getId(), DefaultEntities::LANGUAGE, 'de-DE', $context, null, null, $mappingService::DEFAULT_LANGUAGE_UUID);
        $mappingService->getOrCreateMapping($this->connection22->getId(), DefaultEntities::LANGUAGE, 'de-DE', $context, null, null, $mappingService::DEFAULT_LANGUAGE_UUID);
        $mappingService->getOrCreateMapping($this->connection23->getId(), DefaultEntities::LANGUAGE, 'de-DE', $context, null, null, $mappingService::DEFAULT_LANGUAGE_UUID);
        $mappingService->getOrCreateMapping($this->connection20->getId(), MagentoDefaultEntities::ROOT_CATEGORY, '1', $context);
        $mappingService->getOrCreateMapping($this->connection21->getId(), MagentoDefaultEntities::ROOT_CATEGORY, '1', $context);
        $mappingService->getOrCreateMapping($this->connection22->getId(), MagentoDefaultEntities::ROOT_CATEGORY, '1', $context);
        $mappingService->getOrCreateMapping($this->connection23->getId(), MagentoDefaultEntities::ROOT_CATEGORY, '1', $context);

        $this->categoryConverter20 = new Magento20CategoryConverter($mappingService, $this->loggingService, $mediaFileService);
        $this->categoryConverter21 = new Magento21CategoryConverter($mappingService, $this->loggingService, $mediaFileService);
        $this->categoryConverter22 = new Magento22CategoryConverter($mappingService, $this->loggingService, $mediaFileService);
        $this->categoryConverter23 = new Magento23CategoryConverter($mappingService, $this->loggingService, $mediaFileService);
    }

    public function testSupports(): void
    {
        $supportsDefinition20 = $this->categoryConverter20->supports($this->migrationContext20);
        $supportsDefinition21 = $this->categoryConverter21->supports($this->migrationContext21);
        $supportsDefinition22 = $this->categoryConverter22->supports($this->migrationContext22);
        $supportsDefinition23 = $this->categoryConverter23->supports($this->migrationContext23);

        static::assertTrue($supportsDefinition20);
        static::assertTrue($supportsDefinition21);
        static::assertTrue($supportsDefinition22);
        static::assertTrue($supportsDefinition23);
    }

    public function testConvert20(): void
    {
        $categoryData = require __DIR__ . '/../../../_fixtures/category_data.php';

        $context = Context::createDefaultContext();
        $convertResult = $this->categoryConverter20->convert($categoryData[1], $context, $this->migrationContext20);

        $converted = $convertResult->getConverted();

        static::assertNull($convertResult->getUnmapped());
        static::assertArrayHasKey('id', $converted);
        static::assertArrayHasKey($this->languageUuid, $converted['translations']);
        static::assertNotNull($convertResult->getMappingUuid());

        static::assertSame(
            $categoryData[1]['meta_title'],
            $converted['metaTitle']
        );
        static::assertSame(
            \mb_substr($categoryData[1]['meta_description'], 0, 255),
            $converted['metaDescription']
        );
        static::assertSame(
            \mb_substr($categoryData[1]['meta_keywords'], 0, 255),
            $converted['keywords']
        );

        static::assertSame(
            $categoryData[1]['translations']['1']['name']['value'],
            $converted['translations'][$this->languageUuid]['name']
        );
        static::assertSame(
            \mb_substr($categoryData[1]['translations']['1']['meta_description']['value'], 0, 255),
            $converted['translations'][$this->languageUuid]['metaDescription']
        );
        static::assertSame(
            \mb_substr($categoryData[1]['translations']['1']['meta_keywords']['value'], 0, 255),
            $converted['translations'][$this->languageUuid]['keywords']
        );
    }

    public function testConvert21(): void
    {
        $categoryData = require __DIR__ . '/../../../_fixtures/category_data.php';

        $context = Context::createDefaultContext();
        $convertResult = $this->categoryConverter21->convert($categoryData[1], $context, $this->migrationContext21);

        $converted = $convertResult->getConverted();

        static::assertNull($convertResult->getUnmapped());
        static::assertArrayHasKey('id', $converted);
        static::assertArrayHasKey($this->languageUuid, $converted['translations']);
        static::assertNotNull($convertResult->getMappingUuid());

        static::assertSame(
            $categoryData[1]['meta_title'],
            $converted['metaTitle']
        );
        static::assertSame(
            \mb_substr($categoryData[1]['meta_description'], 0, 255),
            $converted['metaDescription']
        );
        static::assertSame(
            \mb_substr($categoryData[1]['meta_keywords'], 0, 255),
            $converted['keywords']
        );

        static::assertSame(
            $categoryData[1]['translations']['1']['name']['value'],
            $converted['translations'][$this->languageUuid]['name']
        );
        static::assertSame(
            \mb_substr($categoryData[1]['translations']['1']['meta_description']['value'], 0, 255),
            $converted['translations'][$this->languageUuid]['metaDescription']
        );
        static::assertSame(
            \mb_substr($categoryData[1]['translations']['1']['meta_keywords']['value'], 0, 255),
            $converted['translations'][$this->languageUuid]['keywords']
        );
    }

    public function testConvert22(): void
    {
        $categoryData = require __DIR__ . '/../../../_fixtures/category_data.php';

        $context = Context::createDefaultContext();
        $convertResult = $this->categoryConverter22->convert($categoryData[1], $context, $this->migrationContext22);

        $converted = $convertResult->getConverted();

        static::assertNull($convertResult->getUnmapped());
        static::assertArrayHasKey('id', $converted);
        static::assertArrayHasKey($this->languageUuid, $converted['translations']);
        static::assertNotNull($convertResult->getMappingUuid());

        static::assertSame(
            $categoryData[1]['meta_title'],
            $converted['metaTitle']
        );
        static::assertSame(
            \mb_substr($categoryData[1]['meta_description'], 0, 255),
            $converted['metaDescription']
        );
        static::assertSame(
            \mb_substr($categoryData[1]['meta_keywords'], 0, 255),
            $converted['keywords']
        );

        static::assertSame(
            $categoryData[1]['translations']['1']['name']['value'],
            $converted['translations'][$this->languageUuid]['name']
        );
        static::assertSame(
            \mb_substr($categoryData[1]['translations']['1']['meta_description']['value'], 0, 255),
            $converted['translations'][$this->languageUuid]['metaDescription']
        );
        static::assertSame(
            \mb_substr($categoryData[1]['translations']['1']['meta_keywords']['value'], 0, 255),
            $converted['translations'][$this->languageUuid]['keywords']
        );
    }

    public function testConvert23(): void
    {
        $categoryData = require __DIR__ . '/../../../_fixtures/category_data.php';

        $context = Context::createDefaultContext();
        $convertResult = $this->categoryConverter23->convert($categoryData[1], $context, $this->migrationContext23);

        $converted = $convertResult->getConverted();

        static::assertNull($convertResult->getUnmapped());
        static::assertArrayHasKey('id', $converted);
        static::assertArrayHasKey($this->languageUuid, $converted['translations']);
        static::assertNotNull($convertResult->getMappingUuid());

        static::assertSame(
            $categoryData[1]['meta_title'],
            $converted['metaTitle']
        );
        static::assertSame(
            \mb_substr($categoryData[1]['meta_description'], 0, 255),
            $converted['metaDescription']
        );
        static::assertSame(
            \mb_substr($categoryData[1]['meta_keywords'], 0, 255),
            $converted['keywords']
        );

        static::assertSame(
            $categoryData[1]['translations']['1']['name']['value'],
            $converted['translations'][$this->languageUuid]['name']
        );
        static::assertSame(
            \mb_substr($categoryData[1]['translations']['1']['meta_description']['value'], 0, 255),
            $converted['translations'][$this->languageUuid]['metaDescription']
        );
        static::assertSame(
            \mb_substr($categoryData[1]['translations']['1']['meta_keywords']['value'], 0, 255),
            $converted['translations'][$this->languageUuid]['keywords']
        );
    }

    public function testConvertWithParent20(): void
    {
        $categoryData = require __DIR__ . '/../../../_fixtures/category_data.php';

        $context = Context::createDefaultContext();
        $this->categoryConverter20->convert($categoryData[1], $context, $this->migrationContext20);
        $convertResult = $this->categoryConverter20->convert($categoryData[2], $context, $this->migrationContext20);

        $converted = $convertResult->getConverted();
        static::assertNull($convertResult->getUnmapped());
        static::assertArrayHasKey('id', $converted);
        static::assertArrayHasKey('parentId', $converted);
        static::assertArrayHasKey(DummyMagentoMappingService::DEFAULT_LANGUAGE_UUID, $converted['translations']);
    }

    public function testConvertWithParent21(): void
    {
        $categoryData = require __DIR__ . '/../../../_fixtures/category_data.php';

        $context = Context::createDefaultContext();
        $this->categoryConverter21->convert($categoryData[1], $context, $this->migrationContext21);
        $convertResult = $this->categoryConverter21->convert($categoryData[2], $context, $this->migrationContext21);

        $converted = $convertResult->getConverted();
        static::assertNull($convertResult->getUnmapped());
        static::assertArrayHasKey('id', $converted);
        static::assertArrayHasKey('parentId', $converted);
        static::assertArrayHasKey(DummyMagentoMappingService::DEFAULT_LANGUAGE_UUID, $converted['translations']);
    }

    public function testConvertWithParent22(): void
    {
        $categoryData = require __DIR__ . '/../../../_fixtures/category_data.php';

        $context = Context::createDefaultContext();
        $this->categoryConverter20->convert($categoryData[1], $context, $this->migrationContext22);
        $convertResult = $this->categoryConverter22->convert($categoryData[2], $context, $this->migrationContext22);

        $converted = $convertResult->getConverted();
        static::assertNull($convertResult->getUnmapped());
        static::assertArrayHasKey('id', $converted);
        static::assertArrayHasKey('parentId', $converted);
        static::assertArrayHasKey(DummyMagentoMappingService::DEFAULT_LANGUAGE_UUID, $converted['translations']);
    }

    public function testConvertWithParent23(): void
    {
        $categoryData = require __DIR__ . '/../../../_fixtures/category_data.php';

        $context = Context::createDefaultContext();
        $this->categoryConverter23->convert($categoryData[1], $context, $this->migrationContext23);
        $convertResult = $this->categoryConverter23->convert($categoryData[2], $context, $this->migrationContext23);

        $converted = $convertResult->getConverted();
        static::assertNull($convertResult->getUnmapped());
        static::assertArrayHasKey('id', $converted);
        static::assertArrayHasKey('parentId', $converted);
        static::assertArrayHasKey(DummyMagentoMappingService::DEFAULT_LANGUAGE_UUID, $converted['translations']);
    }

    public function testConvertWithParentButParentNotConverted20(): void
    {
        $categoryData = require __DIR__ . '/../../../_fixtures/category_data.php';

        $context = Context::createDefaultContext();

        try {
            $this->categoryConverter20->convert($categoryData[2], $context, $this->migrationContext20);
        } catch (\Exception $e) {
            /* @var ParentEntityForChildNotFoundException $e */
            static::assertInstanceOf(ParentEntityForChildNotFoundException::class, $e);
            static::assertSame(Response::HTTP_NOT_FOUND, $e->getStatusCode());
        }
    }

    public function testConvertWithParentButParentNotConverted21(): void
    {
        $categoryData = require __DIR__ . '/../../../_fixtures/category_data.php';

        $context = Context::createDefaultContext();

        try {
            $this->categoryConverter21->convert($categoryData[2], $context, $this->migrationContext21);
        } catch (\Exception $e) {
            /* @var ParentEntityForChildNotFoundException $e */
            static::assertInstanceOf(ParentEntityForChildNotFoundException::class, $e);
            static::assertSame(Response::HTTP_NOT_FOUND, $e->getStatusCode());
        }
    }

    public function testConvertWithParentButParentNotConverted22(): void
    {
        $categoryData = require __DIR__ . '/../../../_fixtures/category_data.php';

        $context = Context::createDefaultContext();

        try {
            $this->categoryConverter22->convert($categoryData[2], $context, $this->migrationContext22);
        } catch (\Exception $e) {
            /* @var ParentEntityForChildNotFoundException $e */
            static::assertInstanceOf(ParentEntityForChildNotFoundException::class, $e);
            static::assertSame(Response::HTTP_NOT_FOUND, $e->getStatusCode());
        }
    }

    public function testConvertWithParentButParentNotConverted23(): void
    {
        $categoryData = require __DIR__ . '/../../../_fixtures/category_data.php';

        $context = Context::createDefaultContext();

        try {
            $this->categoryConverter23->convert($categoryData[2], $context, $this->migrationContext23);
        } catch (\Exception $e) {
            /* @var ParentEntityForChildNotFoundException $e */
            static::assertInstanceOf(ParentEntityForChildNotFoundException::class, $e);
            static::assertSame(Response::HTTP_NOT_FOUND, $e->getStatusCode());
        }
    }

    public function testConvertWithoutLocale20(): void
    {
        $categoryData = require __DIR__ . '/../../../_fixtures/category_data.php';
        $categoryData = $categoryData[1];
        unset($categoryData['defaultLocale']);

        $context = Context::createDefaultContext();
        $convertResult = $this->categoryConverter20->convert($categoryData, $context, $this->migrationContext20);
        static::assertNull($convertResult->getConverted());

        $logs = $this->loggingService->getLoggingArray();
        $title = 'The category entity has one or more empty necessary fields';
        static::assertSame($title, $logs[0]['title']);
        static::assertCount(1, $logs);
    }

    public function testConvertWithoutLocale21(): void
    {
        $categoryData = require __DIR__ . '/../../../_fixtures/category_data.php';
        $categoryData = $categoryData[1];
        unset($categoryData['defaultLocale']);

        $context = Context::createDefaultContext();
        $convertResult = $this->categoryConverter21->convert($categoryData, $context, $this->migrationContext21);
        static::assertNull($convertResult->getConverted());

        $logs = $this->loggingService->getLoggingArray();
        $title = 'The category entity has one or more empty necessary fields';
        static::assertSame($title, $logs[0]['title']);
        static::assertCount(1, $logs);
    }

    public function testConvertWithoutLocale22(): void
    {
        $categoryData = require __DIR__ . '/../../../_fixtures/category_data.php';
        $categoryData = $categoryData[1];
        unset($categoryData['defaultLocale']);

        $context = Context::createDefaultContext();
        $convertResult = $this->categoryConverter22->convert($categoryData, $context, $this->migrationContext22);
        static::assertNull($convertResult->getConverted());

        $logs = $this->loggingService->getLoggingArray();
        $title = 'The category entity has one or more empty necessary fields';
        static::assertSame($title, $logs[0]['title']);
        static::assertCount(1, $logs);
    }

    public function testConvertWithoutLocale23(): void
    {
        $categoryData = require __DIR__ . '/../../../_fixtures/category_data.php';
        $categoryData = $categoryData[1];
        unset($categoryData['defaultLocale']);

        $context = Context::createDefaultContext();
        $convertResult = $this->categoryConverter23->convert($categoryData, $context, $this->migrationContext23);
        static::assertNull($convertResult->getConverted());

        $logs = $this->loggingService->getLoggingArray();
        $title = 'The category entity has one or more empty necessary fields';
        static::assertSame($title, $logs[0]['title']);
        static::assertCount(1, $logs);
    }
}
