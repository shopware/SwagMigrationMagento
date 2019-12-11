<?php declare(strict_types=1);
/*
 * (c) shopware AG <info@shopware.com>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Swag\MigrationMagento\Profile\Magento\Premapping;

use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Swag\MigrationMagento\Profile\Magento\Gateway\Local\Magento19LocalGateway;
use Swag\MigrationMagento\Profile\Magento\Magento19Profile;
use SwagMigrationAssistant\Migration\Gateway\GatewayRegistryInterface;
use SwagMigrationAssistant\Migration\MigrationContextInterface;
use SwagMigrationAssistant\Migration\Premapping\AbstractPremappingReader;
use SwagMigrationAssistant\Migration\Premapping\PremappingChoiceStruct;
use SwagMigrationAssistant\Migration\Premapping\PremappingEntityStruct;
use SwagMigrationAssistant\Migration\Premapping\PremappingStruct;

class PaymentMethodReader extends AbstractPremappingReader
{
    private const MAPPING_NAME = 'payment_method';

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

    public function supports(MigrationContextInterface $migrationContext, array $entityGroupNames): bool
    {
        return $migrationContext->getProfile() instanceof Magento19Profile;
    }

    public function getPremapping(Context $context, MigrationContextInterface $migrationContext): PremappingStruct
    {
        $this->fillConnectionPremappingDictionary($migrationContext);
        $mapping = $this->getMapping($migrationContext);
        $choices = $this->getChoices($context);
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
            }

            $entityData[] = new PremappingEntityStruct($data['payment_id'], $data['value'], $uuid);
        }

        $uuid = '';
        if (isset($this->connectionPremappingDictionary['default_payment_method'])) {
            $uuid = $this->connectionPremappingDictionary['default_payment_method']['destinationUuid'];
        }

        $entityData[] = new PremappingEntityStruct('default_payment_method', 'Standard payment method', $uuid);

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
            $this->preselectionDictionary[$paymentMethod->getName()] = $paymentMethod->getId();
            $choices[] = new PremappingChoiceStruct($paymentMethod->getId(), $paymentMethod->getName());
        }

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
