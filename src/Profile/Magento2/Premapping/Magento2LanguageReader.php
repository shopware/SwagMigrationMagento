<?php declare(strict_types=1);
/*
 * (c) shopware AG <info@shopware.com>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Swag\MigrationMagento\Profile\Magento2\Premapping;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\Language\LanguageEntity;
use SwagMigrationAssistant\Migration\MigrationContextInterface;
use SwagMigrationAssistant\Migration\Premapping\AbstractPremappingReader;
use SwagMigrationAssistant\Migration\Premapping\PremappingChoiceStruct;
use SwagMigrationAssistant\Migration\Premapping\PremappingEntityStruct;
use SwagMigrationAssistant\Migration\Premapping\PremappingStruct;

abstract class Magento2LanguageReader extends AbstractPremappingReader
{
    protected const MAPPING_NAME = 'language';

    /**
     * @var EntityRepositoryInterface
     */
    protected $languageRepo;

    /**
     * @var array
     */
    protected $preselectionDictionary;

    public function __construct(
        EntityRepositoryInterface $countryRepo
    ) {
        $this->languageRepo = $countryRepo;
    }

    public static function getMappingName(): string
    {
        return self::MAPPING_NAME;
    }

    public function getPremapping(Context $context, MigrationContextInterface $migrationContext): PremappingStruct
    {
        $this->fillConnectionPremappingDictionary($migrationContext);
        $mapping = $this->getMapping();
        $choices = $this->getChoices($context);
        $this->setPreselection($mapping);

        return new PremappingStruct(self::getMappingName(), $mapping, $choices);
    }

    protected function getMapping(): array
    {
        $uuid = '';
        if (isset($this->connectionPremappingDictionary['default_language'])) {
            $uuid = $this->connectionPremappingDictionary['default_language']['destinationUuid'];
        }

        $entityData = [];
        $entityData[] = new PremappingEntityStruct('default_language', 'Standard language', $uuid);
        \usort($entityData, function (PremappingEntityStruct $item1, PremappingEntityStruct $item2) {
            return \strcmp($item1->getDescription(), $item2->getDescription());
        });

        return $entityData;
    }

    /**
     * @return PremappingChoiceStruct[]
     */
    protected function getChoices(Context $context): array
    {
        /** @var LanguageEntity[] $langauges */
        $langauges = $this->languageRepo->search(new Criteria(), $context)->getElements();

        $choices = [];
        foreach ($langauges as $language) {
            $this->preselectionDictionary[$language->getName()] = $language->getId();
            $choices[] = new PremappingChoiceStruct($language->getId(), $language->getName());
        }
        \usort($choices, function (PremappingChoiceStruct $item1, PremappingChoiceStruct $item2) {
            return \strcmp($item1->getDescription(), $item2->getDescription());
        });

        return $choices;
    }

    /**
     * @param PremappingEntityStruct[] $mapping
     */
    protected function setPreselection(array $mapping): void
    {
        foreach ($mapping as $item) {
            if ($item->getDestinationUuid() !== '' || !isset($this->preselectionDictionary[$item->getSourceId()])) {
                continue;
            }

            $item->setDestinationUuid($this->preselectionDictionary[$item->getSourceId()]);
        }
    }
}
