<?php declare(strict_types=1);
/*
 * (c) shopware AG <info@shopware.com>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Swag\MigrationMagento\Profile\Magento\Converter;

use Shopware\Core\Framework\Context;
use Swag\MigrationMagento\Migration\Mapping\MagentoMappingServiceInterface;
use Swag\MigrationMagento\Migration\Mapping\Registry\CountryRegistry;
use Swag\MigrationMagento\Profile\Magento\DataSelection\DataSet\CountryDataSet;
use Swag\MigrationMagento\Profile\Magento\Magento19Profile;
use SwagMigrationAssistant\Migration\Converter\ConvertStruct;
use SwagMigrationAssistant\Migration\DataSelection\DefaultEntities;
use SwagMigrationAssistant\Migration\MigrationContextInterface;

class CountryConverter extends MagentoConverter
{
    /**
     * @var MagentoMappingServiceInterface
     */
    protected $mappingService;

    /**
     * @var string
     */
    protected $connectionId;

    public function getSourceIdentifier(array $data): string
    {
        return $data['isoCode'];
    }

    public function supports(MigrationContextInterface $migrationContext): bool
    {
        return $migrationContext->getProfile()->getName() === Magento19Profile::PROFILE_NAME
            && $migrationContext->getDataSet()::getEntity() === CountryDataSet::getEntity();
    }

    public function convert(array $data, Context $context, MigrationContextInterface $migrationContext): ConvertStruct
    {
        $this->connectionId = $migrationContext->getConnection()->getId();
        $countryValue = CountryRegistry::get($data['isoCode']);

        if ($countryValue === null) {
            return new ConvertStruct(null, $data);
        }

        $this->generateChecksum($data);
        $countryUuid = $this->mappingService->getMagentoCountryUuid(
            $data['isoCode'],
            $this->connectionId,
            $context
        );

        if ($countryUuid === null) {
            $this->mainMapping = $this->mappingService->getOrCreateMapping(
                $migrationContext->getConnection()->getId(),
                DefaultEntities::COUNTRY,
                $data['isoCode'],
                $context,
                $this->checksum
            );
        } else {
            $this->mainMapping = $this->mappingService->getOrCreateMapping(
                $this->connectionId,
                DefaultEntities::COUNTRY,
                $data['isoCode'],
                $context,
                $this->checksum,
                null,
                $countryUuid
            );
        }
        $countryUuid = $this->mainMapping['entityUuid'];

        $converted['id'] = $countryUuid;
        $converted['name'] = $countryValue['name'];
        $converted['iso'] = $data['isoCode'];
        $converted['iso3'] = $countryValue['iso3'];
        $converted['active'] = true;

        foreach ($countryValue['translations'] as $key => $value) {
            $languageUuid = $countryUuid;
            if ($key !== $data['isoCode']) {
                $uuid = $this->mappingService->getLanguageUuid($this->connectionId, $key, $context);

                if ($uuid === null) {
                    continue;
                }
                $languageUuid = $uuid;
            }

            $localeTranslation['languageId'] = $languageUuid;
            $localeTranslation['name'] = $value;
            $converted['translations'][$languageUuid] = $localeTranslation;
        }
        unset($data['isoCode']);

        $this->updateMainMapping($migrationContext, $context);

        if (empty($data)) {
            $data = null;
        }

        return new ConvertStruct($converted, $data, $this->mainMapping['id']);
    }
}
