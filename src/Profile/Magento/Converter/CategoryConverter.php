<?php declare(strict_types=1);

namespace Swag\MigrationMagento\Profile\Magento\Converter;

use Shopware\Core\Framework\Context;
use Swag\MigrationMagento\Profile\Magento\DataSelection\DataSet\CategoryDataSet;
use Swag\MigrationMagento\Profile\Magento\Magento19Profile;
use SwagMigrationAssistant\Migration\Converter\ConvertStruct;
use SwagMigrationAssistant\Migration\DataSelection\DefaultEntities;
use SwagMigrationAssistant\Migration\Logging\LoggingServiceInterface;
use SwagMigrationAssistant\Migration\Mapping\MappingServiceInterface;
use SwagMigrationAssistant\Migration\MigrationContextInterface;
use SwagMigrationAssistant\Profile\Shopware\Exception\ParentEntityForChildNotFoundException;

class CategoryConverter extends MagentoConverter
{
    /**
     * @var MappingServiceInterface
     */
    private $mappingService;

    /**
     * @var LoggingServiceInterface
     */
    private $loggingService;

    /**
     * @var string
     */
    private $connectionId;

    /**
     * @var Context
     */
    private $context;

    /**
     * @var string
     */
    private $entity_id;

    public function __construct(MappingServiceInterface $mappingService, LoggingServiceInterface $loggingService)
    {
        $this->mappingService = $mappingService;
        $this->loggingService = $loggingService;
    }

    public function supports(MigrationContextInterface $migrationContext): bool
    {
        return $migrationContext->getProfile()->getName() === Magento19Profile::PROFILE_NAME
            && $migrationContext->getDataSet()::getEntity() === CategoryDataSet::getEntity();
    }

    public function writeMapping(Context $context): void
    {
        $this->mappingService->writeMapping($context);
    }

    public function convert(array $data, Context $context, MigrationContextInterface $migrationContext): ConvertStruct
    {
        $this->connectionId = $migrationContext->getConnection()->getId();
        $this->context = $context;
        $this->entity_id = $data['entity_id'];

        $cmsPageUuid = $this->mappingService->getDefaultCmsPageUuid($migrationContext->getConnection()->getId(), $context);
        if ($cmsPageUuid !== null) {
            $converted['cmsPageId'] = $cmsPageUuid;
        }

        if (isset($data['parent_id']) && $data['parent_id'] !== '0') {
            $parentUuid = $this->mappingService->getUuid(
                $this->connectionId,
                DefaultEntities::CATEGORY,
                $data['parent_id'],
                $this->context
            );

            if ($parentUuid === null) {
                throw new ParentEntityForChildNotFoundException(DefaultEntities::CATEGORY, $this->entity_id);
            }

            $converted['parentId'] = $parentUuid;
            // get last root category as previous sibling
        } elseif (!isset($data['previousSiblingId'])) {
            $previousSiblingUuid = $this->mappingService->getLowestRootCategoryUuid($context);

            if ($previousSiblingUuid !== null) {
                $converted['afterCategoryId'] = $previousSiblingUuid;
            }
        }
        unset($data['parent']);

        if (isset($data['previousSiblingId'])) {
            $previousSiblingUuid = $this->mappingService->getUuid(
                $this->connectionId,
                DefaultEntities::CATEGORY,
                $data['previousSiblingId'],
                $this->context
            );

            $converted['afterCategoryId'] = $previousSiblingUuid;
        }
        unset($data['previousSiblingId'], $data['position']);

        $converted['id'] = $this->mappingService->createNewUuid(
            $this->connectionId,
            DefaultEntities::CATEGORY,
            $this->entity_id,
            $this->context
        );
        unset($data['id']);

        $this->convertValue($converted, 'description', $data, 'description', self::TYPE_STRING);
        $this->convertValue($converted, 'level', $data, 'level', self::TYPE_INTEGER);
        $this->convertValue($converted, 'active', $data, 'status', self::TYPE_BOOLEAN);

        $converted['translations'] = [];
        $this->setCategoryTranslation($data, $converted);
        unset($data['defaultLocale']);

        return new ConvertStruct($converted, $data);
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

        $localeTranslation['id'] = $this->mappingService->createNewUuid(
            $this->connectionId,
            DefaultEntities::CATEGORY_TRANSLATION,
            $this->entity_id . ':' . $data['defaultLocale'],
            $this->context
        );

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