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
use SwagMigrationAssistant\Migration\MigrationContextInterface;

abstract class CustomFieldConverter extends Converter
{
    protected $typeMapping = [
        'price' => 'float',
        'select' => 'select',
        'textarea' => 'html',
        'text' => 'text',
        'date' => 'datetime',
        'boolean' => 'bool',
    ];

    public function getSourceIdentifier(array $data): string
    {
        return $data['attribute_id'];
    }

    public function convert(array $data, Context $context, MigrationContextInterface $migrationContext): ConvertStruct
    {
        $this->generateChecksum($data);
        $type = $this->validateType($data);

        if ($type === null) {
            return new ConvertStruct(null, $data);
        }

        $mapping = $this->mappingService->getOrCreateMapping(
            $migrationContext->getConnection()->getId(),
            DefaultEntities::CUSTOM_FIELD_SET,
            $this->getCustomFieldEntityName() . 'CustomFieldSet',
            $context
        );
        $converted['id'] = $mapping['entityUuid'];
        $this->mappingIds[] = $mapping['id'];

        $connectionName = $migrationContext->getConnection()->getName();
        $connectionName = str_replace(' ', '', $connectionName);
        $connectionName = preg_replace('/[^A-Za-z0-9\-]/', '', $connectionName);

        $converted['name'] = 'migration_' . $connectionName . '_' . $this->getCustomFieldEntityName();

        $converted['config'] = [
            'label' => [
                $data['defaultLocale'] => ucfirst($this->getCustomFieldEntityName()) . ' migration custom fields (attributes)',
            ],
            'translated' => true,
        ];
        $mapping = $this->mappingService->getOrCreateMapping(
            $migrationContext->getConnection()->getId(),
            DefaultEntities::CUSTOM_FIELD_SET_RELATION,
            $this->getCustomFieldEntityName() . 'CustomFieldSetRelation',
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
            $migrationContext->getConnection()->getId(),
            $migrationContext->getDataSet()::getEntity(),
            $data['attribute_id'],
            $context,
            $this->checksum
        );
        $converted['customFields'] = [
            [
                'id' => $this->mainMapping['entityUuid'],
                'name' => $converted['name'] . '_' . $data['attribute_id'],
                'type' => $type,
                'config' => $this->getConfiguredCustomFieldData($data),
            ],
        ];
        unset(
            // Used keys
            $data['attribute_id'],
            $data['attribute_code'],
            $data['options'],
            $data['frontend_input'],
            $data['frontend_label'],
            $data['defaultLocale'],

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

    protected function getConfiguredCustomFieldData(array $data): array
    {
        $locale = $data['defaultLocale'];
        $attributeData = [
            'componentName' => 'sw-field',
            'label' => [
                $locale => $data['frontend_label'],
            ],
            'helpText' => [
                $locale => null,
            ],
            'placeholder' => [
                $locale => null,
            ],
            'type' => 'text',
            'customFieldType' => 'text',
        ];

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

        if ($data['frontend_input'] === 'select') {
            $options = [];
            foreach ($data['options'] as $option) {
                $options[] = [
                    'value' => $option['option_id'],
                    'label' => [
                        $locale => $option['value'],
                    ],
                ];
            }

            $attributeData['componentName'] = 'sw-single-select';
            $attributeData['type'] = 'select';
            $attributeData['customFieldType'] = 'select';
            $attributeData['options'] = $options;

            return $attributeData;
        }

        return [];
    }

    protected function validateType($data): ?string
    {
        $frontendInput = $data['frontend_input'];

        if (isset($this->typeMapping[$frontendInput])) {
            return $this->typeMapping[$frontendInput];
        }

        return null;
    }
}
