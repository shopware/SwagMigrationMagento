<?php declare(strict_types=1);
/*
 * (c) shopware AG <info@shopware.com>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Swag\MigrationMagento\Profile\Magento\Premapping;

use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Swag\MigrationMagento\Profile\Magento\MagentoProfileInterface;
use Swag\MigrationMagento\Profile\Magento19\Gateway\Local\Magento19LocalGateway;
use SwagMigrationAssistant\Migration\Gateway\GatewayRegistryInterface;
use SwagMigrationAssistant\Migration\MigrationContextInterface;
use SwagMigrationAssistant\Migration\Premapping\AbstractPremappingReader;
use SwagMigrationAssistant\Migration\Premapping\PremappingChoiceStruct;
use SwagMigrationAssistant\Migration\Premapping\PremappingEntityStruct;
use SwagMigrationAssistant\Migration\Premapping\PremappingStruct;

#[Package('services-settings')]
abstract class PaymentMethodReader extends AbstractPremappingReader
{
    private const MAPPING_NAME = 'payment_method';

    /**
     * @var EntityRepository<EntityCollection<PaymentMethodEntity>>
     */
    private EntityRepository $paymentMethodRepo;

    private GatewayRegistryInterface $gatewayRegistry;

    private array $preselectionDictionary;

    /**
     * @var string[]
     */
    private array $choiceUuids;

    /**
     * @param EntityRepository<EntityCollection<PaymentMethodEntity>> $paymentMethodRepo
     */
    public function __construct(
        GatewayRegistryInterface $gatewayRegistry,
        EntityRepository $paymentMethodRepo
    ) {
        $this->gatewayRegistry = $gatewayRegistry;
        $this->paymentMethodRepo = $paymentMethodRepo;
    }

    public static function getMappingName(): string
    {
        return self::MAPPING_NAME;
    }

    public function supports(MigrationContextInterface $migrationContext, array $entityGroupNames): bool
    {
        return $migrationContext->getProfile() instanceof MagentoProfileInterface;
    }

    public function getPremapping(Context $context, MigrationContextInterface $migrationContext): PremappingStruct
    {
        $this->fillConnectionPremappingDictionary($migrationContext);
        $choices = $this->getChoices($context);
        $mapping = $this->getMapping($migrationContext);
        $this->setPreselection($mapping);

        return new PremappingStruct(self::getMappingName(), $mapping, $choices);
    }

    /**
     * @return PremappingEntityStruct[]
     */
    private function getMapping(MigrationContextInterface $migrationContext): array
    {
        /** @var Magento19LocalGateway $gateway */
        $gateway = $this->gatewayRegistry->getGateway($migrationContext);

        $preMappingData = $gateway->readPayments($migrationContext);

        $entityData = [];
        foreach ($preMappingData as $data) {
            $uuid = '';
            if (isset($this->connectionPremappingDictionary[$data['payment_id']])) {
                $uuid = $this->connectionPremappingDictionary[$data['payment_id']]['destinationUuid'];

                if (!isset($this->choiceUuids[$uuid])) {
                    $uuid = '';
                }
            }

            $entityData[] = new PremappingEntityStruct($data['payment_id'], $data['value'] ?? $data['payment_id'], $uuid);
        }

        $uuid = '';
        if (isset($this->connectionPremappingDictionary['default_payment_method'])) {
            $uuid = $this->connectionPremappingDictionary['default_payment_method']['destinationUuid'];

            if (!isset($this->choiceUuids[$uuid])) {
                $uuid = '';
            }
        }

        $entityData[] = new PremappingEntityStruct('default_payment_method', 'Standard payment method', $uuid);
        \usort($entityData, function (PremappingEntityStruct $item1, PremappingEntityStruct $item2) {
            return \strcmp($item1->getDescription(), $item2->getDescription());
        });

        return $entityData;
    }

    /**
     * @return PremappingChoiceStruct[]
     */
    private function getChoices(Context $context): array
    {
        /** @var PaymentMethodEntity[] $paymentMethods */
        $paymentMethods = $this->paymentMethodRepo->search(new Criteria(), $context)->getElements();

        $choices = [];
        foreach ($paymentMethods as $paymentMethod) {
            $paymentMethodName = $paymentMethod->getName();
            if ($paymentMethodName === null) {
                continue;
            }

            $id = $paymentMethod->getId();
            $this->preselectionDictionary[$paymentMethodName] = $id;
            $choices[] = new PremappingChoiceStruct($id, $paymentMethodName);
            $this->choiceUuids[$id] = $id;
        }
        \usort($choices, function (PremappingChoiceStruct $item1, PremappingChoiceStruct $item2) {
            return \strcmp($item1->getDescription(), $item2->getDescription());
        });

        return $choices;
    }

    /**
     * @param PremappingEntityStruct[] $mapping
     */
    private function setPreselection(array $mapping): void
    {
        foreach ($mapping as $item) {
            if ($item->getDestinationUuid() !== '' || !isset($this->preselectionDictionary[$item->getSourceId()])) {
                continue;
            }

            $item->setDestinationUuid($this->preselectionDictionary[$item->getSourceId()]);
        }
    }
}
