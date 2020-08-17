<?php declare(strict_types=1);
/*
 * (c) shopware AG <info@shopware.com>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Swag\MigrationMagento\Test\Profile\Magento\Premapping;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Salutation\SalutationEntity;
use Swag\MigrationMagento\Profile\Magento19\Gateway\Local\Magento19LocalGateway;
use Swag\MigrationMagento\Profile\Magento19\Magento19Profile;
use Swag\MigrationMagento\Profile\Magento19\Premapping\Magento19SalutationReader;
use SwagMigrationAssistant\Migration\Connection\SwagMigrationConnectionEntity;
use SwagMigrationAssistant\Migration\Gateway\GatewayRegistry;
use SwagMigrationAssistant\Migration\MigrationContext;
use SwagMigrationAssistant\Migration\MigrationContextInterface;
use SwagMigrationAssistant\Migration\Premapping\PremappingStruct;
use SwagMigrationAssistant\Profile\Shopware\Gateway\Local\ShopwareLocalGateway;

class SalutationMethodReaderTest extends TestCase
{
    use KernelTestBehaviour;

    /**
     * @var MigrationContextInterface
     */
    private $migrationContext;

    /**
     * @var Magento19SalutationReader
     */
    private $reader;

    /**
     * @var Context
     */
    private $context;

    public function setUp(): void
    {
        $this->context = Context::createDefaultContext();

        $connection = new SwagMigrationConnectionEntity();
        $connection->setId(Uuid::randomHex());
        $connection->setProfileName(Magento19Profile::PROFILE_NAME);
        $connection->setGatewayName(ShopwareLocalGateway::GATEWAY_NAME);
        $connection->setCredentialFields([]);
        $connection->setPremapping([]);

        $mrMock = new SalutationEntity();
        $mrMock->setId(Uuid::randomHex());
        $mrMock->setDisplayName('Mr');
        $mrMock->setLetterName('Mr');
        $mrMock->setSalutationKey('Mr');

        $msMock = new SalutationEntity();
        $msMock->setId(Uuid::randomHex());
        $msMock->setDisplayName('Ms');
        $msMock->setLetterName('Ms');
        $msMock->setSalutationKey('Ms');

        $mock = $this->createMock(EntityRepository::class);
        $mock->method('search')->willReturn(new EntitySearchResult(2, new EntityCollection([$mrMock, $msMock]), null, new Criteria(), $this->context));

        $gatewayMock = $this->createMock(Magento19LocalGateway::class);
        $gatewayMock->method('readGenders')->willReturn([
            ['option_id' => '1', 'value' => 'Mr'],
            ['option_id' => '2', 'value' => 'Ms'],
            ['option_id' => 'withoutDescription'],
        ]);

        $gatewayRegistryMock = $this->createMock(GatewayRegistry::class);
        $gatewayRegistryMock->method('getGateway')->willReturn($gatewayMock);

        $this->migrationContext = new MigrationContext(
            new Magento19Profile(),
            $connection
        );

        $this->reader = new Magento19SalutationReader($gatewayRegistryMock, $mock);
    }

    public function testGetPremapping(): void
    {
        $result = $this->reader->getPremapping($this->context, $this->migrationContext);

        static::assertInstanceOf(PremappingStruct::class, $result);

        static::assertCount(3, $result->getMapping());
        static::assertCount(2, $result->getChoices());

        $choices = $result->getChoices();
        static::assertSame('Mr', $choices[0]->getDescription());
        static::assertSame('Ms', $choices[1]->getDescription());

        $mapping = $result->getMapping();
        static::assertSame('Mr', $mapping[0]->getDescription());
        static::assertSame('Ms', $mapping[1]->getDescription());
        static::assertSame('Standard salutation', $mapping[2]->getDescription());
    }
}
