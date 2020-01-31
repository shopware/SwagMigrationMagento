<?php declare(strict_types=1);
/*
 * (c) shopware AG <info@shopware.com>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Swag\MigrationMagento\Profile\Magento\Converter;

use Shopware\Core\Framework\Context;
use Swag\MigrationMagento\Profile\Magento\DataSelection\DataSet\PropertyGroupDataSet;
use Swag\MigrationMagento\Profile\Magento\Magento19Profile;
use SwagMigrationAssistant\Migration\Converter\ConvertStruct;
use SwagMigrationAssistant\Migration\DataSelection\DefaultEntities;
use SwagMigrationAssistant\Migration\Logging\Log\EmptyNecessaryFieldRunLog;
use SwagMigrationAssistant\Migration\MigrationContextInterface;

class PropertyGroupConverter extends MagentoConverter
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

    public function supports(MigrationContextInterface $migrationContext): bool
    {
        return $migrationContext->getProfile()->getName() === Magento19Profile::PROFILE_NAME
            && $migrationContext->getDataSet()::getEntity() === PropertyGroupDataSet::getEntity();
    }

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
        $this->connectionId = $migrationContext->getConnection()->getId();
        $this->oldIdentifier = $data['id'];

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
            'name' => $data['name'],
        ];
        unset($data['name']);

        $this->getProperties($data, $converted);
        unset($data['options']);

        if (isset($data['translations'])) {
            $converted['translations'] = $this->getTranslations($data['translations'], ['name' => 'name'], $this->context);
        }
        unset($data['translations']);

        $this->updateMainMapping($migrationContext, $context);

        if (empty($data)) {
            $data = null;
        }

        return new ConvertStruct($converted, $data, $this->mainMapping['id']);
    }

    protected function getProperties(array $data, array &$converted): void
    {
        foreach ($data['options'] as $option) {
            $mapping = $this->mappingService->getOrCreateMapping(
                $this->connectionId,
                DefaultEntities::PROPERTY_GROUP_OPTION,
                $option['id'],
                $this->context
            );
            $this->mappingIds[] = $mapping['id'];

            $translations = [];
            if (isset($option['translations'])) {
                $translations = $this->getTranslations($option['translations'], ['name' => 'name'], $this->context);
            }

            $converted['options'][] = [
                'id' => $mapping['entityUuid'],
                'name' => $option['name'],
                'translations' => $translations,
            ];
        }
    }
}
