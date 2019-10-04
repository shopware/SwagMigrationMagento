<?php declare(strict_types=1);

namespace Swag\MigrationMagento\Migration\Mapping;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\System\Country\CountryEntity;
use SwagMigrationAssistant\Migration\DataSelection\DefaultEntities;
use SwagMigrationAssistant\Migration\Mapping\MappingService;

class MagentoMappingService extends MappingService implements MagentoMappingServiceInterface
{
    public function getMagentoCountryUuid(string $iso, string $connectionId, Context $context): ?string
    {
        $countryUuid = $this->getUuid($connectionId, DefaultEntities::COUNTRY, $iso, $context);

        if ($countryUuid !== null) {
            return $countryUuid;
        }

        /** @var EntitySearchResult $result */
        $result = $context->disableCache(function (Context $context) use ($iso) {
            $criteria = new Criteria();
            $criteria->addFilter(new EqualsFilter('iso', $iso));
            $criteria->setLimit(1);

            return $this->countryRepository->search($criteria, $context);
        });

        if ($result->getTotal() > 0) {
            /** @var CountryEntity $element */
            $element = $result->getEntities()->first();

            $countryUuid = $element->getId();

            $this->saveMapping(
                [
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
}