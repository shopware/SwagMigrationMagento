<?php declare(strict_types=1);
/*
 * (c) shopware AG <info@shopware.com>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Swag\MigrationMagento\Test\Mock\Migration\Mapping;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Language\LanguageEntity;
use Shopware\Core\System\Locale\LocaleEntity;
use Swag\MigrationMagento\Migration\Mapping\MagentoMappingService;
use SwagMigrationAssistant\Exception\LocaleNotFoundException;
use SwagMigrationAssistant\Migration\DataSelection\DefaultEntities;
use SwagMigrationAssistant\Migration\Mapping\SwagMigrationMappingDefinition;
use SwagMigrationAssistant\Migration\MigrationContextInterface;

class DummyMagentoMappingService extends MagentoMappingService
{
    public const DEFAULT_LANGUAGE_UUID = '20080911ffff4fffafffffff19830531';
    public const DEFAULT_LOCAL_UUID = '20080911ffff4fffafffffff19830531';

    public function __construct()
    {
    }

    public function createListItemMapping(
        string $connectionId,
        string $entityName,
        string $oldIdentifier,
        Context $context,
        ?array $additionalData = null,
        ?string $newUuid = null
    ): void {
        $uuid = Uuid::randomHex();
        if ($newUuid !== null) {
            $uuid = $newUuid;

            foreach ($this->writeArray as $item) {
                if (
                    $item['connectionId'] === $connectionId
                    && $item['entity'] === $entityName
                    && $item['oldIdentifier'] === $oldIdentifier
                    && $item['entityUuid'] === $newUuid
                ) {
                    return;
                }
            }
        }

        $this->saveListMapping(
            [
                'id' => Uuid::randomHex(),
                'connectionId' => $connectionId,
                'entity' => $entityName,
                'oldIdentifier' => $oldIdentifier,
                'entityUuid' => $uuid,
                'additionalData' => $additionalData,
            ]
        );
    }

    public function writeMapping(Context $context): void
    {
    }

    public function saveMapping(array $mapping): void
    {
        $entity = $mapping['entity'];
        $oldIdentifier = $mapping['oldIdentifier'];
        $this->mappings[\md5($entity . $oldIdentifier)] = $mapping;
    }

    public function getMapping(string $connectionId, string $entityName, string $oldIdentifier, Context $context): ?array
    {
        return $this->mappings[\md5($entityName . $oldIdentifier)] ?? null;
    }

    public function deleteDummyMapping(string $entityName, string $oldIdentifier): void
    {
        unset($this->mappings[\md5($entityName . $oldIdentifier)]);
    }

    public function getMappings(string $connectionId, string $entityName, array $ids, Context $context): EntitySearchResult
    {
        return new EntitySearchResult(SwagMigrationMappingDefinition::ENTITY_NAME, 0, new EntityCollection(), null, new Criteria(), $context);
    }

    public function getUuidsByEntity(string $connectionId, string $entityName, Context $context): array
    {
        return [];
    }

    public function getValue(string $connectionId, string $entityName, string $oldIdentifier, Context $context): ?string
    {
        return $this->values[$entityName][$oldIdentifier] ?? null;
    }

    public function pushValueMapping(string $connectionId, string $entity, string $oldIdentifier, string $value): void
    {
        $this->values[$entity][$oldIdentifier] = $value;
    }

    public function getUuidList(string $connectionId, string $entityName, string $identifier, Context $context): array
    {
        return isset($this->mappings[\md5($entityName . $identifier)])
            ? \array_column($this->mappings[\md5($entityName . $identifier)], 'entityUuid')
            : [];
    }

    public function updateMapping(
        string $connectionId,
        string $entityName,
        string $oldIdentifier,
        array $updateData,
        Context $context
    ): array {
        $mapping = $this->getMapping($connectionId, $entityName, $oldIdentifier, $context);

        if ($mapping === null) {
            return $this->createMapping(
                $connectionId,
                $entityName,
                $oldIdentifier,
                $updateData['checksum'] ?? null,
                $updateData['additionalData'] ?? null,
                $updateData['entityUuid'] ?? null
            );
        }

        $mapping = \array_merge($mapping, $updateData);
        $this->saveMapping($mapping);

        // required for tests
        if (isset($mapping['entityValue'])) {
            $this->values[$entityName][$oldIdentifier] = $mapping['entityValue'];
        }

        return $mapping;
    }

    public function deleteMapping(string $entityUuid, string $connectionId, Context $context): void
    {
        foreach ($this->writeArray as $writeMapping) {
            if ($writeMapping['connectionId'] === $connectionId && $writeMapping['entityUuid'] === $entityUuid) {
                unset($writeMapping);

                break;
            }
        }

        foreach ($this->mappings as $hash => $mapping) {
            if ($mapping['entityUuid'] === $entityUuid) {
                unset($this->mappings[$hash]);
            }
        }
    }

    public function pushMapping(string $connectionId, string $entity, string $oldIdentifier, string $uuid): void
    {
        $this->uuids[$entity][$oldIdentifier] = $uuid;
    }

    public function bulkDeleteMapping(array $mappingUuids, Context $context): void
    {
    }

    public function getLanguageUuid(string $connectionId, string $localeCode, Context $context, bool $withoutMapping = false): ?string
    {
        $languageMapping = $this->getMapping($connectionId, DefaultEntities::LANGUAGE, $localeCode, $context);

        if ($languageMapping !== null) {
            return $languageMapping['entityUuid'];
        }

        return null;
    }

    public function getLocaleUuid(string $connectionId, string $localeCode, Context $context): string
    {
        $localeMapping = $this->getMapping($connectionId, DefaultEntities::LOCALE, $localeCode, $context);

        if ($localeMapping !== null) {
            return $localeMapping['entityUuid'];
        }

        throw new LocaleNotFoundException($localeCode);
    }

    public function getMigratedSalesChannelUuids(string $connectionId, Context $context): array
    {
        return [];
    }

    public function getCountryUuid(string $oldIdentifier, string $iso, string $iso3, string $connectionId, Context $context): ?string
    {
        $countryMapping = $this->getMapping($connectionId, DefaultEntities::COUNTRY, $oldIdentifier, $context);

        if ($countryMapping !== null) {
            return $countryMapping['entityUuid'];
        }

        return null;
    }

    public function getCurrencyUuid(string $connectionId, string $oldIsoCode, Context $context): ?string
    {
        $currencyUuid = $this->getMapping($connectionId, DefaultEntities::CURRENCY, $oldIsoCode, $context);

        if ($currencyUuid !== null) {
            return $currencyUuid['entityUuid'];
        }

        return null;
    }

    public function getTaxUuid(string $connectionId, float $taxRate, Context $context): ?string
    {
        return null;
    }

    public function getDefaultAvailabilityRule(Context $context): string
    {
        return Uuid::randomHex();
    }

    public function getDefaultLanguage(Context $context): LanguageEntity
    {
        $defaultLanguage = new LanguageEntity();
        $locale = new LocaleEntity();
        $defaultLanguage->assign([
            'id' => self::DEFAULT_LANGUAGE_UUID,
            'locale' => $locale->assign([
                'id' => self::DEFAULT_LOCAL_UUID,
                'code' => 'en-GB',
            ]),
        ]);

        return $defaultLanguage;
    }

    public function getDeliveryTime(string $connectionId, Context $context, int $minValue, int $maxValue, string $unit, string $name): string
    {
        return Uuid::randomHex();
    }

    public function getDefaultFolderIdByEntity(string $entityName, MigrationContextInterface $migrationContext, Context $context): ?string
    {
        return Uuid::randomHex();
    }

    public function getThumbnailSizeUuid(int $width, int $height, MigrationContextInterface $migrationContext, Context $context): ?string
    {
        return null;
    }

    public function getNumberRangeUuid(string $type, string $oldIdentifier, string $checksum, MigrationContextInterface $migrationContext, Context $context): ?string
    {
        return Uuid::randomHex();
    }

    public function getCurrencyUuidWithoutMapping(string $connectionId, string $oldIsoCode, Context $context): ?string
    {
        return Uuid::randomHex();
    }

    public function getLowestRootCategoryUuid(Context $context): ?string
    {
        return null;
    }

    public function getDefaultCmsPageUuid(string $connectionId, Context $context): ?string
    {
        return Uuid::randomHex();
    }

    public function getMagentoCountryUuid(string $iso, string $connectionId, Context $context): ?string
    {
        $countryUuid = $this->getMapping($connectionId, DefaultEntities::COUNTRY, $iso, $context);

        if ($countryUuid !== null) {
            return $countryUuid['entityUuid'];
        }

        return null;
    }

    public function getTransactionStateUuid(string $state, Context $context): ?string
    {
        return Uuid::randomHex();
    }

    public function getTaxRate(string $uuid, Context $context): ?float
    {
        return 19.0;
    }
}
