<?php declare(strict_types=1);
/*
 * (c) shopware AG <info@shopware.com>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Swag\MigrationMagento\Profile\Magento\Premapping;

use Shopware\Core\Checkout\Shipping\ShippingMethodEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Swag\MigrationMagento\Profile\Magento\MagentoProfileInterface;
use Swag\MigrationMagento\Profile\Magento19\Gateway\Local\Magento19LocalGateway;
use SwagMigrationAssistant\Migration\Gateway\GatewayRegistryInterface;
use SwagMigrationAssistant\Migration\MigrationContextInterface;
use SwagMigrationAssistant\Migration\Premapping\AbstractPremappingReader;
use SwagMigrationAssistant\Migration\Premapping\PremappingChoiceStruct;
use SwagMigrationAssistant\Migration\Premapping\PremappingEntityStruct;
use SwagMigrationAssistant\Migration\Premapping\PremappingStruct;

abstract class ShippingMethodReader extends AbstractPremappingReader
{
    private const MAPPING_NAME = 'shipping_method';

    /**
     * @var EntityRepositoryInterface
     */
    private $paymentMethodRepo;

    /**
     * @var GatewayRegistryInterface
     */
    private $gatewayRegistry;

    /**
     * @var array
     */
    private $preselectionDictionary;

    /**
     * @var string[]
     */
    private $choiceUuids;

    public function __construct(
        GatewayRegistryInterface $gatewayRegistry,
        EntityRepositoryInterface $paymentMethodRepo
    ) {
        $this->gatewayRegistry = $gatewayRegistry;
        $this->paymentMethodRepo = $paymentMethodRepo;
    }

    public static function getMappingName(): string
    {
        return self::MAPPING_NAME;
    }

    /**
     * @param string[] $entityGroupNames
     */
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

        $preMappingData = $gateway->readCarriers($migrationContext);

        $entityData = [];
        foreach ($preMappingData as $data) {
            $uuid = '';
            if (isset($this->connectionPremappingDictionary[$data['carrier_id']])) {
                $uuid = $this->connectionPremappingDictionary[$data['carrier_id']]['destinationUuid'];

                if (!isset($this->choiceUuids[$uuid])) {
                    $uuid = '';
                }
            }

            $entityData[] = new PremappingEntityStruct($data['carrier_id'], $data['value'] ?? $data['carrier_id'], $uuid);
        }

        $uuid = '';
        if (isset($this->connectionPremappingDictionary['default_shipping_method'])) {
            $uuid = $this->connectionPremappingDictionary['default_shipping_method']['destinationUuid'];

            if (!isset($this->choiceUuids[$uuid])) {
                $uuid = '';
            }
        }

        $entityData[] = new PremappingEntityStruct('default_shipping_method', 'Standard shipping method', $uuid);
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
        /** @var ShippingMethodEntity[] $shippingMethods */
        $shippingMethods = $this->paymentMethodRepo->search(new Criteria(), $context)->getElements();

        $choices = [];
        foreach ($shippingMethods as $shippingMethod) {
            $shippingMethodName = $shippingMethod->getName();
            if ($shippingMethodName === null) {
                continue;
            }

            $id = $shippingMethod->getId();
            $this->preselectionDictionary[$shippingMethodName] = $id;
            $choices[] = new PremappingChoiceStruct($id, $shippingMethodName);
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
