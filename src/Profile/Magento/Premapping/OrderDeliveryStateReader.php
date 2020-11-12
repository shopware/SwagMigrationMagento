<?php declare(strict_types=1);
/*
 * (c) shopware AG <info@shopware.com>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Swag\MigrationMagento\Profile\Magento\Premapping;

use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryStates;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateEntity;
use Shopware\Core\System\StateMachine\StateMachineEntity;
use Swag\MigrationMagento\Profile\Magento\MagentoProfileInterface;
use SwagMigrationAssistant\Migration\MigrationContextInterface;
use SwagMigrationAssistant\Migration\Premapping\AbstractPremappingReader;
use SwagMigrationAssistant\Migration\Premapping\PremappingChoiceStruct;
use SwagMigrationAssistant\Migration\Premapping\PremappingEntityStruct;
use SwagMigrationAssistant\Migration\Premapping\PremappingStruct;
use SwagMigrationAssistant\Profile\Shopware\DataSelection\CustomerAndOrderDataSelection;

abstract class OrderDeliveryStateReader extends AbstractPremappingReader
{
    public const DEFAULT_OPEN_STATUS = 'default_open_order_delivery_status';
    public const DEFAULT_SHIPPED_STATUS = 'default_shipped_order_delivery_status';
    private const MAPPING_NAME = 'order_delivery_state';

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
     * @var string
     */
    private $connectionPremappingValue = '';

    /**
     * @var string[]
     */
    private $choiceUuids;

    public function __construct(
        EntityRepositoryInterface $stateMachineRepo,
        EntityRepositoryInterface $stateMachineStateRepo
    ) {
        $this->stateMachineRepo = $stateMachineRepo;
        $this->stateMachineStateRepo = $stateMachineStateRepo;
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
        $mapping = $this->getMapping();
        $this->setPreselection($mapping);

        return new PremappingStruct(self::getMappingName(), $mapping, $choices);
    }

    /**
     * @return PremappingEntityStruct[]
     */
    private function getMapping(): array
    {
        $entityData = [];
        $entityData[] = new PremappingEntityStruct(self::DEFAULT_OPEN_STATUS, 'Standard open order delivery status', $this->connectionPremappingValue);
        $entityData[] = new PremappingEntityStruct(self::DEFAULT_SHIPPED_STATUS, 'Standard shipped order delivery status', $this->connectionPremappingValue);

        return $entityData;
    }

    /**
     * @return PremappingChoiceStruct[]
     */
    private function getChoices(Context $context): array
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('technicalName', OrderDeliveryStates::STATE_MACHINE));

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

            $preselectionValue = $this->preselectionDictionary[OrderDeliveryStates::STATE_OPEN] ?? null;
            if ($item->getSourceId() === OrderDeliveryStateReader::DEFAULT_SHIPPED_STATUS) {
                $preselectionValue = $this->preselectionDictionary[OrderDeliveryStates::STATE_SHIPPED] ?? null;

                if (!isset($this->choiceUuids[$preselectionValue])) {
                    $preselectionValue = null;
                }
            }

            if ($preselectionValue !== null) {
                $item->setDestinationUuid($preselectionValue);
            }
        }
    }
}
