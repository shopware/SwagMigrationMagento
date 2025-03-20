<?php declare(strict_types=1);
/*
 * (c) shopware AG <info@shopware.com>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Swag\MigrationMagento\Test;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;

/**
 * @internal
 */
trait LookupHelperTrait
{
    use KernelTestBehaviour;

    protected function getLanguageIdByLocaleCode(string $localCode): ?string
    {
        $localeRepository = $this->getContainer()->get('locale.repository');
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('code', $localCode));
        $criteria->setLimit(1);

        $localeId = $localeRepository->searchIds($criteria, Context::createDefaultContext())->firstId();
        if ($localeId === null) {
            return null;
        }

        $languageRepository = $this->getContainer()->get('language.repository');
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('localeId', $localeId));
        $criteria->setLimit(1);

        return $languageRepository->searchIds($criteria, Context::createDefaultContext())->firstId();
    }

    protected function getCurrencyIdByCode(string $code): ?string
    {
        $currencyRepository = $this->getContainer()->get('currency.repository');
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('isoCode', $code));
        $criteria->setLimit(1);

        return $currencyRepository->searchIds($criteria, Context::createDefaultContext())->firstId();
    }

    protected function getCountryStateIdByCode(string $code): ?string
    {
        $countryStateRepository = $this->getContainer()->get('country_state.repository');
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('shortCode', $code));
        $criteria->setLimit(1);

        return $countryStateRepository->searchIds($criteria, Context::createDefaultContext())->firstId();
    }
}
