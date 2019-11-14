<?php declare(strict_types=1);

namespace Swag\MigrationMagento\Profile\Magento\Converter;

use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Rule\Container\AndRule;
use Shopware\Core\Framework\Rule\Container\OrRule;
use Swag\MigrationMagento\Migration\Mapping\MagentoMappingServiceInterface;
use Swag\MigrationMagento\Profile\Magento\DataSelection\DataSet\MediaDataSet;
use Swag\MigrationMagento\Profile\Magento\DataSelection\DataSet\ProductDataSet;
use Swag\MigrationMagento\Profile\Magento\DataSelection\DefaultEntities as MagentoDefaultEntities;
use Swag\MigrationMagento\Profile\Magento\Magento19Profile;
use SwagMigrationAssistant\Migration\Converter\ConvertStruct;
use SwagMigrationAssistant\Migration\DataSelection\DefaultEntities;
use SwagMigrationAssistant\Migration\Logging\Log\EmptyNecessaryFieldRunLog;
use SwagMigrationAssistant\Migration\Logging\Log\UnknownEntityLog;
use SwagMigrationAssistant\Migration\Logging\LoggingServiceInterface;
use SwagMigrationAssistant\Migration\Media\MediaFileServiceInterface;
use SwagMigrationAssistant\Migration\MigrationContextInterface;
use SwagMigrationAssistant\Profile\Shopware\Exception\ParentEntityForChildNotFoundException;

class ProductConverter extends MagentoConverter
{
    /**
     * @var Context
     */
    protected $context;

    /**
     * @var string
     */
    protected $connectionId;

    /**
     * @var MediaFileServiceInterface
     */
    protected $mediaFileService;

    /**
     * @var string
     */
    protected $runUuid;

    /**
     * @var string
     */
    protected $oldIdentifier;

    /**
     * @var MagentoMappingServiceInterface
     */
    protected $mappingService;

    public function __construct(
        MagentoMappingServiceInterface $mappingService,
        LoggingServiceInterface $loggingService,
        MediaFileServiceInterface $mediaFileService
    ) {
        parent::__construct($mappingService, $loggingService);

        $this->mediaFileService = $mediaFileService;
    }

    public function supports(MigrationContextInterface $migrationContext): bool
    {
        return $migrationContext->getProfile()->getName() === Magento19Profile::PROFILE_NAME
            && $migrationContext->getDataSet()::getEntity() === ProductDataSet::getEntity();
    }

    public function getSourceIdentifier(array $data): string
    {
        return $data['entity_id'];
    }

    public function convert(array $data, Context $context, MigrationContextInterface $migrationContext): ConvertStruct
    {
        $this->generateChecksum($data);
        $this->originalData = $data;
        $this->context = $context;
        $this->migrationContext = $migrationContext;
        $this->connectionId = $migrationContext->getConnection()->getId();
        $this->runUuid = $migrationContext->getRunUuid();
        $this->oldIdentifier = $data['entity_id'];
        $converted = [];

        /*
         * Set manufacturer
         */
        if (isset($data['manufacturer'])) {
            $this->setManufacturerId($data['manufacturer'], $converted);
        }
        unset($data['manufacturer']);

        /*
         * Throw error if no tax class is found
         */
        if (!isset($data['tax_class_id'])) {
            $this->loggingService->addLogEntry(
                new EmptyNecessaryFieldRunLog(
                    $this->runUuid,
                    DefaultEntities::PRODUCT,
                    $this->oldIdentifier,
                    'tax class'
                )
            );

            return new ConvertStruct(null, $this->originalData);
        }

        /*
         * Set tax
         */
        if (!$this->setTax($data['tax_class_id'], $converted)) {
            $this->loggingService->addLogEntry(
                new UnknownEntityLog(
                    $this->runUuid,
                    DefaultEntities::TAX,
                    $data['tax_class_id'],
                    DefaultEntities::PRODUCT,
                    $this->oldIdentifier
                )
            );

            return new ConvertStruct(null, $this->originalData);
        }
        unset($data['tax_class_id']);

        if (!isset($data['price'])) {
            $this->loggingService->addLogEntry(
                new EmptyNecessaryFieldRunLog(
                    $this->runUuid,
                    DefaultEntities::PRODUCT,
                    $this->oldIdentifier,
                    'price'
                )
            );

            return new ConvertStruct(null, $this->originalData);
        }

        $converted['price'] = $this->getPrice($data, $converted);

        if (empty($converted['price'])) {
            $this->loggingService->addLogEntry(new EmptyNecessaryFieldRunLog(
                $this->runUuid,
                DefaultEntities::PRODUCT,
                $this->oldIdentifier,
                'currency'
            ));

            return new ConvertStruct(null, $this->originalData);
        }

        /*
         * Set main id
         */
        $this->mainMapping = $this->mappingService->getOrCreateMapping(
            $migrationContext->getConnection()->getId(),
            DefaultEntities::PRODUCT,
            $data['entity_id'],
            $context,
            $this->checksum
        );
        $converted['id'] = $this->mainMapping['entityUuid'];

        if (isset($data['prices'])) {
            $converted['prices'] = $this->getPrices($data['prices'], $converted);
        }

        $this->convertValue($converted, 'stock', $data, 'instock', self::TYPE_INTEGER);
        $this->convertValue($converted, 'productNumber', $data, 'sku');
        $this->convertValue($converted, 'name', $data, 'name');

        /*
         * Set parent
         */
        if (isset($data['parentId'])) {
            $this->setParent($converted, $data);
        }

        /*
         * Set properties
         */
        if (isset($data['properties'])) {
            $this->setProperties($converted, $data);
        }

        if (isset($data['type_id']) && $data['type_id'] === 'configurable') {
            /*
             * Set configurator settings
             */
            if (isset($data['configuratorSettings'])) {
                $this->setConfiguratorSettings($converted, $data);
            }
        } else {
            /*
             * Set options
             */
            if (isset($data['options'], $converted['parentId'])) {
                $this->setOptions($converted, $data);
            }
        }

        if (isset($data['media'])) {
            $convertedMedia = $this->getMedia($data['media'], $converted);

            if (!empty($convertedMedia['media'])) {
                $converted['media'] = $convertedMedia['media'];
            }

            if (isset($convertedMedia['cover'])) {
                $converted['cover'] = $convertedMedia['cover'];
            }

            unset($data['media'], $convertedMedia);
        }

        if (isset($data['visibility'])) {
            $this->setVisibility($converted, $data);
        }

        if (isset($data['categories'])) {
            $this->setCategories($converted, $data);
        }

        if (isset($data['attributes'])) {
            $converted['customFields'] = $this->getAttributes($data['attributes'], DefaultEntities::PRODUCT, $migrationContext->getConnection()->getName());
        }

        $this->updateMainMapping($migrationContext, $context);

        if (empty($data)) {
            $data = null;
        }

        return new ConvertStruct($converted, null, $this->mainMapping['id']);
    }

    protected function setCategories(array &$converted, array &$data): void
    {
        $categoryMapping = [];

        foreach ($data['categories'] as $category) {
            $mapping = $this->mappingService->getMapping(
                $this->connectionId,
                DefaultEntities::CATEGORY,
                $category['categoryId'],
                $this->context
            );

            if ($mapping === null) {
                continue;
            }
            $categoryMapping[] = ['id' => $mapping['entityUuid']];
            $this->mappingIds[] = $mapping['id'];
        }

        $converted['categories'] = $categoryMapping;
    }

    /**
     * @throws ParentEntityForChildNotFoundException
     */
    protected function setParent(array &$converted, array &$data): void
    {
        $parentMapping = $this->mappingService->getMapping(
            $this->connectionId,
            DefaultEntities::PRODUCT,
            $data['parentId'],
            $this->context
        );

        if ($parentMapping === null) {
            throw new ParentEntityForChildNotFoundException(DefaultEntities::PRODUCT, $this->oldIdentifier);
        }

        $converted['parentId'] = $parentMapping['entityUuid'];
        $this->mappingIds[] = $parentMapping['id'];
    }

    protected function setManufacturerId(string $manufacturer, array &$converted): void
    {
        $mapping = $this->mappingService->getMapping(
            $this->connectionId,
            DefaultEntities::PRODUCT_MANUFACTURER,
            $manufacturer,
            $this->context
        );

        if ($mapping === null) {
            return;
        }

        $converted['manufacturerId'] = $mapping['entityUuid'];
    }

    protected function setTax(string $taxClassId, array &$converted): bool
    {
        $mapping = $this->mappingService->getMapping(
            $this->connectionId,
            DefaultEntities::TAX,
            $taxClassId,
            $this->context
        );

        if ($mapping !== null) {
            $this->mappingIds[] = $mapping['id'];
            $converted['taxId'] = $mapping['entityUuid'];

            return true;
        }

        if ((int) $taxClassId === 0) {
            $mapping = $this->mappingService->getOrCreateMapping(
                $this->connectionId,
                DefaultEntities::TAX,
                '0',
                $this->context
            );

            $converted['tax'] = [
                'id' => $mapping['entityUuid'],
                'taxRate' => 0,
                'name' => '0%',
            ];
            $this->mappingIds[] = $mapping['id'];

            return true;
        }

        return false;
    }

    protected function getPrice(array $priceData, array $converted): array
    {
        $taxRate = 0;
        if (isset($converted['taxId'])) {
            $taxRate = $this->mappingService->getTaxRate(
                $converted['taxId'],
                $this->context
            );
        }

        $currencyMapping = $this->mappingService->getMapping(
            $this->connectionId,
            DefaultEntities::CURRENCY,
            'default_currency',
            $this->context
        );

        if (!isset($currencyMapping)) {
            return [];
        }
        $currencyUuid = $currencyMapping['entityUuid'];
        $this->mappingIds[] = $currencyMapping['id'];

        $gross = round((float) $priceData['price'] * (1 + $taxRate / 100), $this->context->getCurrencyPrecision());

        $price = [];
        if ($currencyUuid !== Defaults::CURRENCY) {
            $price[] = [
                'currencyId' => Defaults::CURRENCY,
                'gross' => $gross,
                'net' => (float) $priceData['price'],
                'linked' => true,
            ];
        }

        $price[] = [
            'currencyId' => $currencyUuid,
            'gross' => $gross,
            'net' => (float) $priceData['price'],
            'linked' => true,
        ];

        return $price;
    }

    protected function getPrices(array $prices, array $converted): array
    {
        foreach ($prices as $key => &$price) {
            $price['toQty'] = null;
            if (isset($prices[$key + 1])
                && $prices[$key + 1]['all_groups'] === $price['all_groups']
                && $prices[$key + 1]['customer_group_id'] === $price['customer_group_id']
            ) {
                $price['toQty'] = ((int) $prices[$key + 1]['qty']) - 1;
            }
        }

        $newData = [];
        foreach ($prices as $price) {
            $customerGroupMapping = $this->mappingService->getMapping(
                $this->connectionId,
                DefaultEntities::CUSTOMER_GROUP,
                $price['customer_group_id'],
                $this->context
            );

            if ($customerGroupMapping === null || !isset($price['price'])) {
                continue;
            }
            $customerGroupUuid = $customerGroupMapping['entityUuid'];
            $this->mappingIds[] = $customerGroupMapping['id'];

            $mapping = $this->mappingService->getOrCreateMapping(
                $this->connectionId,
                DefaultEntities::RULE,
                'customerGroupRule_productPriceRule_' . $price['entity_id'] . '_' . $price['customer_group_id'],
                $this->context
            );
            $productPriceRuleUuid = $mapping['entityUuid'];
            $this->mappingIds[] = $mapping['id'];

            $mapping = $this->mappingService->getOrCreateMapping(
                $this->connectionId,
                DefaultEntities::RULE,
                'customerGroupRule_' . $price['customer_group_id'],
                $this->context
            );
            $priceRuleUuid = $mapping['entityUuid'];
            $this->mappingIds[] = $mapping['id'];

            $mapping = $this->mappingService->getOrCreateMapping(
                $this->connectionId,
                DefaultEntities::RULE,
                'customerGroupRule_orContainer_' . $price['customer_group_id'],
                $this->context
            );
            $orContainerUuid = $mapping['entityUuid'];
            $this->mappingIds[] = $mapping['id'];

            $mapping = $this->mappingService->getOrCreateMapping(
                $this->connectionId,
                DefaultEntities::RULE,
                'customerGroupRule_andContainer_' . $price['customer_group_id'],
                $this->context
            );
            $andContainerUuid = $mapping['entityUuid'];
            $this->mappingIds[] = $mapping['id'];

            $mapping = $this->mappingService->getOrCreateMapping(
                $this->connectionId,
                DefaultEntities::RULE,
                'customerGroupRule_condition_' . $price['customer_group_id'],
                $this->context
            );
            $conditionUuid = $mapping['entityUuid'];
            $this->mappingIds[] = $mapping['id'];

            $priceArray = $this->getPrice($price, $converted);

            if (empty($priceArray)) {
                continue;
            }

            $data = [
                'id' => $productPriceRuleUuid,
                'productId' => $converted['id'],
                'rule' => [
                    'id' => $priceRuleUuid,
                    'name' => $price['customerGroupCode'],
                    'priority' => 0,
                    'moduleTypes' => [
                        'types' => [
                            'price',
                        ],
                    ],
                    'conditions' => [
                        [
                            'id' => $orContainerUuid,
                            'type' => (new OrRule())->getName(),
                            'value' => [],
                        ],

                        [
                            'id' => $andContainerUuid,
                            'type' => (new AndRule())->getName(),
                            'parentId' => $orContainerUuid,
                            'value' => [],
                        ],

                        [
                            'id' => $conditionUuid,
                            'type' => 'customerCustomerGroup',
                            'parentId' => $andContainerUuid,
                            'position' => 1,
                            'value' => [
                                'customerGroupIds' => [
                                    $customerGroupUuid,
                                ],
                                'operator' => '=',
                            ],
                        ],
                    ],
                ],
                'price' => $priceArray,
                'quantityStart' => (int) $price['fromQty'],
                'quantityEnd' => $price['toQty'],
            ];

            $newData[] = $data;
        }

        return $newData;
    }

    protected function setProperties(array &$converted, array &$data): void
    {
        $properties = [];
        foreach ($data['properties'] as $property) {
            $propertyMapping = $this->mappingService->getMapping(
                $this->connectionId,
                DefaultEntities::PROPERTY_GROUP_OPTION,
                $property['optionId'],
                $this->context
            );

            if ($propertyMapping === null) {
                continue;
            }

            $properties[]['id'] = $propertyMapping['entityUuid'];
        }

        $converted['properties'] = $properties;
    }

    protected function setConfiguratorSettings(array &$converted, array &$data): void
    {
        $options = [];
        foreach ($data['configuratorSettings'] as $option) {
            $configuratorSettingMapping = $this->mappingService->getOrCreateMapping(
                $this->connectionId,
                MagentoDefaultEntities::PRODUCT_CONFIGURATOR_SETTING,
                $this->oldIdentifier . '_' . $option['optionId'],
                $this->context
            );
            $this->mappingIds[] = $configuratorSettingMapping['id'];

            $optionMapping = $this->mappingService->getMapping(
                $this->connectionId,
                DefaultEntities::PROPERTY_GROUP_OPTION,
                $option['optionId'],
                $this->context
            );

            if ($optionMapping === null) {
                continue;
            }

            $this->mappingIds[] = $optionMapping['id'];

            $optionElement = [
                'id' => $configuratorSettingMapping['entityUuid'],
                'productId' => $converted['id'],
                'optionId' => $optionMapping['entityUuid'],
            ];

            $options[] = $optionElement;
        }

        $converted['configuratorSettings'] = $options;
    }

    protected function setOptions(array &$converted, array &$data): void
    {
        $options = [];
        foreach ($data['options'] as $option) {
            $optionMapping = $this->mappingService->getOrCreateMapping(
                $this->connectionId,
                DefaultEntities::PROPERTY_GROUP_OPTION,
                $option['optionId'],
                $this->context
            );
            $this->mappingIds[] = $optionMapping['id'];

            $optionElement = [
                'id' => $optionMapping['entityUuid'],
            ];

            $options[] = $optionElement;
        }

        $converted['options'] = $options;
    }

    protected function getMedia(array $media, array $converted): array
    {
        $mediaObjects = [];
        $cover = null;
        foreach ($media as $mediaData) {
            $newProductMedia = [];
            $mapping = $this->mappingService->getOrCreateMapping(
                $this->connectionId,
                DefaultEntities::PRODUCT_MEDIA,
                $this->oldIdentifier . '_' . $mediaData['image'],
                $this->context
            );
            $newProductMedia['id'] = $mapping['entityUuid'];
            $this->mappingIds[] = $mapping['id'];
            $newProductMedia['productId'] = $converted['id'];
            $this->convertValue($newProductMedia, 'position', $mediaData, 'position', self::TYPE_INTEGER);

            $newMedia = [];
            $mapping = $this->mappingService->getOrCreateMapping(
                $this->connectionId,
                DefaultEntities::MEDIA,
                $mediaData['image'],
                $this->context
            );
            $newMedia['id'] = $mapping['entityUuid'];
            $this->mappingIds[] = $mapping['id'];

            if (!isset($mediaData['description'])) {
                $mediaData['description'] = $newMedia['id'];
            }

            $this->mediaFileService->saveMediaFile(
                [
                    'runId' => $this->runUuid,
                    'entity' => MediaDataSet::getEntity(),
                    'uri' => '/media/catalog/product' . $mediaData['image'],
                    'fileName' => $mediaData['description'],
                    'fileSize' => 0,
                    'mediaId' => $newMedia['id'],
                ]
            );

            $this->convertValue($newMedia, 'name', $mediaData, 'description');
            $newMedia['description'] = $newMedia['name'];

            $folderUuid = $this->mappingService->getDefaultFolderIdByEntity(DefaultEntities::PRODUCT, $this->migrationContext, $this->context);

            if ($folderUuid !== null) {
                $newMedia['mediaFolderId'] = $folderUuid;
            }

            $newProductMedia['media'] = $newMedia;
            $mediaObjects[] = $newProductMedia;

            if ($cover === null && (int) $mediaData['main'] === 1) {
                $cover = $newProductMedia;
            }
        }

        return ['media' => $mediaObjects, 'cover' => $cover];
    }

    protected function setVisibility(array &$converted, array &$data): void
    {
        $productId = $converted['id'];
        $visibilities = [];
        foreach ($data['visibility'] as $storeConfig) {
            $storeId = (int) $storeConfig['store_id'];
            $status = (int) $storeConfig['value'];

            if ($storeId === 0) {
                $this->setDefaultStoreVisibility($status, $productId, $visibilities);
                continue;
            }

            $this->setStoreVisibilities($storeId, $status, $productId, $visibilities);
        }

        $converted['visibilities'] = array_values($visibilities);
    }

    private function setDefaultStoreVisibility(int $status, string $productId, array &$visibilities): void
    {
        if ($status !== 1) {
            return;
        }

        $uuids = $this->mappingService->getUuidList(
            $this->connectionId,
            MagentoDefaultEntities::STORE_DEFAULT,
            '0',
            $this->context
        );

        foreach ($uuids as $uuid) {
            if (isset($visibilities[$uuid])) {
                continue;
            }

            $mapping = $this->mappingService->getOrCreateMapping(
                $this->connectionId,
                DefaultEntities::PRODUCT_VISIBILITY,
                $this->oldIdentifier . '_' . $uuid,
                $this->context
            );
            $this->mappingIds[] = $mapping['id'];

            $visibilities[$uuid] = [
                'id' => $mapping['entityUuid'],
                'productId' => $productId,
                'salesChannelId' => $uuid,
                'visibility' => ProductVisibilityDefinition::VISIBILITY_ALL,
            ];
        }
    }

    private function setStoreVisibilities(int $storeId, int $status, string $productId, array &$visibilities): void
    {
        $mapping = $this->mappingService->getMapping(
            $this->connectionId,
            MagentoDefaultEntities::STORE,
            (string) $storeId,
            $this->context
        );

        if ($mapping !== null) {
            $salesChannelUuid = $mapping['entityUuid'];
            if ($status !== 1) {
                unset($visibilities[$salesChannelUuid]);

                return;
            }

            if (isset($visibilities[$salesChannelUuid])) {
                return;
            }

            $this->mappingIds[] = $mapping['id'];
            $mapping = $this->mappingService->getOrCreateMapping(
                $this->connectionId,
                DefaultEntities::PRODUCT_VISIBILITY,
                $this->oldIdentifier . '_' . $storeId,
                $this->context
            );
            $this->mappingIds[] = $mapping['id'];
            $visibilities[$salesChannelUuid] = [
                'id' => $mapping['entityUuid'],
                'productId' => $productId,
                'salesChannelId' => $salesChannelUuid,
                'visibility' => ProductVisibilityDefinition::VISIBILITY_ALL,
            ];
        }
    }
}
