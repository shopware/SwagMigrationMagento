<?php declare(strict_types=1);
/*
 * (c) shopware AG <info@shopware.com>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Swag\MigrationMagento\Profile\Magento\Converter;

use Shopware\Core\Framework\Context;
use Shopware\Core\System\Language\LanguageEntity;
use SwagMigrationAssistant\Migration\Converter\ConvertStruct;
use SwagMigrationAssistant\Migration\DataSelection\DefaultEntities;
use SwagMigrationAssistant\Migration\Logging\Log\EmptyNecessaryFieldRunLog;
use SwagMigrationAssistant\Migration\MigrationContextInterface;

abstract class PropertyGroupConverter extends MagentoConverter
{
    /**
     * @var Context
     */
    protected $context;

    /**
     * @var string
     */
    protected $runId;

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
        return $data['id'];
    }

    public function convert(array $data, Context $context, MigrationContextInterface $migrationContext): ConvertStruct
    {
        $this->generateChecksum($data);
        $this->originalData = $data;
        $this->context = $context;
        $this->migrationContext = $migrationContext;
        $this->runId = $migrationContext->getRunUuid();
        $this->oldIdentifier = $data['id'];
        $defaultLanguage = $this->mappingService->getDefaultLanguage($this->context);

        $connection = $migrationContext->getConnection();
        $this->connectionId = '';
        if ($connection !== null) {
            $this->connectionId = $connection->getId();
        }

        if (!isset($data['name'])) {
            $this->loggingService->addLogEntry(new EmptyNecessaryFieldRunLog(
                $this->runId,
                DefaultEntities::PROPERTY_GROUP,
                $this->oldIdentifier,
                'group name'
            ));

            return new ConvertStruct(null, $this->originalData);
        }
        unset($data['id']);

        $this->mainMapping = $this->mappingService->getOrCreateMapping(
            $this->connectionId,
            DefaultEntities::PROPERTY_GROUP,
            $this->oldIdentifier,
            $context,
            $this->checksum
        );

        $converted = [
            'id' => $this->mainMapping['entityUuid'],
        ];

        if (!isset($data['options'])) {
            $this->loggingService->addLogEntry(new EmptyNecessaryFieldRunLog(
                $this->runId,
                DefaultEntities::PROPERTY_GROUP,
                $this->oldIdentifier,
                'options'
            ));

            return new ConvertStruct(null, $this->originalData);
        }

        $this->getProperties($data, $converted, $defaultLanguage);
        unset($data['options']);

        if (isset($data['translations'])) {
            $converted['translations'] = $this->getTranslations($data['translations'], ['name' => 'name'], $this->context);

            if ($converted['translations'] === []) {
                unset($converted['translations']);
            }
        }
        unset($data['translations']);

        if ($defaultLanguage === null || !isset($converted['translations'][$defaultLanguage->getId()]['name'])) {
            $this->convertValue($converted, 'name', $data, 'name');
        }
        unset($data['name']);

        $this->updateMainMapping($migrationContext, $context);

        $resultData = $data;
        if (empty($resultData)) {
            $resultData = null;
        }

        return new ConvertStruct($converted, $resultData, $this->mainMapping['id']);
    }

    protected function getProperties(array $data, array &$converted, ?LanguageEntity $language): void
    {
        foreach ($data['options'] as $option) {
            $mapping = $this->mappingService->getOrCreateMapping(
                $this->connectionId,
                DefaultEntities::PROPERTY_GROUP_OPTION,
                $option['id'],
                $this->context
            );
            $this->mappingIds[] = $mapping['id'];

            $convertedOption = [
                'id' => $mapping['entityUuid'],
                'translations' => [],
            ];
            if (isset($option['translations'])) {
                $translations = $this->getTranslations($option['translations'], ['name' => 'name'], $this->context);

                if ($translations !== []) {
                    $convertedOption['translations'] = $translations;
                }
            }

            if ($language === null || !isset($convertedOption['translations'][$language->getId()]['name'])) {
                $convertedOption['name'] = $option['name'];
            }

            if ($convertedOption['translations'] === []) {
                unset($convertedOption['translations']);
            }

            $converted['options'][] = $convertedOption;
        }
    }
}
