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
use Swag\MigrationMagento\Profile\Magento\DataSelection\DataSet\NewsletterRecipientDataSet;
use Swag\MigrationMagento\Profile\Magento\DataSelection\DefaultEntities;
use Swag\MigrationMagento\Profile\Magento23\Converter\Magento23NewsletterRecipientConverter;
use Swag\MigrationMagento\Profile\Magento23\Magento23Profile;
use Swag\MigrationMagento\Profile\Magento23\Premapping\Magento23NewsletterRecipientStatusReader;
use Swag\MigrationMagento\Test\Mock\Migration\Mapping\DummyMagentoMappingService;
use SwagMigrationAssistant\Migration\Connection\SwagMigrationConnectionEntity;
use SwagMigrationAssistant\Migration\MigrationContext;
use SwagMigrationAssistant\Migration\MigrationContextInterface;
use SwagMigrationAssistant\Test\Mock\Migration\Logging\DummyLoggingService;

class Magento2NewsletterRecipientConverterTest extends TestCase
{
    /**
     * @var Magento23NewsletterRecipientConverter
     */
    private $newsletterRecipientConverter;

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

    /**
     * @var string
     */
    private $salesChannelUuid;

    /**
     * @var string
     */
    private $newsletterStatus;

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

        $this->languageUuid = Uuid::randomHex();
        $this->mappingService->createMapping(
            $this->connection->getId(),
            DefaultEntities::STORE_LANGUAGE,
            '1',
            null,
            null,
            $this->languageUuid
        );

        $this->salesChannelUuid = Uuid::randomHex();
        $this->mappingService->createMapping(
            $this->connection->getId(),
            DefaultEntities::STORE,
            '1',
            null,
            null,
            $this->salesChannelUuid
        );

        $this->newsletterStatus = 'Subscribed';
        $this->mappingService->pushValueMapping(
            $this->connection->getId(),
            Magento23NewsletterRecipientStatusReader::getMappingName(),
            '1',
            $this->newsletterStatus
        );

        $this->newsletterRecipientConverter = new Magento23NewsletterRecipientConverter($this->mappingService, $this->loggingService);

        $this->migrationContext = new MigrationContext(
            new Magento23Profile(),
            $this->connection,
            $this->runId,
            new NewsletterRecipientDataSet(),
            0,
            250
        );
    }

    public function testSupports(): void
    {
        $supportsDefinition = $this->newsletterRecipientConverter->supports($this->migrationContext);

        static::assertTrue($supportsDefinition);
    }

    public function testConvert(): void
    {
        $newsletterRecipientData = require __DIR__ . '/../../../_fixtures/newsletter_recipient_data.php';

        $context = Context::createDefaultContext();
        $convertResult = $this->newsletterRecipientConverter->convert($newsletterRecipientData[0], $context, $this->migrationContext);

        $converted = $convertResult->getConverted();

        static::assertNull($convertResult->getUnmapped());
        static::assertArrayHasKey('id', $converted);
        static::assertNotNull($convertResult->getMappingUuid());
        static::assertSame($this->newsletterStatus, $converted['status']);
    }

    public function testConvertWithInvalidLanguage(): void
    {
        $context = Context::createDefaultContext();
        $newsletterRecipientData = require __DIR__ . '/../../../_fixtures/newsletter_recipient_data.php';
        $this->mappingService->deleteMapping($this->languageUuid, $this->connection->getId(), $context);

        $convertResult = $this->newsletterRecipientConverter->convert($newsletterRecipientData[0], $context, $this->migrationContext);

        static::assertNotNull($convertResult->getUnmapped());
        static::assertNull($convertResult->getConverted());

        $logs = $this->loggingService->getLoggingArray();
        static::assertCount(1, $logs);

        static::assertSame($logs[0]['code'], 'SWAG_MIGRATION__SHOPWARE_ASSOCIATION_REQUIRED_MISSING_LANGUAGE');
        static::assertSame($newsletterRecipientData[0]['store_id'], $logs[0]['parameters']['sourceId']);
    }

    public function testConvertWithInvalidSalesChannel(): void
    {
        $context = Context::createDefaultContext();
        $newsletterRecipientData = require __DIR__ . '/../../../_fixtures/newsletter_recipient_data.php';
        $newsletterRecipientData[0]['store_id'] = '3';

        $this->mappingService->createMapping(
            $this->connection->getId(),
            DefaultEntities::STORE_LANGUAGE,
            '3',
            null,
            null,
            Uuid::randomHex()
        );

        $convertResult = $this->newsletterRecipientConverter->convert($newsletterRecipientData[0], $context, $this->migrationContext);

        static::assertNotNull($convertResult->getUnmapped());
        static::assertNull($convertResult->getConverted());

        $logs = $this->loggingService->getLoggingArray();
        static::assertCount(1, $logs);

        static::assertSame($logs[0]['code'], 'SWAG_MIGRATION_EMPTY_NECESSARY_FIELD_NEWSLETTER_RECIPIENT');
        static::assertSame($newsletterRecipientData[0]['subscriber_id'], $logs[0]['parameters']['sourceId']);
        static::assertSame('salesChannel', $logs[0]['parameters']['emptyField']);
    }
}
