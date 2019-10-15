<?php declare(strict_types=1);

namespace Swag\MigrationMagento\Profile\Magento\Converter;

use Shopware\Core\Framework\Context;
use Swag\MigrationMagento\Migration\Mapping\MagentoMappingServiceInterface;
use Swag\MigrationMagento\Migration\Mapping\Registry\CurrencyRegistry;
use Swag\MigrationMagento\Profile\Magento\DataSelection\DataSet\CurrencyDataSet;
use Swag\MigrationMagento\Profile\Magento\Magento19Profile;
use SwagMigrationAssistant\Migration\Converter\ConvertStruct;
use SwagMigrationAssistant\Migration\DataSelection\DefaultEntities;
use SwagMigrationAssistant\Migration\MigrationContextInterface;

class CurrencyConverter extends MagentoConverter
{
    /**
     * @var MagentoMappingServiceInterface
     */
    protected $mappingService;

    /**
     * @var string
     */
    protected $connectionId;

    public function supports(MigrationContextInterface $migrationContext): bool
    {
        return $migrationContext->getProfile()->getName() === Magento19Profile::PROFILE_NAME
            && $migrationContext->getDataSet()::getEntity() === CurrencyDataSet::getEntity();
    }

    public function getSourceIdentifier(array $data): string
    {
        return $data['isoCode'];
    }

    public function convert(array $data, Context $context, MigrationContextInterface $migrationContext): ConvertStruct
    {
        $this->connectionId = $migrationContext->getConnection()->getId();
        $currencyValue = CurrencyRegistry::get($data['isoCode']);

        if ($currencyValue === null) {
            return new ConvertStruct(null, $data);
        }

        $this->generateChecksum($data);
        $currencyUuid = $this->mappingService->getCurrencyUuid(
            $this->connectionId,
            $data['isoCode'],
            $context
        );

        if ($currencyUuid === null) {
            $this->mainMapping = $this->mappingService->getOrCreateMapping(
                $migrationContext->getConnection()->getId(),
                DefaultEntities::CURRENCY,
                $data['isoCode'],
                $context
            );
        } else {
            $this->mainMapping = $this->mappingService->getOrCreateMapping(
                $this->connectionId,
                DefaultEntities::CURRENCY,
                $data['isoCode'],
                $context,
                null,
                null,
                $currencyUuid
            );
        }

        $this->mappingIds[] = $this->mainMapping['id'];
        $currencyUuid = $this->mainMapping['entityUuid'];

        $converted['id'] = $currencyUuid;
        $converted['name'] = $currencyValue['name'];
        $converted['symbol'] = $currencyValue['symbol'];
        $converted['isoCode'] = $data['isoCode'];
        $converted['shortName'] = $data['isoCode'];
        $converted['decimalPrecision'] = $context->getCurrencyPrecision();

        /*
         * Todo: Migrate currency factor
         */
        $converted['factor'] = 1.0;

        foreach ($currencyValue['translations'] as $key => $value) {
            $languageUuid = $currencyUuid;
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

        $this->updateMainMapping($migrationContext, $context);

        return new ConvertStruct($converted, $data, $this->mainMapping['id']);
    }
}
