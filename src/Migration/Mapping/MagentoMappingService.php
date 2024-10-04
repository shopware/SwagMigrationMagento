<?php declare(strict_types=1);
/*
 * (c) shopware AG <info@shopware.com>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Swag\MigrationMagento\Migration\Mapping;

use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Document\Aggregate\DocumentType\DocumentTypeCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStates;
use Shopware\Core\Content\Category\CategoryCollection;
use Shopware\Core\Content\Cms\CmsPageCollection;
use Shopware\Core\Content\Media\Aggregate\MediaDefaultFolder\MediaDefaultFolderCollection;
use Shopware\Core\Content\Media\Aggregate\MediaThumbnailSize\MediaThumbnailSizeCollection;
use Shopware\Core\Content\Rule\RuleCollection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriterInterface;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Country\Aggregate\CountryState\CountryStateCollection;
use Shopware\Core\System\Country\CountryCollection;
use Shopware\Core\System\Country\CountryEntity;
use Shopware\Core\System\Currency\CurrencyCollection;
use Shopware\Core\System\DeliveryTime\DeliveryTimeCollection;
use Shopware\Core\System\Language\LanguageCollection;
use Shopware\Core\System\Locale\LocaleCollection;
use Shopware\Core\System\NumberRange\NumberRangeCollection;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateCollection;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateEntity;
use Shopware\Core\System\StateMachine\StateMachineCollection;
use Shopware\Core\System\StateMachine\StateMachineEntity;
use Shopware\Core\System\Tax\TaxCollection;
use Shopware\Core\System\Tax\TaxEntity;
use SwagMigrationAssistant\Migration\DataSelection\DefaultEntities;
use SwagMigrationAssistant\Migration\Mapping\MappingService;
use SwagMigrationAssistant\Migration\Mapping\SwagMigrationMappingCollection;

#[Package('services-settings')]
class MagentoMappingService extends MappingService implements MagentoMappingServiceInterface
{
    /**
     * @param EntityRepository<SwagMigrationMappingCollection> $migrationMappingRepo
     * @param EntityRepository<LocaleCollection> $localeRepository
     * @param EntityRepository<LanguageCollection> $languageRepository
     * @param EntityRepository<CountryCollection> $countryRepository
     * @param EntityRepository<CurrencyCollection> $currencyRepository
     * @param EntityRepository<TaxCollection> $taxRepo
     * @param EntityRepository<NumberRangeCollection> $numberRangeRepo
     * @param EntityRepository<RuleCollection> $ruleRepo
     * @param EntityRepository<MediaThumbnailSizeCollection> $thumbnailSizeRepo
     * @param EntityRepository<MediaDefaultFolderCollection> $mediaDefaultRepo
     * @param EntityRepository<CategoryCollection> $categoryRepo
     * @param EntityRepository<CmsPageCollection> $cmsPageRepo
     * @param EntityRepository<DeliveryTimeCollection> $deliveryTimeRepo
     * @param EntityRepository<DocumentTypeCollection> $documentTypeRepo
     * @param EntityRepository<StateMachineCollection> $stateMachineRepo
     * @param EntityRepository<StateMachineStateCollection> $stateMachineStateRepo
     * @param EntityRepository<CountryStateCollection> $countryStateRepo
     */
    public function __construct(
        EntityRepository $migrationMappingRepo,
        EntityRepository $localeRepository,
        EntityRepository $languageRepository,
        EntityRepository $countryRepository,
        EntityRepository $currencyRepository,
        EntityRepository $taxRepo,
        EntityRepository $numberRangeRepo,
        EntityRepository $ruleRepo,
        EntityRepository $thumbnailSizeRepo,
        EntityRepository $mediaDefaultRepo,
        EntityRepository $categoryRepo,
        EntityRepository $cmsPageRepo,
        EntityRepository $deliveryTimeRepo,
        EntityRepository $documentTypeRepo,
        EntityRepository $countryStateRepo,
        EntityWriterInterface $entityWriter,
        EntityDefinition $mappingDefinition,
        protected LoggerInterface $logger,
        private readonly EntityRepository $stateMachineRepo,
        private readonly EntityRepository $stateMachineStateRepo,
    ) {
        parent::__construct(
            $migrationMappingRepo,
            $localeRepository,
            $languageRepository,
            $countryRepository,
            $currencyRepository,
            $taxRepo,
            $numberRangeRepo,
            $ruleRepo,
            $thumbnailSizeRepo,
            $mediaDefaultRepo,
            $categoryRepo,
            $cmsPageRepo,
            $deliveryTimeRepo,
            $documentTypeRepo,
            $countryStateRepo,
            $entityWriter,
            $mappingDefinition,
            $logger
        );
    }

    public function getMagentoCountryUuid(string $iso, string $connectionId, Context $context): ?string
    {
        $countryUuid = $this->getMapping($connectionId, DefaultEntities::COUNTRY, $iso, $context);

        if ($countryUuid !== null) {
            return $countryUuid['entityUuid'];
        }

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('iso', $iso));
        $criteria->setLimit(1);

        $result = $this->countryRepository->search($criteria, $context);

        if ($result->getTotal() > 0) {
            /** @var CountryEntity $element */
            $element = $result->getEntities()->first();

            $countryUuid = $element->getId();

            $this->saveMapping(
                [
                    'id' => Uuid::randomHex(),
                    'connectionId' => $connectionId,
                    'entity' => DefaultEntities::COUNTRY,
                    'oldIdentifier' => $iso,
                    'entityUuid' => $countryUuid,
                ]
            );

            return $countryUuid;
        }

        return null;
    }

    public function getTransactionStateUuid(string $state, Context $context): ?string
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('technicalName', OrderTransactionStates::STATE_MACHINE));

        /** @var StateMachineEntity|null $stateMachine */
        $stateMachine = $this->stateMachineRepo->search($criteria, $context)->first();

        if ($stateMachine === null) {
            return null;
        }

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('stateMachineId', $stateMachine->getId()));
        $criteria->addFilter(new EqualsFilter('technicalName', $state));

        /** @var StateMachineStateEntity|null $state */
        $state = $this->stateMachineStateRepo->search($criteria, $context)->first();

        if ($state === null) {
            return null;
        }

        return $state->getId();
    }

    public function getTaxRate(string $uuid, Context $context): ?float
    {
        /** @var TaxEntity|null $tax */
        $tax = $this->taxRepo->search(new Criteria([$uuid]), $context)->first();

        if ($tax === null) {
            return null;
        }

        return $tax->getTaxRate();
    }
}
