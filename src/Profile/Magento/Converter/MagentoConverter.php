<?php declare(strict_types=1);
/*
 * (c) shopware AG <info@shopware.com>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Swag\MigrationMagento\Profile\Magento\Converter;

use Shopware\Core\Framework\Context;
use Swag\MigrationMagento\Migration\Mapping\MagentoMappingServiceInterface;
use Swag\MigrationMagento\Profile\Magento\DataSelection\DefaultEntities;
use SwagMigrationAssistant\Migration\Converter\Converter;
use SwagMigrationAssistant\Migration\Logging\LoggingServiceInterface;
use SwagMigrationAssistant\Migration\MigrationContextInterface;

abstract class MagentoConverter extends Converter
{
    protected const TYPE_STRING = 'string';
    protected const TYPE_BOOLEAN = 'bool';
    protected const TYPE_INTEGER = 'int';
    protected const TYPE_FLOAT = 'float';
    protected const TYPE_DATETIME = 'datetime';

    /**
     * @var MigrationContextInterface
     */
    protected $migrationContext;

    /**
     * @var array
     */
    protected $originalData;

    public function __construct(
        MagentoMappingServiceInterface $mappingService,
        LoggingServiceInterface $loggingService
    ) {
        parent::__construct($mappingService, $loggingService);
    }

    protected function convertValue(
        array &$newData,
        string $newKey,
        array &$sourceData,
        string $sourceKey,
        string $castType = self::TYPE_STRING,
        bool $unset = true
    ): void {
        if (isset($sourceData[$sourceKey]) && $sourceData[$sourceKey] !== '') {
            switch ($castType) {
                case self::TYPE_BOOLEAN:
                    $sourceValue = (bool) $sourceData[$sourceKey];

                    break;
                case self::TYPE_INTEGER:
                    $sourceValue = (int) $sourceData[$sourceKey];

                    break;
                case self::TYPE_FLOAT:
                    $sourceValue = (float) $sourceData[$sourceKey];

                    break;
                case self::TYPE_DATETIME:
                    $sourceValue = $sourceData[$sourceKey];
                    if (!$this->validDate($sourceValue)) {
                        return;
                    }

                    break;
                default:
                    $sourceValue = (string) $sourceData[$sourceKey];
            }
            $newData[$newKey] = $sourceValue;
        }
        if ($unset === true) {
            unset($sourceData[$sourceKey]);
        }
    }

    protected function validDate(string $value): bool
    {
        try {
            new \DateTime($value);

            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * @param string[] $requiredDataFieldKeys
     *
     * @return string[]
     */
    protected function checkForEmptyRequiredDataFields(array $rawData, array $requiredDataFieldKeys): array
    {
        $emptyFields = [];
        foreach ($requiredDataFieldKeys as $requiredDataFieldKey) {
            if (!isset($rawData[$requiredDataFieldKey]) || $rawData[$requiredDataFieldKey] === '') {
                $emptyFields[] = $requiredDataFieldKey;
            }
        }

        return $emptyFields;
    }

    protected function getAttributes(array $attributes, int $attributeSetId, array $blacklist = []): array
    {
        $result = [];

        foreach ($attributes as $attribute) {
            if (\in_array($attribute['attribute_code'], $blacklist, true)) {
                continue;
            }

            $value = $attribute['value'];
            if (isset($attribute['frontend_input']) && $attribute['frontend_input'] === 'boolean') {
                $value = (bool) $value;
            }
            if (isset($attribute['frontend_input']) && $attribute['frontend_input'] === 'select') {
                $value = 'option_' . $value;
            }
            if (isset($attribute['frontend_input']) && $attribute['frontend_input'] === 'multiselect') {
                $explodedValue = \explode(',', $value);

                $options = [];
                foreach ($explodedValue as $currentOption) {
                    $options[] = 'option_' . $currentOption;
                }

                $value = $options;
            }
            $result['migration_attribute_' . $attributeSetId . '_' . $attribute['attribute_code'] . '_' . $attribute['attribute_id']] = $value;
        }

        return $result;
    }

    /**
     * @psalm-suppress PossiblyInvalidArrayOffset
     */
    protected function getTranslations(array $translations, array $defaultEntities, Context $context, ?int $attributeSetId = null): array
    {
        $connection = $this->migrationContext->getConnection();
        if ($connection === null) {
            return [];
        }

        $localeTranslation = [];
        foreach ($translations as $store => $translationValues) {
            $languageMapping = $this->mappingService->getMapping(
                $connection->getId(),
                DefaultEntities::STORE_LANGUAGE,
                (string) $store,
                $context
            );

            if ($languageMapping === null) {
                continue;
            }
            $this->mappingIds[] = $languageMapping['id'];
            $languageId = $languageMapping['entityUuid'];

            foreach ($translationValues as $attributeCode => $attributeData) {
                if (!isset($attributeData['attribute_id'], $attributeData['value'])) {
                    continue;
                }

                if (isset($defaultEntities[$attributeCode])) {
                    $newKey = $defaultEntities[$attributeCode];

                    if (\is_array($newKey)) {
                        $localeTranslation[$languageId][$newKey['key']] = $this->trimValue($attributeData['value'], $newKey['maxChars']);
                    } else {
                        $localeTranslation[$languageId][$newKey] = $attributeData['value'];
                    }

                    continue;
                }

                if ($attributeSetId === null) {
                    continue;
                }

                $value = $attributeData['value'];
                if (isset($attributeData['frontend_input']) && $attributeData['frontend_input'] === 'boolean') {
                    $value = (bool) $value;
                }
                if (isset($attributeData['frontend_input']) && $attributeData['frontend_input'] === 'select') {
                    $value = 'option_' . $value;
                }

                if ($languageId !== null) {
                    $localeTranslation[$languageId]['languageId'] = $languageId;
                    $localeTranslation[$languageId]['customFields']['migration_attribute_' . $attributeSetId . '_' . $attributeCode . '_' . $attributeData['attribute_id']] = $value;
                }
            }
        }

        return $localeTranslation;
    }

    protected function trimValue(string $value, int $limit = 255): string
    {
        return \mb_substr($value, 0, $limit);
    }
}
