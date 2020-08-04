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
use Shopware\Core\System\Country\CountryEntity;
use SwagMigrationAssistant\Migration\MigrationContextInterface;
use SwagMigrationAssistant\Migration\Premapping\AbstractPremappingReader;
use SwagMigrationAssistant\Migration\Premapping\PremappingChoiceStruct;
use SwagMigrationAssistant\Migration\Premapping\PremappingEntityStruct;
use SwagMigrationAssistant\Migration\Premapping\PremappingStruct;

abstract class Magento2CountryReader extends AbstractPremappingReader
{
    private const MAPPING_NAME = 'country';

    /**
     * @var EntityRepositoryInterface
     */
    private $countryRepo;

    /**
     * @var array
     */
    private $preselectionDictionary;

    public function __construct(
        EntityRepositoryInterface $countryRepo
    ) {
        $this->countryRepo = $countryRepo;
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
        if (isset($this->connectionPremappingDictionary['default_country'])) {
            $uuid = $this->connectionPremappingDictionary['default_country']['destinationUuid'];
        }

        $entityData = [];
        $entityData[] = new PremappingEntityStruct('default_country', 'Standard country', $uuid);
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
        /** @var CountryEntity[] $countries */
        $countries = $this->countryRepo->search(new Criteria(), $context)->getElements();

        $choices = [];
        foreach ($countries as $country) {
            $countryName = $country->getName();
            if ($countryName === null) {
                continue;
            }

            $this->preselectionDictionary[$countryName] = $country->getId();
            $choices[] = new PremappingChoiceStruct($country->getId(), $countryName);
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
