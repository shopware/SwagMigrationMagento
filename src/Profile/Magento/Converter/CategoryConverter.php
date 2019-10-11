<?php declare(strict_types=1);

namespace Swag\MigrationMagento\Profile\Magento\Converter;

use Shopware\Core\Framework\Context;
use Swag\MigrationMagento\Profile\Magento\DataSelection\DataSet\CategoryDataSet;
use Swag\MigrationMagento\Profile\Magento\Magento19Profile;
use SwagMigrationAssistant\Migration\Converter\ConvertStruct;
use SwagMigrationAssistant\Migration\DataSelection\DefaultEntities;
use SwagMigrationAssistant\Migration\Logging\Log\EmptyNecessaryFieldRunLog;
use SwagMigrationAssistant\Migration\MigrationContextInterface;
use SwagMigrationAssistant\Profile\Shopware\Exception\ParentEntityForChildNotFoundException;

class CategoryConverter extends MagentoConverter
{
    /**
     * @var string
     */
    protected $connectionId;

    /**
     * @var Context
     */
    protected $context;

    /**
     * @var string
     */
    protected $entity_id;

    /**
     * @var string[]
     */
    protected static $requiredDataFieldKeys = [
        'entity_id',
        'name',
        'defaultLocale',
    ];

    public function getSourceIdentifier(array $data): string
    {
        return $data['entity_id'];
    }

    public function supports(MigrationContextInterface $migrationContext): bool
    {
        return $migrationContext->getProfile()->getName() === Magento19Profile::PROFILE_NAME
            && $migrationContext->getDataSet()::getEntity() === CategoryDataSet::getEntity();
    }

    public function convert(array $data, Context $context, MigrationContextInterface $migrationContext): ConvertStruct
    {
        $fields = $this->checkForEmptyRequiredDataFields($data, self::$requiredDataFieldKeys);
        if (!empty($fields)) {
            $this->loggingService->addLogEntry(new EmptyNecessaryFieldRunLog(
                $migrationContext->getRunUuid(),
                DefaultEntities::CATEGORY,
                $data['entity_id'],
                implode(',', $fields)
            ));

            return new ConvertStruct(null, $data);
        }

        /*
         * Set main data
         */
        $this->generateChecksum($data);
        $this->connectionId = $migrationContext->getConnection()->getId();
        $this->context = $context;
        $this->entity_id = $data['entity_id'];

        /*
         * Set cms page with default cms page
         */
        $cmsPageUuid = $this->mappingService->getDefaultCmsPageUuid($migrationContext->getConnection()->getId(), $context);
        if ($cmsPageUuid !== null) {
            $converted['cmsPageId'] = $cmsPageUuid;
        }

        /*
         * Set parent category and afterCategory with a root category, if a previous category is not found
         */
        if (isset($data['parent_id']) && $data['parent_id'] !== '0') {
            $parentMapping = $this->mappingService->getMapping(
                $this->connectionId,
                DefaultEntities::CATEGORY,
                $data['parent_id'],
                $this->context
            );

            if ($parentMapping === null) {
                throw new ParentEntityForChildNotFoundException(DefaultEntities::CATEGORY, $this->entity_id);
            }

            $this->mappingIds[] = $parentMapping['id'];
            $converted['parentId'] = $parentMapping['entityUuid'];
        } elseif (!isset($data['previousSiblingId'])) {
            $previousSiblingUuid = $this->mappingService->getLowestRootCategoryUuid($context);

            if ($previousSiblingUuid !== null) {
                $converted['afterCategoryId'] = $previousSiblingUuid;
            }
        }
        unset($data['parent']);

        /*
         * Set afterCategory
         */
        if (isset($data['previousSiblingId'])) {
            $previousSiblingMapping = $this->mappingService->getMapping(
                $this->connectionId,
                DefaultEntities::CATEGORY,
                $data['previousSiblingId'],
                $this->context
            );

            if ($previousSiblingMapping !== null) {
                $converted['afterCategoryId'] = $previousSiblingMapping['entityUuid'];
                $this->mappingIds[] = $previousSiblingMapping['id'];
            }
            unset($previousSiblingMapping);
        }
        unset($data['previousSiblingId'], $data['categoryPosition'], $previousSiblingMapping);

        /*
         * Set main mapping
         */
        $this->mainMapping = $this->mappingService->getOrCreateMapping(
            $this->connectionId,
            DefaultEntities::CATEGORY,
            $this->entity_id,
            $this->context,
            $this->checksum
        );
        $converted['id'] = $this->mainMapping['entityUuid'];
        unset($data['id']);

        $this->convertValue($converted, 'description', $data, 'description', self::TYPE_STRING);
        $this->convertValue($converted, 'level', $data, 'level', self::TYPE_INTEGER);
        $this->convertValue($converted, 'active', $data, 'status', self::TYPE_BOOLEAN);

        /*
         * Set translations
         */
        $converted['translations'] = [];
        $this->setCategoryTranslation($data, $converted);
        unset($data['defaultLocale']);

        $this->updateMainMapping($migrationContext, $context);

        return new ConvertStruct($converted, $data, $this->mainMapping['id']);
    }

    protected function setCategoryTranslation(array &$data, array &$converted): void
    {
        $originalData = $data;
        $this->convertValue($converted, 'name', $data, 'name');

        $language = $this->mappingService->getDefaultLanguage($this->context);
        if ($language->getLocale()->getCode() === $data['defaultLocale']) {
            return;
        }

        $localeTranslation = [];
        $localeTranslation['categoryId'] = $converted['id'];

        $this->convertValue($localeTranslation, 'name', $originalData, 'name');

        $mapping = $this->mappingService->getOrCreateMapping(
            $this->connectionId,
            DefaultEntities::CATEGORY_TRANSLATION,
            $this->entity_id . ':' . $data['defaultLocale'],
            $this->context
        );
        $localeTranslation['id'] = $mapping['entityUuid'];
        $this->mappingIds[] = $mapping['id'];

        try {
            $languageUuid = $this->mappingService->getLanguageUuid($this->connectionId, $data['defaultLocale'], $this->context);
        } catch (\Exception $exception) {
            $this->mappingService->deleteMapping($converted['id'], $this->connectionId, $this->context);
            throw $exception;
        }

        $localeTranslation['languageId'] = $languageUuid;

        if (isset($converted['customFields'])) {
            $localeTranslation['customFields'] = $converted['customFields'];
        }

        $converted['translations'][$languageUuid] = $localeTranslation;
    }
}
