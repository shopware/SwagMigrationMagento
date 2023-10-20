<?php declare(strict_types=1);
/*
 * (c) shopware AG <info@shopware.com>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Swag\MigrationMagento\Migration\Mapping;

use Shopware\Core\Checkout\Document\Aggregate\DocumentType\DocumentTypeEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStates;
use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Core\Content\Cms\CmsPageEntity;
use Shopware\Core\Content\Media\Aggregate\MediaDefaultFolder\MediaDefaultFolderEntity;
use Shopware\Core\Content\Media\Aggregate\MediaThumbnailSize\MediaThumbnailSizeEntity;
use Shopware\Core\Content\Rule\RuleEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriterInterface;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Country\CountryEntity;
use Shopware\Core\System\Currency\CurrencyEntity;
use Shopware\Core\System\DeliveryTime\DeliveryTimeEntity;
use Shopware\Core\System\Language\LanguageEntity;
use Shopware\Core\System\Locale\LocaleEntity;
use Shopware\Core\System\NumberRange\NumberRangeEntity;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateEntity;
use Shopware\Core\System\StateMachine\StateMachineEntity;
use Shopware\Core\System\Tax\TaxEntity;
use SwagMigrationAssistant\Migration\DataSelection\DefaultEntities;
use SwagMigrationAssistant\Migration\Mapping\MappingService;
use SwagMigrationAssistant\Migration\Mapping\SwagMigrationMappingEntity;

#[Package('services-settings')]
class MagentoMappingService extends MappingService implements MagentoMappingServiceInterface
{
    /**
     * @param EntityRepository<EntityCollection<SwagMigrationMappingEntity>> $migrationMappingRepo
     * @param EntityRepository<EntityCollection<LocaleEntity>> $localeRepository
     * @param EntityRepository<EntityCollection<LanguageEntity>> $languageRepository
     * @param EntityRepository<EntityCollection<CountryEntity>> $countryRepository
     * @param EntityRepository<EntityCollection<CurrencyEntity>> $currencyRepository
     * @param EntityRepository<EntityCollection<TaxEntity>> $taxRepo
     * @param EntityRepository<EntityCollection<NumberRangeEntity>> $numberRangeRepo
     * @param EntityRepository<EntityCollection<RuleEntity>> $ruleRepo
     * @param EntityRepository<EntityCollection<MediaThumbnailSizeEntity>> $thumbnailSizeRepo
     * @param EntityRepository<EntityCollection<MediaDefaultFolderEntity>> $mediaDefaultRepo
     * @param EntityRepository<EntityCollection<CategoryEntity>> $categoryRepo
     * @param EntityRepository<EntityCollection<CmsPageEntity>> $cmsPageRepo
     * @param EntityRepository<EntityCollection<DeliveryTimeEntity>> $deliveryTimeRepo
     * @param EntityRepository<EntityCollection<DocumentTypeEntity>> $documentTypeRepo
     * @param EntityRepository<EntityCollection<StateMachineEntity>> $stateMachineRepo
     * @param EntityRepository<EntityCollection<StateMachineStateEntity>> $stateMachineStateRepo
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
        EntityWriterInterface $entityWriter,
        EntityDefinition $mappingDefinition,
        private EntityRepository $stateMachineRepo,
        private EntityRepository $stateMachineStateRepo,
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
            $entityWriter,
            $mappingDefinition
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
