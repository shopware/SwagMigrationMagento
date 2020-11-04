<?php declare(strict_types=1);
/*
 * (c) shopware AG <info@shopware.com>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Swag\MigrationMagento\Profile\Magento\Converter;

use Shopware\Core\Framework\Context;
use SwagMigrationAssistant\Migration\Converter\Converter;
use SwagMigrationAssistant\Migration\Converter\ConvertStruct;
use SwagMigrationAssistant\Migration\DataSelection\DefaultEntities;
use SwagMigrationAssistant\Migration\Logging\Log\AssociationRequiredMissingLog;
use SwagMigrationAssistant\Migration\MigrationContextInterface;

abstract class CustomFieldConverter extends Converter
{
    /**
     * @var MigrationContextInterface
     */
    protected $migrationContext;

    /**
     * @var string[]
     */
    protected $typeMapping = [
        'price' => 'float',
        'select' => 'select',
        'multiselect' => 'select',
        'textarea' => 'html',
        'text' => 'text',
        'date' => 'datetime',
        'boolean' => 'bool',
    ];

    /**
     * @var string
     */
    protected $connectionId;

    public function getSourceIdentifier(array $data): string
    {
        return $data['attribute_id'] . '_' . $data['setId'];
    }

    public function convert(array $data, Context $context, MigrationContextInterface $migrationContext): ConvertStruct
    {
        $this->generateChecksum($data);
        $this->migrationContext = $migrationContext;
        $type = $this->validateType($data);

        $connection = $migrationContext->getConnection();
        $this->connectionId = '';
        if ($connection !== null) {
            $this->connectionId = $connection->getId();
        }

        if ($type === null) {
            return new ConvertStruct(null, $data);
        }

        $defaultLocale = $this->mappingService->getValue(
            $this->connectionId,
            DefaultEntities::LOCALE,
            'global_default',
            $context
        );

        if ($defaultLocale === null) {
            $this->loggingService->addLogEntry(
                new AssociationRequiredMissingLog(
                    $migrationContext->getRunUuid(),
                    DefaultEntities::LOCALE,
                    'global_default',
                    DefaultEntities::CUSTOM_FIELD_SET
                )
            );

            return new ConvertStruct(null, $data);
        }

        $mapping = $this->mappingService->getOrCreateMapping(
            $this->connectionId,
            DefaultEntities::CUSTOM_FIELD_SET,
            $data['setId'],
            $context
        );

        $converted = [];
        $converted['id'] = $mapping['entityUuid'];
        $this->mappingIds[] = $mapping['id'];

        $converted['name'] = 'migration_set_' . $data['setId'];
        $converted['config'] = [
            'label' => [
                $defaultLocale => $data['setName'],
            ],
            'translated' => true,
        ];
        $mapping = $this->mappingService->getOrCreateMapping(
            $this->connectionId,
            DefaultEntities::CUSTOM_FIELD_SET_RELATION,
            $this->getCustomFieldEntityName() . 'CustomFieldSetRelation-' . $data['setId'],
            $context
        );
        $this->mappingIds[] = $mapping['id'];

        $converted['relations'] = [
            [
                'id' => $mapping['entityUuid'],
                'entityName' => $this->getCustomFieldEntityName(),
            ],
        ];

        $this->mainMapping = $this->mappingService->getOrCreateMapping(
            $this->connectionId,
            $migrationContext->getDataSet()::getEntity(),
            $data['attribute_id'] . '_' . $data['setId'],
            $context,
            $this->checksum
        );
        $converted['customFields'] = [
            [
                'id' => $this->mainMapping['entityUuid'],
                'name' => 'migration_attribute_' . $data['setId'] . '_' . $data['attribute_code'] . '_' . $data['attribute_id'],
                'type' => $type,
                'config' => $this->getConfiguredCustomFieldData($data, $defaultLocale),
            ],
        ];
        unset(
            // Used keys
            $data['attribute_id'],
            $data['attribute_code'],
            $data['options'],
            $data['frontend_input'],
            $data['frontend_label'],
            $data['setId'],
            $data['setName'],

            // There is no equivalent field
            $data['entity_type_id'],
            $data['attribute_model'],
            $data['backend_model'],
            $data['backend_type'],
            $data['backend_table'],
            $data['frontend_model'],
            $data['frontend_class'],
            $data['source_model'],
            $data['is_required'],
            $data['is_user_defined'],
            $data['default_value'],
            $data['is_unique'],
            $data['note']
        );

        $this->updateMainMapping($migrationContext, $context);

        if (empty($data)) {
            $data = null;
        }

        return new ConvertStruct($converted, $data, $this->mainMapping['id']);
    }

    abstract protected function getCustomFieldEntityName(): string;

    protected function getConfiguredCustomFieldData(array $data, string $defaultLocale): array
    {
        $attributeData = [
            'componentName' => 'sw-field',
            'label' => [
                $defaultLocale => $data['frontend_label'],
            ],
            'helpText' => [
                $defaultLocale => null,
            ],
            'placeholder' => [
                $defaultLocale => null,
            ],
            'type' => 'text',
            'customFieldType' => 'text',
        ];

        if (isset($data['translations'])) {
            foreach ($data['translations'] as $translation) {
                $attributeData['label'][$translation['locale']] = $translation['value'];
            }
        }

        if ($data['frontend_input'] === 'text') {
            $attributeData['type'] = 'text';
            $attributeData['customFieldType'] = 'text';

            return $attributeData;
        }

        if ($data['frontend_input'] === 'price') {
            $attributeData['type'] = 'number';
            $attributeData['numberType'] = 'float';
            $attributeData['customFieldType'] = 'number';

            return $attributeData;
        }

        if ($data['frontend_input'] === 'textarea') {
            $attributeData['componentName'] = 'sw-text-editor';
            $attributeData['customFieldType'] = 'textEditor';

            return $attributeData;
        }

        if ($data['frontend_input'] === 'boolean') {
            $attributeData['type'] = 'checkbox';
            $attributeData['customFieldType'] = 'checkbox';

            return $attributeData;
        }

        if ($data['frontend_input'] === 'date') {
            $attributeData['type'] = 'date';
            $attributeData['dateType'] = 'date';
            $attributeData['customFieldType'] = 'date';

            return $attributeData;
        }

        if ($data['frontend_input'] === 'select' || $data['frontend_input'] === 'multiselect') {
            $options = [];
            if (isset($data['options'])) {
                foreach ($data['options'] as $option) {
                    $optionData = [
                        'value' => 'option_' . $option['option_id'],
                        'label' => [
                            $defaultLocale => $option['value'],
                        ],
                    ];

                    if (isset($option['translations'])) {
                        foreach ($option['translations'] as $translation) {
                            $optionData['label'][$translation['locale']] = $translation['value'];
                        }
                    }
                    $options[] = $optionData;
                }
            }

            $attributeData['componentName'] = 'sw-single-select';
            if ($data['frontend_input'] === 'multiselect') {
                $attributeData['componentName'] = 'sw-multi-select';
            }
            $attributeData['type'] = 'select';
            $attributeData['customFieldType'] = 'select';
            $attributeData['options'] = $options;

            return $attributeData;
        }

        return [];
    }

    protected function validateType(array $data): ?string
    {
        $frontendInput = $data['frontend_input'];

        if (isset($this->typeMapping[$frontendInput])) {
            return $this->typeMapping[$frontendInput];
        }

        return null;
    }

    protected function getDataSetEntity(MigrationContextInterface $migrationContext): ?string
    {
        $dataSet = $migrationContext->getDataSet();
        if ($dataSet === null) {
            return null;
        }

        return $dataSet::getEntity();
    }
}
