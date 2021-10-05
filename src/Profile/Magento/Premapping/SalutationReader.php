<?php declare(strict_types=1);
/*
 * (c) shopware AG <info@shopware.com>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Swag\MigrationMagento\Profile\Magento\Premapping;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\System\Salutation\SalutationEntity;
use Swag\MigrationMagento\Profile\Magento\DataSelection\CustomerAndOrderDataSelection;
use Swag\MigrationMagento\Profile\Magento\DataSelection\ProductReviewDataSelection;
use Swag\MigrationMagento\Profile\Magento\Gateway\MagentoGatewayInterface;
use Swag\MigrationMagento\Profile\Magento\MagentoProfileInterface;
use SwagMigrationAssistant\Migration\Gateway\GatewayRegistryInterface;
use SwagMigrationAssistant\Migration\MigrationContextInterface;
use SwagMigrationAssistant\Migration\Premapping\AbstractPremappingReader;
use SwagMigrationAssistant\Migration\Premapping\PremappingChoiceStruct;
use SwagMigrationAssistant\Migration\Premapping\PremappingEntityStruct;
use SwagMigrationAssistant\Migration\Premapping\PremappingStruct;

abstract class SalutationReader extends AbstractPremappingReader
{
    private const MAPPING_NAME = 'salutation';

    /**
     * @var string[]
     */
    protected $preselectionDictionary = [];

    /**
     * @var EntityRepositoryInterface
     */
    private $salutationRepo;

    /**
     * @var GatewayRegistryInterface
     */
    private $gatewayRegistry;

    /**
     * @var string[]
     */
    private $choiceUuids;

    public function __construct(
        GatewayRegistryInterface $gatewayRegistry,
        EntityRepositoryInterface $salutationRepo
    ) {
        $this->gatewayRegistry = $gatewayRegistry;
        $this->salutationRepo = $salutationRepo;
    }

    public static function getMappingName(): string
    {
        return self::MAPPING_NAME;
    }

    public function supports(MigrationContextInterface $migrationContext, array $entityGroupNames): bool
    {
        return $migrationContext->getProfile() instanceof MagentoProfileInterface
            && (\in_array(CustomerAndOrderDataSelection::IDENTIFIER, $entityGroupNames, true)
            || \in_array(ProductReviewDataSelection::IDENTIFIER, $entityGroupNames, true));
    }

    public function getPremapping(Context $context, MigrationContextInterface $migrationContext): PremappingStruct
    {
        $this->fillConnectionPremappingDictionary($migrationContext);
        $choices = $this->getChoices($context);
        $mapping = $this->getMapping($migrationContext);
        $this->setPreselection($mapping, $context);

        return new PremappingStruct(self::getMappingName(), $mapping, $choices);
    }

    /**
     * @return PremappingEntityStruct[]
     */
    private function getMapping(MigrationContextInterface $migrationContext): array
    {
        /** @var MagentoGatewayInterface $gateway */
        $gateway = $this->gatewayRegistry->getGateway($migrationContext);

        $salutations = $gateway->readGenders($migrationContext);

        $entityData = [];
        foreach ($salutations as $salutation) {
            $uuid = '';
            if (isset($this->connectionPremappingDictionary[$salutation['option_id']])) {
                $uuid = $this->connectionPremappingDictionary[$salutation['option_id']]['destinationUuid'];

                if (!isset($this->choiceUuids[$uuid])) {
                    $uuid = '';
                }
            }

            if (!isset($salutation['value'])) {
                continue;
            }

            $entityData[] = new PremappingEntityStruct($salutation['option_id'], $salutation['value'], $uuid);
        }

        $uuid = '';
        if (isset($this->connectionPremappingDictionary['default_salutation'])) {
            $uuid = $this->connectionPremappingDictionary['default_salutation']['destinationUuid'];

            if (!isset($this->choiceUuids[$uuid])) {
                $uuid = '';
            }
        }

        $entityData[] = new PremappingEntityStruct('default_salutation', 'Standard salutation', $uuid);
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
        $criteria->addSorting(new FieldSorting('salutationKey'));
        $salutations = $this->salutationRepo->search($criteria, $context);

        $choices = [];
        /** @var SalutationEntity $salutation */
        foreach ($salutations as $salutation) {
            $id = $salutation->getId();
            $choices[] = new PremappingChoiceStruct($id, (string) $salutation->getSalutationKey());
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
    private function setPreselection(array $mapping, Context $context): void
    {
        foreach ($mapping as $item) {
            if ($item->getDestinationUuid() !== '') {
                continue;
            }

            $preselectionValue = $this->getPreselectionValue($item->getSourceId(), $context);

            if ($preselectionValue !== null) {
                $item->setDestinationUuid($preselectionValue);
            }
        }
    }

    private function getPreselectionValue(string $sourceId, Context $context): ?string
    {
        $preselectionValue = null;
        switch ($sourceId) {
            case '1':
                $criteria = new Criteria();
                $criteria->addFilter(new EqualsFilter('salutationKey', 'mr'));
                /** @var SalutationEntity|null $salutation */
                $salutation = $this->salutationRepo->search($criteria, $context)->first();

                if ($salutation !== null) {
                    $preselectionValue = $salutation->getId();
                }

                break;
            case '2':
                $criteria = new Criteria();
                $criteria->addFilter(new EqualsFilter('salutationKey', 'mrs'));
                $salutation = $this->salutationRepo->search($criteria, $context)->first();
                if ($salutation !== null) {
                    $preselectionValue = $salutation->getId();
                }

                break;
            default:
                $criteria = new Criteria();
                $criteria->addFilter(new EqualsFilter('salutationKey', 'not_specified'));
                /** @var SalutationEntity|null $salutation */
                $salutation = $this->salutationRepo->search($criteria, $context)->first();
                if ($salutation !== null) {
                    $preselectionValue = $salutation->getId();
                }

                break;
        }

        return $preselectionValue;
    }
}
