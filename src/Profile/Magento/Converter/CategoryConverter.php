<?php declare(strict_types=1);
/*
 * (c) shopware AG <info@shopware.com>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Swag\MigrationMagento\Profile\Magento\Converter;

use Shopware\Core\Framework\Context;
use Swag\MigrationMagento\Migration\Mapping\MagentoMappingServiceInterface;
use Swag\MigrationMagento\Profile\Magento\DataSelection\DataSet\MediaDataSet;
use Swag\MigrationMagento\Profile\Magento\DataSelection\DefaultEntities as MagentoDefaults;
use SwagMigrationAssistant\Migration\Converter\ConvertStruct;
use SwagMigrationAssistant\Migration\DataSelection\DefaultEntities;
use SwagMigrationAssistant\Migration\Logging\Log\EmptyNecessaryFieldRunLog;
use SwagMigrationAssistant\Migration\Logging\LoggingServiceInterface;
use SwagMigrationAssistant\Migration\Media\MediaFileServiceInterface;
use SwagMigrationAssistant\Migration\MigrationContextInterface;
use SwagMigrationAssistant\Profile\Shopware\Exception\ParentEntityForChildNotFoundException;

abstract class CategoryConverter extends MagentoConverter
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

    /**
     * @var MediaFileServiceInterface
     */
    private $mediaFileService;

    /**
     * @var string
     */
    private $runId;

    public function __construct(
        MagentoMappingServiceInterface $mappingService,
        LoggingServiceInterface $loggingService,
        MediaFileServiceInterface $mediaFileService
    ) {
        parent::__construct($mappingService, $loggingService);

        $this->mediaFileService = $mediaFileService;
    }

    public function getMediaUuids(array $converted): ?array
    {
        $mediaUuids = [];
        foreach ($converted as $data) {
            if (!isset($data['media']['id'])) {
                continue;
            }

            $mediaUuids[] = $data['media']['id'];
        }

        return $mediaUuids;
    }

    public function getSourceIdentifier(array $data): string
    {
        return $data['entity_id'];
    }

    public function convert(array $data, Context $context, MigrationContextInterface $migrationContext): ConvertStruct
    {
        $this->context = $context;

        $connection = $migrationContext->getConnection();
        $this->connectionId = '';
        if ($connection !== null) {
            $this->connectionId = $connection->getId();
        }

        // Ignore the magento root category
        if (isset($data['parent_id']) && $data['parent_id'] === '0') {
            $mapping = $this->mappingService->getOrCreateMapping(
                $this->connectionId,
                MagentoDefaults::ROOT_CATEGORY,
                $data['entity_id'],
                $this->context
            );
            $this->mappingIds[] = $mapping['id'];

            return new ConvertStruct(null, $data);
        }
        $rootCategoryMapping = $this->mappingService->getMapping($this->connectionId, MagentoDefaults::ROOT_CATEGORY, $data['parent_id'], $context);

        $fields = $this->checkForEmptyRequiredDataFields($data, self::$requiredDataFieldKeys);
        if (!empty($fields)) {
            $this->loggingService->addLogEntry(new EmptyNecessaryFieldRunLog(
                $migrationContext->getRunUuid(),
                DefaultEntities::CATEGORY,
                $data['entity_id'],
                \implode(',', $fields)
            ));

            return new ConvertStruct(null, $data);
        }

        /*
         * Set main data
         */
        $this->generateChecksum($data);
        $this->runId = $migrationContext->getRunUuid();
        $this->migrationContext = $migrationContext;
        $this->entity_id = $data['entity_id'];
        $converted = [];

        /*
         * Set cms page with default cms page
         */
        $cmsPageUuid = $this->mappingService->getDefaultCmsPageUuid($this->connectionId, $context);
        if ($cmsPageUuid !== null) {
            $converted['cmsPageId'] = $cmsPageUuid;
        }

        /*
         * Set parent category and afterCategory with a root category, if a previous category is not found
         */
        if (isset($data['parent_id']) && !$this->parentIsRoot($data, $rootCategoryMapping)) {
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
        unset($data['parent_id']);

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
        unset($data['previousSiblingId']);

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
        unset($data['entity_id']);

        $this->convertValue($converted, 'level', $data, 'level', self::TYPE_INTEGER);
        $this->convertValue($converted, 'active', $data, 'status', self::TYPE_BOOLEAN);
        $this->convertValue($converted, 'visible', $data, 'visible', self::TYPE_BOOLEAN);

        /*
         * Set category image
         */
        if (isset($data['image'])) {
            $converted['media'] = $this->getCategoryMedia($data['image']);
        }
        unset($data['image']);

        $converted['translations'] = [];
        if (isset($data['translations'])) {
            $converted['translations'] = $this->getTranslations(
                $data['translations'],
                [
                    'name' => 'name',
                    'description' => 'description',
                    'meta_title' => ['key' => 'metaTitle', 'maxChars' => 255],
                    'meta_keywords' => ['key' => 'keywords', 'maxChars' => 255],
                    'meta_description' => ['key' => 'metaDescription', 'maxChars' => 255],
                ],
                $this->context
            );

            foreach ($converted['translations'] as &$translation) {
                $translation['categoryId'] = $converted['id'];
            }
            unset($translation);
        }
        unset($data['translations']);

        $this->setCategoryTranslation($data, $converted);
        unset($data['defaultLocale']);

        if (isset($converted['name'])) {
            $converted['name'] = $this->trimValue($converted['name']);
        }
        if (isset($converted['metaDescription'])) {
            $converted['metaDescription'] = $this->trimValue($converted['metaDescription']);
        }
        if (isset($converted['keywords'])) {
            $converted['keywords'] = $this->trimValue($converted['keywords']);
        }
        if (isset($converted['metaTitle'])) {
            $converted['metaTitle'] = $this->trimValue($converted['metaTitle']);
        }

        if ($converted['translations'] === []) {
            unset($converted['translations']);
        }

        $this->updateMainMapping($migrationContext, $context);

        // There is no equivalent field
        unset(
            $data['entity_type_id'],
            $data['attribute_set_id'],
            $data['created_at'],
            $data['updated_at'],
            $data['path'],
            $data['position'],
            $data['calcLevel'],
            $data['children_count']
        );

        $resultData = $data;
        if (empty($resultData)) {
            $resultData = null;
        }

        return new ConvertStruct($converted, $resultData, $this->mainMapping['id']);
    }

    protected function setCategoryTranslation(array &$data, array &$converted): void
    {
        $defaultLanguage = $this->mappingService->getDefaultLanguage($this->context);

        $defaultLanguageId = '';
        if ($defaultLanguage !== null) {
            $defaultLanguageId = $defaultLanguage->getId();
        }

        $originalData = $data;
        $this->convertTranslationValue($defaultLanguageId, $converted, 'name', $data, 'name');
        $this->convertTranslationValue($defaultLanguageId, $converted, 'description', $data, 'description');
        $this->convertTranslationValue($defaultLanguageId, $converted, 'metaTitle', $data, 'meta_title');
        $this->convertTranslationValue($defaultLanguageId, $converted, 'metaDescription', $data, 'meta_description');
        $this->convertTranslationValue($defaultLanguageId, $converted, 'keywords', $data, 'meta_keywords');

        $language = $this->mappingService->getDefaultLanguage($this->context);
        if ($language === null) {
            return;
        }

        $locale = $language->getLocale();
        if ($locale === null) {
            return;
        }

        if (isset($converted['translations'][$language->getId()]['name'])) {
            unset($converted['name']);
        }

        if ($locale->getCode() === $data['defaultLocale']) {
            return;
        }

        try {
            $languageUuid = $this->mappingService->getLanguageUuid($this->connectionId, $data['defaultLocale'], $this->context);
        } catch (\Exception $exception) {
            $this->mappingService->deleteMapping($converted['id'], $this->connectionId, $this->context);

            throw $exception;
        }

        if ($languageUuid === null) {
            return;
        }

        $localeTranslation = [];
        $localeTranslation['categoryId'] = $converted['id'];

        if (isset($originalData['name'])) {
            $originalData['name'] = $this->trimValue($originalData['name']);
        }
        if (isset($originalData['meta_title'])) {
            $originalData['meta_title'] = $this->trimValue($originalData['meta_title']);
        }
        if (isset($originalData['meta_description'])) {
            $originalData['meta_description'] = $this->trimValue($originalData['meta_description']);
        }
        if (isset($originalData['meta_keywords'])) {
            $originalData['meta_keywords'] = $this->trimValue($originalData['meta_keywords']);
        }
        $this->convertTranslationValue($languageUuid, $localeTranslation, 'name', $originalData, 'name');
        $this->convertTranslationValue($languageUuid, $localeTranslation, 'description', $originalData, 'description');
        $this->convertTranslationValue($languageUuid, $localeTranslation, 'metaTitle', $originalData, 'meta_title');
        $this->convertTranslationValue($languageUuid, $localeTranslation, 'metaDescription', $originalData, 'meta_description');
        $this->convertTranslationValue($languageUuid, $localeTranslation, 'keywords', $originalData, 'meta_keywords');

        $mapping = $this->mappingService->getOrCreateMapping(
            $this->connectionId,
            DefaultEntities::CATEGORY_TRANSLATION,
            $this->entity_id . ':' . $data['defaultLocale'],
            $this->context
        );
        $localeTranslation['id'] = $mapping['entityUuid'];
        $this->mappingIds[] = $mapping['id'];

        if (isset($converted['customFields'])) {
            $localeTranslation['customFields'] = $converted['customFields'];
        }

        if (!isset($converted['translations'][$languageUuid]['name'])) {
            $localeTranslation['languageId'] = $languageUuid;
            $converted['translations'][$languageUuid] = $localeTranslation;
        }
    }

    protected function convertTranslationValue(
        string $defaultLanguage,
        array &$newData,
        string $newKey,
        array &$sourceData,
        string $sourceKey,
        string $castType = self::TYPE_STRING,
        bool $unset = true
    ): void {
        if (!isset($newData['translations'][$defaultLanguage][$newKey]) || $defaultLanguage === '') {
            $this->convertValue($newData, $newKey, $sourceData, $sourceKey, $castType, $unset);
        }

        if ($unset) {
            unset($sourceData[$sourceKey]);
        }
    }

    protected function getCategoryMedia(string $path): array
    {
        $mapping = $this->mappingService->getOrCreateMapping(
            $this->connectionId,
            DefaultEntities::MEDIA,
            $path,
            $this->context
        );

        $categoryMedia = [];
        $categoryMedia['id'] = $mapping['entityUuid'];
        $this->mappingIds[] = $mapping['id'];

        $albumUuid = $this->mappingService->getDefaultFolderIdByEntity(DefaultEntities::CATEGORY, $this->migrationContext, $this->context);

        if ($albumUuid !== null) {
            $categoryMedia['mediaFolderId'] = $albumUuid;
        }

        $this->mediaFileService->saveMediaFile(
            [
                'runId' => $this->runId,
                'entity' => MediaDataSet::getEntity(),
                'uri' => '/media/catalog/category/' . $path,
                'fileName' => $categoryMedia['id'],
                'fileSize' => 0,
                'mediaId' => $categoryMedia['id'],
            ]
        );

        return $categoryMedia;
    }

    private function parentIsRoot(array $data, ?array $rootCategoryMapping): bool
    {
        if ($rootCategoryMapping === null || !isset($rootCategoryMapping['oldIdentifier'])) {
            return false;
        }

        if ($rootCategoryMapping['oldIdentifier'] !== $data['parent_id']) {
            return false;
        }

        return true;
    }
}
