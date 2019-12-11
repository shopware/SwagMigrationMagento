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
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\System\Tax\TaxEntity;
use Swag\MigrationMagento\Profile\Magento\DataSelection\ProductDataSelection;
use Swag\MigrationMagento\Profile\Magento\DataSelection\ProductReviewDataSelection;
use Swag\MigrationMagento\Profile\Magento\DataSelection\SeoUrlDataSelection;
use Swag\MigrationMagento\Profile\Magento\Gateway\Local\Magento19LocalGateway;
use Swag\MigrationMagento\Profile\Magento\Magento19Profile;
use SwagMigrationAssistant\Migration\Gateway\GatewayRegistryInterface;
use SwagMigrationAssistant\Migration\MigrationContextInterface;
use SwagMigrationAssistant\Migration\Premapping\AbstractPremappingReader;
use SwagMigrationAssistant\Migration\Premapping\PremappingChoiceStruct;
use SwagMigrationAssistant\Migration\Premapping\PremappingEntityStruct;
use SwagMigrationAssistant\Migration\Premapping\PremappingStruct;

class TaxReader extends AbstractPremappingReader
{
    private const MAPPING_NAME = 'tax';

    /**
     * @var GatewayRegistryInterface
     */
    private $gatewayRegistry;

    /**
     * @var EntityRepositoryInterface
     */
    private $taxRepo;

    /**
     * @var array
     */
    private $preselectionDictionary;

    public function __construct(
        GatewayRegistryInterface $gatewayRegistry,
        EntityRepositoryInterface $taxRepo
    ) {
        $this->gatewayRegistry = $gatewayRegistry;
        $this->taxRepo = $taxRepo;
    }

    public static function getMappingName(): string
    {
        return self::MAPPING_NAME;
    }

    public function supports(MigrationContextInterface $migrationContext, array $entityGroupNames): bool
    {
        return $migrationContext->getProfile() instanceof Magento19Profile
            && (in_array(ProductDataSelection::IDENTIFIER, $entityGroupNames, true)
            || in_array(ProductReviewDataSelection::IDENTIFIER, $entityGroupNames, true)
            || in_array(SeoUrlDataSelection::IDENTIFIER, $entityGroupNames, true));
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
        $preMappingData = $gateway->readTable($migrationContext, 'tax_class');

        $entityData = [];
        foreach ($preMappingData as $data) {
            $uuid = '';
            if (isset($this->connectionPremappingDictionary[$data['class_id']])) {
                $uuid = $this->connectionPremappingDictionary[$data['class_id']]['destinationUuid'];
            }

            $entityData[] = new PremappingEntityStruct($data['class_id'], $data['class_name'], $uuid);
        }

        return $entityData;
    }

    /**
     * @return PremappingChoiceStruct[]
     */
    private function getChoices(Context $context): array
    {
        $criteria = new Criteria();
        $criteria->addSorting(new FieldSorting('taxRate'));
        $taxes = $this->taxRepo->search($criteria, $context);

        $choices = [];
        /** @var TaxEntity $tax */
        foreach ($taxes as $tax) {
            $taxId = $tax->getId();
            $this->preselectionDictionary[$taxId] = $taxId;
            $choices[] = new PremappingChoiceStruct($taxId, $tax->getName());
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
