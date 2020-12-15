<?php declare(strict_types=1);
/*
 * (c) shopware AG <info@shopware.com>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Swag\MigrationMagento\Profile\Magento\Premapping;

use Shopware\Core\Checkout\Order\OrderStates;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateEntity;
use Shopware\Core\System\StateMachine\StateMachineEntity;
use Swag\MigrationMagento\Profile\Magento\DataSelection\CustomerAndOrderDataSelection;
use Swag\MigrationMagento\Profile\Magento\MagentoProfileInterface;
use SwagMigrationAssistant\Migration\Gateway\GatewayRegistryInterface;
use SwagMigrationAssistant\Migration\MigrationContextInterface;
use SwagMigrationAssistant\Migration\Premapping\AbstractPremappingReader;
use SwagMigrationAssistant\Migration\Premapping\PremappingChoiceStruct;
use SwagMigrationAssistant\Migration\Premapping\PremappingEntityStruct;
use SwagMigrationAssistant\Migration\Premapping\PremappingStruct;
use SwagMigrationAssistant\Profile\Shopware\Gateway\ShopwareGatewayInterface;

abstract class OrderStateReader extends AbstractPremappingReader
{
    private const MAPPING_NAME = 'order_state';

    /**
     * @var EntityRepositoryInterface
     */
    protected $stateMachineRepo;

    /**
     * @var EntityRepositoryInterface
     */
    protected $stateMachineStateRepo;

    /**
     * @var string[]
     */
    protected $preselectionDictionary = [];

    /**
     * @var GatewayRegistryInterface
     */
    private $gatewayRegistry;

    /**
     * @var string[]
     */
    private $choiceUuids;

    public function __construct(
        EntityRepositoryInterface $stateMachineRepo,
        EntityRepositoryInterface $stateMachineStateRepo,
        GatewayRegistryInterface $gatewayRegistry
    ) {
        $this->stateMachineRepo = $stateMachineRepo;
        $this->stateMachineStateRepo = $stateMachineStateRepo;
        $this->gatewayRegistry = $gatewayRegistry;
    }

    public static function getMappingName(): string
    {
        return self::MAPPING_NAME;
    }

    public function supports(MigrationContextInterface $migrationContext, array $entityGroupNames): bool
    {
        return $migrationContext->getProfile() instanceof MagentoProfileInterface
            && \in_array(CustomerAndOrderDataSelection::IDENTIFIER, $entityGroupNames, true);
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
        /** @var ShopwareGatewayInterface $gateway */
        $gateway = $this->gatewayRegistry->getGateway($migrationContext);

        $preMappingData = $gateway->readTable($migrationContext, 'sales_order_status');

        $entityData = [];
        foreach ($preMappingData as $data) {
            $uuid = '';
            if (isset($this->connectionPremappingDictionary[$data['status']])) {
                $uuid = $this->connectionPremappingDictionary[$data['status']]['destinationUuid'];

                if (!isset($this->choiceUuids[$uuid])) {
                    $uuid = '';
                }
            }

            $entityData[] = new PremappingEntityStruct($data['status'], $data['label'], $uuid);
        }
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
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('technicalName', OrderStates::STATE_MACHINE));

        /** @var StateMachineEntity $stateMachine */
        $stateMachine = $this->stateMachineRepo->search($criteria, $context)->first();

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('stateMachineId', $stateMachine->getId()));
        $criteria->addSorting(new FieldSorting('name'));
        $states = $this->stateMachineStateRepo->search($criteria, $context);

        $choices = [];
        /** @var StateMachineStateEntity $state */
        foreach ($states as $state) {
            $id = $state->getId();
            $this->preselectionDictionary[$state->getTechnicalName()] = $id;
            $choices[] = new PremappingChoiceStruct($id, $state->getName());
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
            if ($item->getDestinationUuid() !== '') {
                continue;
            }

            $preselectionValue = $this->getPreselectionValue($item->getSourceId());

            if ($preselectionValue !== null) {
                $item->setDestinationUuid($preselectionValue);
            }
        }
    }

    private function getPreselectionValue(string $sourceId): ?string
    {
        $preselectionValue = null;

        switch ($sourceId) {
            case 'canceled':
                $preselectionValue = $this->preselectionDictionary[OrderStates::STATE_CANCELLED];

                break;
            case 'cancel_ogone':
                $preselectionValue = $this->preselectionDictionary[OrderStates::STATE_CANCELLED];

                break;
            case 'closed':
                $preselectionValue = $this->preselectionDictionary[OrderStates::STATE_CANCELLED];

                break;
            case 'complete':
                $preselectionValue = $this->preselectionDictionary[OrderStates::STATE_COMPLETED];

                break;
            case 'decline_ogone':
                $preselectionValue = $this->preselectionDictionary[OrderStates::STATE_CANCELLED];

                break;
            case 'fraud':
                $preselectionValue = $this->preselectionDictionary[OrderStates::STATE_CANCELLED];

                break;
            case 'holded':
                $preselectionValue = $this->preselectionDictionary[OrderStates::STATE_IN_PROGRESS];

                break;
            case 'payment_review':
                $preselectionValue = $this->preselectionDictionary[OrderStates::STATE_IN_PROGRESS];

                break;
            case 'paypal_canceled_reversal':
                $preselectionValue = $this->preselectionDictionary[OrderStates::STATE_CANCELLED];

                break;
            case 'paypal_reversed':
                $preselectionValue = $this->preselectionDictionary[OrderStates::STATE_CANCELLED];

                break;
            case 'pending':
                $preselectionValue = $this->preselectionDictionary[OrderStates::STATE_OPEN];

                break;
            case 'pending_ogone':
                $preselectionValue = $this->preselectionDictionary[OrderStates::STATE_OPEN];

                break;
            case 'pending_payment':
                $preselectionValue = $this->preselectionDictionary[OrderStates::STATE_IN_PROGRESS];

                break;
            case 'pending_paypal':
                $preselectionValue = $this->preselectionDictionary[OrderStates::STATE_IN_PROGRESS];

                break;
            case 'processed_ogone':
                $preselectionValue = $this->preselectionDictionary[OrderStates::STATE_IN_PROGRESS];

                break;
            case 'processing':
                $preselectionValue = $this->preselectionDictionary[OrderStates::STATE_IN_PROGRESS];

                break;
            case 'processing_ogone':
                $preselectionValue = $this->preselectionDictionary[OrderStates::STATE_IN_PROGRESS];

                break;
            case 'waiting_authorozation':
                $preselectionValue = $this->preselectionDictionary[OrderStates::STATE_IN_PROGRESS];

                break;
        }

        return $preselectionValue;
    }
}
