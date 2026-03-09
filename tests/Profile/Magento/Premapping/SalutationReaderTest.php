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
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Salutation\SalutationDefinition;
use Shopware\Core\System\Salutation\SalutationEntity;
use Swag\MigrationMagento\Profile\Magento24\Gateway\Local\Magento24LocalGateway;
use Swag\MigrationMagento\Profile\Magento24\Magento24Profile;
use Swag\MigrationMagento\Profile\Magento24\Premapping\Magento24SalutationReader;
use SwagMigrationAssistant\Migration\Connection\SwagMigrationConnectionEntity;
use SwagMigrationAssistant\Migration\Gateway\GatewayRegistry;
use SwagMigrationAssistant\Migration\MigrationContext;
use SwagMigrationAssistant\Migration\MigrationContextInterface;
use SwagMigrationAssistant\Migration\Premapping\PremappingEntityStruct;
use SwagMigrationAssistant\Migration\Premapping\PremappingStruct;
use SwagMigrationAssistant\Profile\Shopware\Gateway\Local\ShopwareLocalGateway;

/**
 * @internal
 */
#[Package('fundamentals@after-sales')]
class SalutationReaderTest extends TestCase
{
    use KernelTestBehaviour;

    private MigrationContextInterface $migrationContext;

    private Magento24SalutationReader $reader;

    private Context $context;

    private SalutationEntity $msMock;

    private SalutationEntity $mrMock;

    protected function setUp(): void
    {
        $this->context = Context::createDefaultContext();

        $connection = new SwagMigrationConnectionEntity();
        $connection->setId(Uuid::randomHex());
        $connection->setProfileName(Magento24Profile::PROFILE_NAME);
        $connection->setGatewayName(ShopwareLocalGateway::GATEWAY_NAME);
        $connection->setCredentialFields([]);

        $this->mrMock = new SalutationEntity();
        $this->mrMock->setId(Uuid::randomHex());
        $this->mrMock->setDisplayName('Mr');
        $this->mrMock->setLetterName('Mr');
        $this->mrMock->setSalutationKey('Mr');

        $this->msMock = new SalutationEntity();
        $this->msMock->setId(Uuid::randomHex());
        $this->msMock->setDisplayName('Ms');
        $this->msMock->setLetterName('Ms');
        $this->msMock->setSalutationKey('Ms');

        $premapping = new PremappingStruct(
            'salutation',
            [
                new PremappingEntityStruct('1', 'mr', $this->mrMock->getId()),
                new PremappingEntityStruct('2', 'ms', $this->msMock->getId()),
                new PremappingEntityStruct('salutation-invalid', 'salutation-invalid', Uuid::randomHex()),
            ],
            []
        );
        $connection->setPremapping([$premapping]);

        $mock = $this->createMock(EntityRepository::class);
        $mock->method('search')->willReturn(new EntitySearchResult(SalutationDefinition::ENTITY_NAME, 2, new EntityCollection([$this->mrMock, $this->msMock]), null, new Criteria(), $this->context));

        $gatewayMock = $this->createMock(Magento24LocalGateway::class);
        $gatewayMock->method('readGenders')->willReturn([
            ['option_id' => '1', 'value' => 'Mr'],
            ['option_id' => '2', 'value' => 'Ms'],
            ['option_id' => 'withoutDescription'],
            ['option_id' => 'salutation-invalid', 'value' => 'salutation-invalid'],
        ]);

        $gatewayRegistryMock = $this->createMock(GatewayRegistry::class);
        $gatewayRegistryMock->method('getGateway')->willReturn($gatewayMock);

        $this->migrationContext = new MigrationContext($connection, new Magento24Profile(), null, null, '', 0, 0);

        $this->reader = new Magento24SalutationReader($gatewayRegistryMock, $mock);
    }

    public function testGetPremapping(): void
    {
        $result = $this->reader->getPremapping($this->context, $this->migrationContext);

        static::assertCount(4, $result->getMapping());
        static::assertCount(2, $result->getChoices());

        $choices = $result->getChoices();
        static::assertSame('Mr', $choices[0]->getDescription());
        static::assertSame('Ms', $choices[1]->getDescription());

        $mapping = $result->getMapping();
        static::assertSame('Mr', $mapping[0]->getDescription());
        static::assertSame('Ms', $mapping[1]->getDescription());
        static::assertSame('Standard salutation', $mapping[2]->getDescription());
        static::assertSame('salutation-invalid', $mapping[3]->getDescription());
        static::assertSame($this->mrMock->getId(), $result->getMapping()[0]->getDestinationUuid());
        static::assertSame($this->msMock->getId(), $result->getMapping()[1]->getDestinationUuid());
        // Because the default of getPreselectionValue
        static::assertSame($this->mrMock->getId(), $result->getMapping()[2]->getDestinationUuid());
        static::assertSame($this->mrMock->getId(), $result->getMapping()[3]->getDestinationUuid());
    }
}
