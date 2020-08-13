<?php declare(strict_types=1);
/*
 * (c) shopware AG <info@shopware.com>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Swag\MigrationMagento\Test\Profile\Magento\Premapping;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\CashPayment;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\DebitPayment;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Swag\MigrationMagento\Profile\Magento19\Gateway\Local\Magento19LocalGateway;
use Swag\MigrationMagento\Profile\Magento19\Magento19Profile;
use Swag\MigrationMagento\Profile\Magento19\Premapping\Magento19PaymentMethodReader;
use SwagMigrationAssistant\Migration\Connection\SwagMigrationConnectionEntity;
use SwagMigrationAssistant\Migration\Gateway\GatewayRegistry;
use SwagMigrationAssistant\Migration\MigrationContext;
use SwagMigrationAssistant\Migration\MigrationContextInterface;
use SwagMigrationAssistant\Migration\Premapping\PremappingStruct;
use SwagMigrationAssistant\Profile\Shopware\Gateway\Local\ShopwareLocalGateway;

class PaymentMethodReaderTest extends TestCase
{
    use KernelTestBehaviour;

    /**
     * @var MigrationContextInterface
     */
    private $migrationContext;

    /**
     * @var Magento19PaymentMethodReader
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

        $debitMock = new PaymentMethodEntity();
        $debitMock->setId(Uuid::randomHex());
        $debitMock->setName('Debit');
        $debitMock->setHandlerIdentifier(DebitPayment::class);

        $cashMock = new PaymentMethodEntity();
        $cashMock->setId(Uuid::randomHex());
        $cashMock->setName('Cash');
        $cashMock->setHandlerIdentifier(CashPayment::class);

        $mock = $this->createMock(EntityRepository::class);
        $mock->method('search')->willReturn(new EntitySearchResult(2, new EntityCollection([$debitMock, $cashMock]), null, new Criteria(), $this->context));

        $gatewayMock = $this->createMock(Magento19LocalGateway::class);
        $gatewayMock->method('readPayments')->willReturn([
            ['payment_id' => 'direct', 'value' => 'Direct Debit'],
            ['payment_id' => 'cash', 'value' => 'Cash'],
            ['payment_id' => 'withoutDescription'],
        ]);

        $gatewayRegistryMock = $this->createMock(GatewayRegistry::class);
        $gatewayRegistryMock->method('getGateway')->willReturn($gatewayMock);

        $this->migrationContext = new MigrationContext(
            new Magento19Profile(),
            $connection
        );

        $this->reader = new Magento19PaymentMethodReader($gatewayRegistryMock, $mock);
    }

    public function testGetPremapping(): void
    {
        $result = $this->reader->getPremapping($this->context, $this->migrationContext);

        static::assertInstanceOf(PremappingStruct::class, $result);

        static::assertCount(4, $result->getMapping());
        static::assertCount(2, $result->getChoices());

        $choices = $result->getChoices();
        static::assertSame('Cash', $choices[0]->getDescription());
        static::assertSame('Debit', $choices[1]->getDescription());

        $mapping = $result->getMapping();
        static::assertSame('Cash', $mapping[0]->getDescription());
        static::assertSame('Direct Debit', $mapping[1]->getDescription());
        static::assertSame('Standard payment method', $mapping[2]->getDescription());
        static::assertSame('withoutDescription', $mapping[3]->getDescription());
    }
}
