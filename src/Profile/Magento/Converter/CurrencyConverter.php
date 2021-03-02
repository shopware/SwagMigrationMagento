<?php declare(strict_types=1);
/*
 * (c) shopware AG <info@shopware.com>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Swag\MigrationMagento\Profile\Magento\Converter;

use Shopware\Core\Framework\Context;
use Swag\MigrationMagento\Migration\Mapping\MagentoMappingServiceInterface;
use Swag\MigrationMagento\Migration\Mapping\Registry\CurrencyRegistry;
use SwagMigrationAssistant\Migration\Converter\ConvertStruct;
use SwagMigrationAssistant\Migration\DataSelection\DefaultEntities;
use SwagMigrationAssistant\Migration\MigrationContextInterface;

abstract class CurrencyConverter extends MagentoConverter
{
    /**
     * @var MagentoMappingServiceInterface
     */
    protected $mappingService;

    /**
     * @var string
     */
    protected $connectionId;

    /**
     * @var string
     */
    protected $oldIdentifier;

    public function getSourceIdentifier(array $data): string
    {
        return $data['isoCode'];
    }

    public function convert(array $data, Context $context, MigrationContextInterface $migrationContext): ConvertStruct
    {
        $this->oldIdentifier = $data['isoCode'];
        $currencyValue = CurrencyRegistry::get($this->oldIdentifier);

        if ($currencyValue === null) {
            return new ConvertStruct(null, $data);
        }
        unset($data['isoCode']);

        $connection = $migrationContext->getConnection();
        $this->connectionId = '';
        if ($connection !== null) {
            $this->connectionId = $connection->getId();
        }

        $this->generateChecksum($data);
        $currencyUuid = $this->mappingService->getCurrencyUuid(
            $this->connectionId,
            $this->oldIdentifier,
            $context
        );

        if ($currencyUuid === null) {
            $this->mainMapping = $this->mappingService->getOrCreateMapping(
                $this->connectionId,
                DefaultEntities::CURRENCY,
                $this->oldIdentifier,
                $context,
                $this->checksum
            );
        } else {
            $this->mainMapping = $this->mappingService->getOrCreateMapping(
                $this->connectionId,
                DefaultEntities::CURRENCY,
                $this->oldIdentifier,
                $context,
                $this->checksum,
                null,
                $currencyUuid
            );
        }
        $currencyUuid = $this->mainMapping['entityUuid'];

        $defaultCurrencyMapping = $this->mappingService->getOrCreateMapping(
            $this->connectionId,
            DefaultEntities::CURRENCY,
            'default_currency',
            $context,
            null,
            null,
            $currencyUuid
        );
        $this->mappingIds[] = $defaultCurrencyMapping['id'];

        $converted = [];
        $converted['id'] = $currencyUuid;
        $converted['name'] = $currencyValue['name'];
        $converted['symbol'] = $currencyValue['symbol'];
        $converted['isoCode'] = $this->oldIdentifier;
        $converted['shortName'] = $this->oldIdentifier;

        $converted['itemRounding'] = [
            'decimals' => $context->getRounding()->getDecimals(),
            'interval' => 0.01,
            'roundForNet' => true,
        ];

        $converted['totalRounding'] = $converted['itemRounding'];

        /*
         * Todo: Migrate currency factor
         */
        $converted['factor'] = 1.0;
        unset($data['isBaseCurrency']);

        foreach ($currencyValue['translations'] as $key => $value) {
            $languageUuid = $currencyUuid;
            if ($key !== $this->oldIdentifier) {
                $uuid = $this->mappingService->getLanguageUuid($this->connectionId, $key, $context);

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

        $this->updateMainMapping($migrationContext, $context);

        if (empty($data)) {
            $data = null;
        }

        return new ConvertStruct($converted, $data, $this->mainMapping['id']);
    }
}
