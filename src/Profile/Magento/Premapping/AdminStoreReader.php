<?php declare(strict_types=1);
/*
 * (c) shopware AG <info@shopware.com>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Swag\MigrationMagento\Profile\Magento\Premapping;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
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

abstract class AdminStoreReader extends AbstractPremappingReader
{
    private const MAPPING_NAME = 'admin_store';

    /**
     * @var string[]
     */
    protected array $preselectionDictionary = [];

    private GatewayRegistryInterface $gatewayRegistry;

    /**
     * @var string[]
     */
    private array $choiceUuids;

    public function __construct(
        GatewayRegistryInterface $gatewayRegistry,
    ) {
        $this->gatewayRegistry = $gatewayRegistry;
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
        $choices = $this->getChoices($migrationContext);
        $mapping = $this->getMapping();

        return new PremappingStruct(self::getMappingName(), $mapping, $choices);
    }

    /**
     * @return PremappingEntityStruct[]
     */
    private function getMapping(): array
    {
        $uuid = '';
        if (isset($this->connectionPremappingDictionary[self::MAPPING_NAME])) {
            $uuid = $this->connectionPremappingDictionary[self::MAPPING_NAME]['destinationUuid'];

            if (!isset($this->choiceUuids[$uuid])) {
                $uuid = '';
            }
        }

        return [
            new PremappingEntityStruct(self::MAPPING_NAME, 'Admin store replacement', $uuid)
        ];
    }

    /**
     * @return PremappingChoiceStruct[]
     */
    private function getChoices(MigrationContextInterface $migrationContext): array
    {
        /** @var MagentoGatewayInterface $gateway */
        $gateway = $this->gatewayRegistry->getGateway($migrationContext);

        $stores = $gateway->readStores($migrationContext);

        $choices = [];
        foreach ($stores as $store) {
            $storeId = $store['store_id'];
            $choices[] = new PremappingChoiceStruct($storeId, $store['name']);
            $this->choiceUuids[$storeId] = $storeId;
        }
        \usort($choices, function (PremappingChoiceStruct $item1, PremappingChoiceStruct $item2) {
            return \strcmp($item1->getDescription(), $item2->getDescription());
        });

        return $choices;
    }
}
