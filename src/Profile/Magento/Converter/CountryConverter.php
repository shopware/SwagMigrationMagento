<?php declare(strict_types=1);
/*
 * (c) shopware AG <info@shopware.com>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Swag\MigrationMagento\Profile\Magento\Converter;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Swag\MigrationMagento\Migration\Mapping\MagentoMappingServiceInterface;
use Swag\MigrationMagento\Migration\Mapping\Registry\CountryRegistry;
use SwagMigrationAssistant\Migration\Converter\ConvertStruct;
use SwagMigrationAssistant\Migration\DataSelection\DefaultEntities;
use SwagMigrationAssistant\Migration\Logging\LoggingServiceInterface;
use SwagMigrationAssistant\Migration\Mapping\Lookup\CountryLookup;
use SwagMigrationAssistant\Migration\Mapping\Lookup\LanguageLookup;
use SwagMigrationAssistant\Migration\Mapping\MappingServiceInterface;
use SwagMigrationAssistant\Migration\MigrationContextInterface;

#[Package('fundamentals@after-sales')]
abstract class CountryConverter extends MagentoConverter
{
    protected MappingServiceInterface|MagentoMappingServiceInterface $mappingService;

    protected string $connectionId;

    /**
     * @internal
     */
    public function __construct(
        MagentoMappingServiceInterface $mappingService,
        LoggingServiceInterface $loggingService,
        protected readonly LanguageLookup $languageLookup,
        protected readonly CountryLookup $countryLookup,
    ) {
        parent::__construct($mappingService, $loggingService);
    }

    public function getSourceIdentifier(array $data): string
    {
        return $data['isoCode'];
    }

    public function convert(array $data, Context $context, MigrationContextInterface $migrationContext): ConvertStruct
    {
        $countryValue = CountryRegistry::get($data['isoCode']);

        if ($countryValue === null) {
            return new ConvertStruct(null, $data);
        }

        $this->connectionId = $migrationContext->getConnection()->getId();

        $this->generateChecksum($data);
        $countryMapping = $this->mappingService->getMapping($this->connectionId, DefaultEntities::COUNTRY, $data['isoCode'], $context);
        if ($countryMapping !== null) {
            $countryUuid = $countryMapping['entityUuid'];
        } else {
            $countryUuid = $this->countryLookup->getByIso2($data['isoCode'], $context);

            if ($countryUuid !== null) {
                $this->mappingService->createMapping(
                    $this->connectionId,
                    DefaultEntities::COUNTRY,
                    $data['isoCode'],
                    $this->checksum,
                );
            }
        }

        if ($countryUuid === null) {
            $this->mainMapping = $this->mappingService->getOrCreateMapping(
                $this->connectionId,
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

        $converted = [];
        $converted['id'] = $countryUuid;
        $converted['name'] = $countryValue['name'];
        $converted['iso'] = $data['isoCode'];
        $converted['iso3'] = $countryValue['iso3'];
        $converted['active'] = true;

        foreach ($countryValue['translations'] as $key => $value) {
            $languageUuid = $countryUuid;
            if ($key !== $data['isoCode']) {
                $uuid = $this->languageLookup->get($key, $context);
                if ($uuid === null) {
                    continue;
                }
                $languageUuid = $uuid;
            }

            $localeTranslation = [];
            $localeTranslation['languageId'] = $languageUuid;
            $localeTranslation['name'] = $value;
            $converted['translations'][$languageUuid] = $localeTranslation;
        }
        unset($data['isoCode']);

        $this->updateMainMapping($migrationContext, $context);

        if (empty($data)) {
            $data = null;
        }

        return new ConvertStruct($converted, $data, $this->mainMapping['id'] ?? null);
    }
}
