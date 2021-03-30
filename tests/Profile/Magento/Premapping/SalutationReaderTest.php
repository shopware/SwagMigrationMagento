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
use Shopware\Core\System\Salutation\SalutationDefinition;
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

class SalutationReaderTest extends TestCase
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

    /**
     * @var SalutationEntity
     */
    private $msMock;

    /**
     * @var SalutationEntity
     */
    private $mrMock;

    public function setUp(): void
    {
        $this->context = Context::createDefaultContext();

        $connection = new SwagMigrationConnectionEntity();
        $connection->setId(Uuid::randomHex());
        $connection->setProfileName(Magento19Profile::PROFILE_NAME);
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

        $premapping = [[
            'entity' => 'salutation',
            'mapping' => [
                0 => [
                    'sourceId' => '1',
                    'description' => 'mr',
                    'destinationUuid' => $this->mrMock->getId(),
                ],
                1 => [
                    'sourceId' => '2',
                    'description' => 'ms',
                    'destinationUuid' => $this->msMock->getId(),
                ],

                2 => [
                    'sourceId' => 'salutation-invalid',
                    'description' => 'salutation-invalid',
                    'destinationUuid' => Uuid::randomHex(),
                ],
            ],
        ]];
        $connection->setPremapping($premapping);

        $mock = $this->createMock(EntityRepository::class);
        $mock->method('search')->willReturn(new EntitySearchResult(SalutationDefinition::ENTITY_NAME, 2, new EntityCollection([$this->mrMock, $this->msMock]), null, new Criteria(), $this->context));

        $gatewayMock = $this->createMock(Magento19LocalGateway::class);
        $gatewayMock->method('readGenders')->willReturn([
            ['option_id' => '1', 'value' => 'Mr'],
            ['option_id' => '2', 'value' => 'Ms'],
            ['option_id' => 'withoutDescription'],
            ['option_id' => 'salutation-invalid', 'value' => 'salutation-invalid'],
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
