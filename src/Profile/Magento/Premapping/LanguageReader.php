<?php declare(strict_types=1);

namespace Swag\MigrationMagento\Profile\Magento\Premapping;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Language\LanguageEntity;
use Swag\MigrationMagento\Profile\Magento\Gateway\Local\Magento19LocalGateway;
use Swag\MigrationMagento\Profile\Magento\Magento19Profile;
use SwagMigrationAssistant\Migration\Gateway\GatewayRegistryInterface;
use SwagMigrationAssistant\Migration\MigrationContextInterface;
use SwagMigrationAssistant\Migration\Premapping\AbstractPremappingReader;
use SwagMigrationAssistant\Migration\Premapping\PremappingChoiceStruct;
use SwagMigrationAssistant\Migration\Premapping\PremappingEntityStruct;
use SwagMigrationAssistant\Migration\Premapping\PremappingStruct;
use SwagMigrationAssistant\Profile\Shopware\Gateway\ShopwareGatewayInterface;

class LanguageReader extends AbstractPremappingReader
{
    private const MAPPING_NAME = 'language';

    /**
     * @var GatewayRegistryInterface
     */
    private $gatewayRegistry;

    /**
     * @var EntityRepositoryInterface
     */
    private $languageRepo;

    /**
     * @var array
     */
    private $preselectionDictionary;

    public static function getMappingName(): string
    {
        return self::MAPPING_NAME;
    }

    public function __construct(
        GatewayRegistryInterface $gatewayRegistry,
        EntityRepositoryInterface $languageRepo
    ) {

        $this->gatewayRegistry = $gatewayRegistry;
        $this->languageRepo = $languageRepo;
    }

    /**
     * @param string[] $entityGroupNames
     */
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

        $preMappingData = $gateway->readTable($migrationContext, 'core_store', ['store_id != 0']);

        $entityData = [];
        foreach ($preMappingData as $data) {
            if ((int) $data['store_id'] === 0) {
                continue;
            }

            $uuid = '';
            if (isset($this->connectionPremappingDictionary[$data['store_id']])) {
                $uuid = $this->connectionPremappingDictionary[$data['store_id']]['destinationUuid'];
            }

            $entityData[] = new PremappingEntityStruct($data['store_id'], $data['name'], $uuid);
        }

        return $entityData;
    }

    /**
     * @return PremappingChoiceStruct[]
     */
    private function getChoices(Context $context): array
    {
        /** @var LanguageEntity[] $languages */
        $languages = $this->languageRepo->search(new Criteria(), $context)->getElements();

        $choices = [];
        foreach ($languages as $language) {
            $this->preselectionDictionary[$language->getName()] = $language->getId();
            $choices[] = new PremappingChoiceStruct($language->getId(), $language->getName());
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