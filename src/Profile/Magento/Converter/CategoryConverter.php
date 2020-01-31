<?php declare(strict_types=1);
/*
 * (c) shopware AG <info@shopware.com>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Swag\MigrationMagento\Profile\Magento\Converter;

use Shopware\Core\Framework\Context;
use Swag\MigrationMagento\Migration\Mapping\MagentoMappingServiceInterface;
use Swag\MigrationMagento\Profile\Magento\DataSelection\DataSet\CategoryDataSet;
use Swag\MigrationMagento\Profile\Magento\DataSelection\DataSet\MediaDataSet;
use Swag\MigrationMagento\Profile\Magento\DataSelection\DefaultEntities as MagentoDefaults;
use Swag\MigrationMagento\Profile\Magento\Magento19Profile;
use SwagMigrationAssistant\Migration\Converter\ConvertStruct;
use SwagMigrationAssistant\Migration\DataSelection\DefaultEntities;
use SwagMigrationAssistant\Migration\Logging\Log\EmptyNecessaryFieldRunLog;
use SwagMigrationAssistant\Migration\Logging\LoggingServiceInterface;
use SwagMigrationAssistant\Migration\Media\MediaFileServiceInterface;
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

    public function supports(MigrationContextInterface $migrationContext): bool
    {
        return $migrationContext->getProfile()->getName() === Magento19Profile::PROFILE_NAME
            && $migrationContext->getDataSet()::getEntity() === CategoryDataSet::getEntity();
    }

    public function convert(array $data, Context $context, MigrationContextInterface $migrationContext): ConvertStruct
    {
        $this->connectionId = $migrationContext->getConnection()->getId();
        $this->context = $context;

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

        /*
         * Set main data
         */
        $this->generateChecksum($data);
        $this->runId = $migrationContext->getRunUuid();
        $this->migrationContext = $migrationContext;
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

        $this->convertValue($converted, 'description', $data, 'description', self::TYPE_STRING);
        $this->convertValue($converted, 'level', $data, 'level', self::TYPE_INTEGER);
        $this->convertValue($converted, 'active', $data, 'status', self::TYPE_BOOLEAN);

        /*
         * Set translations
         */
        $converted['translations'] = [];
        $this->setCategoryTranslation($data, $converted);
        unset($data['defaultLocale']);

        /*
         * Set category image
         */
        if (isset($data['image'])) {
            $converted['media'] = $this->getCategoryMedia($data['image']);
        }
        unset($data['image']);

        if (isset($data['translations'])) {
            $converted['translations'] = $this->getTranslations(
                $data['translations'],
                [
                    'name' => 'name',
                    'description' => 'description',
                    'meta_title' => 'metaTitle',
                    'meta_keywords' => 'keywords',
                    'meta_description' => 'metaDescription',
                ],
                $this->context
            );

            foreach ($converted['translations'] as &$translation) {
                $translation['categoryId'] = $converted['id'];
            }
            unset($translation);
        }
        unset($data['translations']);

        $this->updateMainMapping($migrationContext, $context);

        // There is no equivalent field
        unset(
            $data['entity_type_id'],
            $data['attribute_set_id'],
            $data['created_at'],
            $data['updated_at'],
            $data['path'],
            $data['position'],
            $data['children_count']
        );

        if (empty($data)) {
            $data = null;
        }

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

    protected function getCategoryMedia(string $path): array
    {
        $mapping = $this->mappingService->getOrCreateMapping(
            $this->connectionId,
            DefaultEntities::MEDIA,
            $path,
            $this->context
        );
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
