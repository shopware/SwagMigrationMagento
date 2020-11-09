<?php declare(strict_types=1);
/*
 * (c) shopware AG <info@shopware.com>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Swag\MigrationMagento\Test\Profile\Magento2\Converter;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\NumberRange\ValueGenerator\NumberRangeValueGeneratorInterface;
use Swag\MigrationMagento\Profile\Magento\DataSelection\DataSet\CustomerDataSet;
use Swag\MigrationMagento\Profile\Magento2\PasswordEncoder\Magento2Argon2Id13Encoder;
use Swag\MigrationMagento\Profile\Magento2\PasswordEncoder\Magento2Md5Encoder;
use Swag\MigrationMagento\Profile\Magento2\PasswordEncoder\Magento2Sha256Encoder;
use Swag\MigrationMagento\Profile\Magento23\Converter\Magento23CustomerConverter;
use Swag\MigrationMagento\Profile\Magento23\Magento23Profile;
use Swag\MigrationMagento\Test\Mock\Migration\Mapping\DummyMagentoMappingService;
use SwagMigrationAssistant\Migration\Connection\SwagMigrationConnectionEntity;
use SwagMigrationAssistant\Migration\DataSelection\DefaultEntities;
use SwagMigrationAssistant\Migration\MigrationContext;
use SwagMigrationAssistant\Migration\MigrationContextInterface;
use SwagMigrationAssistant\Test\Mock\Migration\Logging\DummyLoggingService;

class Magento2CustomerConverterTest extends TestCase
{
    use KernelTestBehaviour;
    use DatabaseTransactionBehaviour;

    /**
     * @var Magento23CustomerConverter
     */
    private $customerConverter;

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
    private $mrMappingUuid;

    /**
     * @var string
     */
    private $paymentMappingUuid;

    /**
     * @var string
     */
    private $countryMappingUuid;

    /**
     * @var string
     */
    private $customerGroupUuid;

    protected function setUp(): void
    {
        $mappingService = new DummyMagentoMappingService();
        $this->loggingService = new DummyLoggingService();
        $this->customerConverter = new Magento23CustomerConverter($mappingService, $this->loggingService, $this->getContainer()->get(NumberRangeValueGeneratorInterface::class));

        $this->runId = Uuid::randomHex();
        $this->connection = new SwagMigrationConnectionEntity();
        $this->connection->setId(Uuid::randomHex());
        $this->connection->setProfileName(Magento23Profile::PROFILE_NAME);
        $this->connection->setName('shopware');

        $this->migrationContext = new MigrationContext(
            new Magento23Profile(),
            $this->connection,
            $this->runId,
            new CustomerDataSet(),
            0,
            250
        );

        $context = Context::createDefaultContext();
        $this->mrMappingUuid = Uuid::randomHex();
        $mappingService->getOrCreateMapping(
            $this->connection->getId(),
            DefaultEntities::SALUTATION,
            '1',
            $context,
            null,
            null,
            $this->mrMappingUuid
        );

        $this->customerGroupUuid = Uuid::randomHex();
        $mappingService->getOrCreateMapping(
            $this->connection->getId(),
            DefaultEntities::CUSTOMER_GROUP,
            '2',
            $context,
            null,
            null,
            $this->customerGroupUuid
        );

        $this->paymentMappingUuid = Uuid::randomHex();
        $mappingService->getOrCreateMapping(
            $this->connection->getId(),
            DefaultEntities::PAYMENT_METHOD,
            'default_payment_method',
            $context,
            null,
            null,
            $this->paymentMappingUuid
        );

        $this->countryMappingUuid = Uuid::randomHex();
        $mappingService->getOrCreateMapping(
            $this->connection->getId(),
            DefaultEntities::COUNTRY,
            'US',
            $context,
            null,
            null,
            $this->countryMappingUuid
        );
    }

    public function testSupports(): void
    {
        $supportsDefinition = $this->customerConverter->supports($this->migrationContext);

        static::assertTrue($supportsDefinition);
    }

    public function testConvert(): void
    {
        $currencyData = require __DIR__ . '/../../../_fixtures/customer_data.php';

        $context = Context::createDefaultContext();
        $convertResult = $this->customerConverter->convert($currencyData[0], $context, $this->migrationContext);

        $converted = $convertResult->getConverted();

        static::assertNull($convertResult->getUnmapped());
        static::assertArrayHasKey('id', $converted);
        static::assertNotNull($convertResult->getMappingUuid());
    }

    public function testConvertArgonPassword(): void
    {
        $customerData = require __DIR__ . '/../../../_fixtures/customer_data.php';

        $customerData[1]['password_hash'] = \str_replace(':0', ':2', $customerData[1]['password_hash']);
        $context = Context::createDefaultContext();
        $convertResult = $this->customerConverter->convert($customerData[1], $context, $this->migrationContext);
        $logs = $this->loggingService->getLoggingArray();
        $converted = $convertResult->getConverted();

        static::assertNull($convertResult->getUnmapped());
        static::assertNotNull($converted);
        static::assertCount(0, $logs);
        static::assertSame(Magento2Argon2Id13Encoder::NAME, $converted['legacyEncoder']);
        static::assertSame($customerData[1]['password_hash'], $converted['legacyPassword']);
    }

    public function testConvertMd5Password(): void
    {
        $customerData = require __DIR__ . '/../../../_fixtures/customer_data.php';

        $context = Context::createDefaultContext();
        $convertResult = $this->customerConverter->convert($customerData[1], $context, $this->migrationContext);

        $converted = $convertResult->getConverted();

        static::assertNull($convertResult->getUnmapped());
        static::assertArrayHasKey('id', $converted);
        static::assertNotNull($convertResult->getMappingUuid());
        static::assertSame(Magento2Md5Encoder::NAME, $converted['legacyEncoder']);
        static::assertSame($customerData[1]['password_hash'], $converted['legacyPassword']);
    }

    public function testConvertSha256Password(): void
    {
        $customerData = require __DIR__ . '/../../../_fixtures/customer_data.php';

        $context = Context::createDefaultContext();
        $convertResult = $this->customerConverter->convert($customerData[0], $context, $this->migrationContext);

        $converted = $convertResult->getConverted();

        static::assertNull($convertResult->getUnmapped());
        static::assertArrayHasKey('id', $converted);
        static::assertNotNull($convertResult->getMappingUuid());
        static::assertSame(Magento2Sha256Encoder::NAME, $converted['legacyEncoder']);
        static::assertSame($customerData[0]['password_hash'], $converted['legacyPassword']);
    }

    public function testConvertDefaultPasswordEncoder(): void
    {
        $customerData = require __DIR__ . '/../../../_fixtures/customer_data.php';
        $customerData[0]['password_hash'] = \str_replace(':1', ':69', $customerData[0]['password_hash']);

        $context = Context::createDefaultContext();
        $convertResult = $this->customerConverter->convert($customerData[0], $context, $this->migrationContext);

        $converted = $convertResult->getConverted();

        static::assertNull($convertResult->getUnmapped());
        static::assertArrayHasKey('id', $converted);
        static::assertNotNull($convertResult->getMappingUuid());
        static::assertSame(Magento2Sha256Encoder::NAME, $converted['legacyEncoder']);
        static::assertSame($customerData[0]['password_hash'], $converted['legacyPassword']);
    }

    public function requiredProperties(): array
    {
        return [
            ['email', null],
            ['email', ''],
            ['firstname', null],
            ['firstname', ''],
            ['lastname', null],
            ['lastname', ''],
        ];
    }

    /**
     * @dataProvider requiredProperties
     */
    public function testConvertWithoutRequiredProperties(string $property, ?string $value): void
    {
        $customerData = require __DIR__ . '/../../../_fixtures/customer_data.php';
        $customerData = $customerData[0];
        $customerData[$property] = $value;

        $context = Context::createDefaultContext();
        $convertResult = $this->customerConverter->convert(
            $customerData,
            $context,
            $this->migrationContext
        );
        static::assertNull($convertResult->getConverted());

        $logs = $this->loggingService->getLoggingArray();
        static::assertCount(1, $logs);

        static::assertSame($logs[0]['code'], 'SWAG_MIGRATION_EMPTY_NECESSARY_FIELD_CUSTOMER');
        static::assertSame($logs[0]['parameters']['emptyField'], $property);
    }

    public function testConvertCustomerWithoutNumber(): void
    {
        $customerData = require __DIR__ . '/../../../_fixtures/customer_data.php';
        $customerData = $customerData[0];
        $customerData['increment_id'] = null;

        $context = Context::createDefaultContext();
        $convertResult = $this->customerConverter->convert(
            $customerData,
            $context,
            $this->migrationContext
        );

        $converted = $convertResult->getConverted();

        static::assertNull($convertResult->getUnmapped());
        static::assertArrayHasKey('id', $converted);
        static::assertArrayHasKey('addresses', $converted);
        static::assertSame(Defaults::SALES_CHANNEL, $converted['salesChannelId']);
        static::assertSame('Berg', $converted['lastName']);
        static::assertSame('10000', $converted['customerNumber']);
        static::assertCount(0, $this->loggingService->getLoggingArray());
    }

    public function testConvertCustomerWithoutAddresses(): void
    {
        $customerData = require __DIR__ . '/../../../_fixtures/customer_data.php';
        $customerData = $customerData[0];
        unset($customerData['addresses']);

        $context = Context::createDefaultContext();
        $convertResult = $this->customerConverter->convert(
            $customerData,
            $context,
            $this->migrationContext
        );

        static::assertNull($convertResult->getConverted());

        $logs = $this->loggingService->getLoggingArray();
        static::assertCount(1, $logs);

        static::assertSame($logs[0]['code'], 'SWAG_MIGRATION_EMPTY_NECESSARY_FIELD_CUSTOMER');
        static::assertSame($logs[0]['parameters']['sourceId'], $customerData['entity_id']);
        static::assertSame($logs[0]['parameters']['emptyField'], 'address data');
    }

    public function testConvertCustomerWithoutValidAddresses(): void
    {
        $customerData = require __DIR__ . '/../../../_fixtures/customer_data.php';
        $customerData = $customerData[0];

        $customerData['addresses'][0]['firstname'] = '';

        $context = Context::createDefaultContext();
        $convertResult = $this->customerConverter->convert(
            $customerData,
            $context,
            $this->migrationContext
        );

        static::assertNull($convertResult->getConverted());

        $logs = $this->loggingService->getLoggingArray();
        static::assertCount(2, $logs);

        static::assertSame($logs[0]['code'], 'SWAG_MIGRATION_EMPTY_NECESSARY_FIELD_CUSTOMER_ADDRESS');
        static::assertSame($logs[0]['parameters']['sourceId'], $customerData['addresses'][0]['entity_id']);
        static::assertSame($logs[0]['parameters']['emptyField'], 'firstname');

        static::assertSame($logs[1]['code'], 'SWAG_MIGRATION_EMPTY_NECESSARY_FIELD_CUSTOMER');
        static::assertSame($logs[1]['parameters']['sourceId'], $customerData['entity_id']);
        static::assertSame($logs[1]['parameters']['emptyField'], 'address data');
    }

    public function requiredAddressProperties(): array
    {
        return [
            ['firstname', null],
            ['firstname', ''],
            ['lastname', null],
            ['lastname', ''],
            ['postcode', null],
            ['postcode', ''],
            ['city', null],
            ['city', ''],
            ['street', null],
            ['street', ''],
            ['country_id', null],
            ['country_id', ''],
            ['country_iso2', null],
            ['country_iso2', ''],
            ['country_iso3', null],
            ['country_iso3', ''],
        ];
    }

    /**
     * @dataProvider requiredAddressProperties
     */
    public function testConvertWithoutRequiredAddressPropertiesForBillingDefault(string $property, ?string $value): void
    {
        $customerData = require __DIR__ . '/../../../_fixtures/customer_data.php';
        $customerData = $customerData[1];
        $customerData['addresses'][0][$property] = $value;

        $context = Context::createDefaultContext();
        $convertResult = $this->customerConverter->convert(
            $customerData,
            $context,
            $this->migrationContext
        );

        $converted = $convertResult->getConverted();

        static::assertNull($convertResult->getUnmapped());
        static::assertArrayHasKey('id', $converted);
        static::assertArrayHasKey('addresses', $converted);

        static::assertSame('Mustermannstraße 92', $converted['addresses'][0]['street']);
        static::assertSame($converted['addresses'][0]['id'], $converted['defaultBillingAddressId']);
        static::assertSame($converted['addresses'][0]['id'], $converted['defaultShippingAddressId']);

        $logs = $this->loggingService->getLoggingArray();
        static::assertCount(2, $logs);

        static::assertSame($logs[0]['code'], 'SWAG_MIGRATION_EMPTY_NECESSARY_FIELD_CUSTOMER_ADDRESS');
        static::assertSame($logs[0]['parameters']['sourceId'], $customerData['addresses'][0]['entity_id']);
        static::assertSame($logs[0]['parameters']['emptyField'], $property);

        static::assertSame($logs[1]['code'], 'SWAG_MIGRATION_CUSTOMER_ENTITY_FIELD_REASSIGNED');
        static::assertSame($logs[1]['parameters']['emptyField'], 'default billing address');
        static::assertSame($logs[1]['parameters']['replacementField'], 'default shipping address');
    }

    /**
     * @dataProvider requiredAddressProperties
     */
    public function testConvertWithoutRequiredAddressPropertiesForShippingDefault(string $property, ?string $value): void
    {
        $customerData = require __DIR__ . '/../../../_fixtures/customer_data.php';
        $customerData = $customerData[1];
        $customerData['addresses'][1][$property] = $value;

        $context = Context::createDefaultContext();
        $convertResult = $this->customerConverter->convert(
            $customerData,
            $context,
            $this->migrationContext
        );

        $converted = $convertResult->getConverted();

        static::assertNull($convertResult->getUnmapped());
        static::assertArrayHasKey('id', $converted);
        static::assertArrayHasKey('addresses', $converted);

        static::assertSame('Musterstr. 55', $converted['addresses'][0]['street']);
        static::assertSame($converted['addresses'][0]['id'], $converted['defaultBillingAddressId']);
        static::assertSame($converted['addresses'][0]['id'], $converted['defaultShippingAddressId']);

        $logs = $this->loggingService->getLoggingArray();
        static::assertCount(2, $logs);

        static::assertSame($logs[0]['code'], 'SWAG_MIGRATION_EMPTY_NECESSARY_FIELD_CUSTOMER_ADDRESS');
        static::assertSame($logs[0]['parameters']['sourceId'], $customerData['addresses'][1]['entity_id']);
        static::assertSame($logs[0]['parameters']['emptyField'], $property);

        static::assertSame($logs[1]['code'], 'SWAG_MIGRATION_CUSTOMER_ENTITY_FIELD_REASSIGNED');
        static::assertSame($logs[1]['parameters']['emptyField'], 'default shipping address');
        static::assertSame($logs[1]['parameters']['replacementField'], 'default billing address');
    }

    /**
     * @dataProvider requiredAddressProperties
     */
    public function testConvertWithoutRequiredAddressPropertiesForDefaultBillingAndShipping(string $property, ?string $value): void
    {
        $customerData = require __DIR__ . '/../../../_fixtures/customer_data.php';
        $customerData = $customerData[1];
        $customerData['addresses'][0][$property] = $value;
        $customerData['addresses'][1][$property] = $value;

        $context = Context::createDefaultContext();
        $convertResult = $this->customerConverter->convert(
            $customerData,
            $context,
            $this->migrationContext
        );

        $converted = $convertResult->getConverted();

        static::assertNull($convertResult->getUnmapped());
        static::assertArrayHasKey('id', $converted);
        static::assertArrayHasKey('addresses', $converted);

        static::assertSame('Musterfraustraße 92', $converted['addresses'][0]['street']);
        static::assertSame($converted['addresses'][0]['id'], $converted['defaultBillingAddressId']);
        static::assertSame($converted['addresses'][0]['id'], $converted['defaultShippingAddressId']);

        $logs = $this->loggingService->getLoggingArray();
        static::assertCount(3, $logs);

        static::assertSame($logs[0]['code'], 'SWAG_MIGRATION_EMPTY_NECESSARY_FIELD_CUSTOMER_ADDRESS');
        static::assertSame($logs[0]['parameters']['sourceId'], $customerData['addresses'][0]['entity_id']);
        static::assertSame($logs[0]['parameters']['emptyField'], $property);

        static::assertSame($logs[1]['code'], 'SWAG_MIGRATION_EMPTY_NECESSARY_FIELD_CUSTOMER_ADDRESS');
        static::assertSame($logs[1]['parameters']['sourceId'], $customerData['addresses'][1]['entity_id']);
        static::assertSame($logs[1]['parameters']['emptyField'], $property);

        static::assertSame($logs[2]['code'], 'SWAG_MIGRATION_CUSTOMER_ENTITY_FIELD_REASSIGNED');
        static::assertSame($logs[2]['parameters']['emptyField'], 'default billing and shipping address');
        static::assertSame($logs[2]['parameters']['replacementField'], 'first address');
    }
}
