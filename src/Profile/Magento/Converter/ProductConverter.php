<?php declare(strict_types=1);
/*
 * (c) shopware AG <info@shopware.com>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Swag\MigrationMagento\Profile\Magento\Converter;

use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Rule\Container\AndRule;
use Shopware\Core\Framework\Rule\Container\OrRule;
use Shopware\Core\System\Language\LanguageEntity;
use Swag\MigrationMagento\Migration\Mapping\MagentoMappingServiceInterface;
use Swag\MigrationMagento\Profile\Magento\DataSelection\DataSet\MediaDataSet;
use Swag\MigrationMagento\Profile\Magento\DataSelection\DefaultEntities as MagentoDefaultEntities;
use SwagMigrationAssistant\Migration\Converter\ConvertStruct;
use SwagMigrationAssistant\Migration\DataSelection\DefaultEntities;
use SwagMigrationAssistant\Migration\Logging\Log\EmptyNecessaryFieldRunLog;
use SwagMigrationAssistant\Migration\Logging\Log\UnknownEntityLog;
use SwagMigrationAssistant\Migration\Logging\LoggingServiceInterface;
use SwagMigrationAssistant\Migration\Media\MediaFileServiceInterface;
use SwagMigrationAssistant\Migration\MigrationContextInterface;
use SwagMigrationAssistant\Profile\Shopware\Exception\ParentEntityForChildNotFoundException;

abstract class ProductConverter extends MagentoConverter
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

    /**
     * @var bool
     */
    private $priceIsGross;

    public function __construct(
        MagentoMappingServiceInterface $mappingService,
        LoggingServiceInterface $loggingService,
        MediaFileServiceInterface $mediaFileService
    ) {
        parent::__construct($mappingService, $loggingService);

        $this->mediaFileService = $mediaFileService;
    }

    public function getSourceIdentifier(array $data): string
    {
        return $data['entity_id'];
    }

    public function getMediaUuids(array $converted): ?array
    {
        $mediaUuids = [];
        foreach ($converted as $data) {
            if (isset($data['media'])) {
                foreach ($data['media'] as $media) {
                    if (!isset($media['media'])) {
                        continue;
                    }

                    $mediaUuids[] = $media['media']['id'];
                }
            }
        }

        return $mediaUuids;
    }

    public function convert(array $data, Context $context, MigrationContextInterface $migrationContext): ConvertStruct
    {
        $this->generateChecksum($data);
        $this->originalData = $data;
        $this->context = $context;
        $this->migrationContext = $migrationContext;
        $this->runUuid = $migrationContext->getRunUuid();
        $this->oldIdentifier = $data['entity_id'];
        unset($data['entity_id']);
        $converted = [];

        $connection = $migrationContext->getConnection();
        $this->connectionId = '';
        if ($connection !== null) {
            $this->connectionId = $connection->getId();
        }

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
        $this->priceIsGross = $data['priceIsGross'];
        unset($data['priceIsGross']);
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
        unset($data['price']);

        /*
         * Set main id
         */
        $this->mainMapping = $this->mappingService->getOrCreateMapping(
            $this->connectionId,
            DefaultEntities::PRODUCT,
            $this->oldIdentifier,
            $context,
            $this->checksum
        );
        $converted['id'] = $this->mainMapping['entityUuid'];

        if (isset($data['prices'])) {
            $converted['prices'] = $this->getPrices($data['prices'], $converted);
        }
        unset($data['prices']);

        $this->convertValue($converted, 'stock', $data, 'instock', self::TYPE_INTEGER);
        $this->convertValue($converted, 'weight', $data, 'weight', self::TYPE_FLOAT);
        $this->convertValue($converted, 'productNumber', $data, 'sku');

        if (isset($converted['keywords'])) {
            $converted['keywords'] = $this->trimValue($converted['keywords']);
        }

        $converted['active'] = false;
        if (isset($data['status']) && $data['status'] === '1') {
            $converted['active'] = true;
        }

        if (isset($data['minpurchase']) && ((int) $data['minpurchase']) !== 0) {
            $this->convertValue($converted, 'minPurchase', $data, 'minpurchase', self::TYPE_INTEGER);
        }
        if (isset($data['maxpurchase'])) {
            $this->convertValue($converted, 'maxPurchase', $data, 'maxpurchase', self::TYPE_INTEGER);
        }
        unset($data['minpurchase'], $data['maxpurchase']);

        /*
         * Set parent
         */
        if (isset($data['parentId'])) {
            $this->setParent($converted, $data);
        }
        unset($data['parentId']);

        if (isset($data['type_id']) && $data['type_id'] === 'configurable') {
            /*
             * Set configurator settings
             */
            if (isset($data['configuratorSettings'])) {
                $this->setConfiguratorSettings($converted, $data);
            }
            unset($data['configuratorSettings'], $data['type_id']);
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
        unset($data['visibility']);

        if (isset($data['categories'])) {
            $this->setCategories($converted, $data);
        }
        unset($data['categories']);

        if (isset($data['attributes'])) {
            $converted['customFields'] = $this->getAttributes($data['attributes'], (int) $data['attribute_set_id']);
            $mapping = $this->mappingService->getMapping(
                $this->connectionId,
                DefaultEntities::CUSTOM_FIELD_SET,
                $data['attribute_set_id'],
                $context
            );
            if ($mapping !== null) {
                $converted['customFieldSetSelectionActive'] = true;
                $converted['customFieldSets'] = [
                    ['id' => $mapping['entityUuid']],
                ];
                $this->mappingIds[] = $mapping['id'];
            }
        }
        unset($data['attributes']);

        if (isset($data['translations'])) {
            $converted['translations'] = $this->getTranslations(
                $data['translations'],
                [
                    'name' => 'name',
                    'description' => 'description',
                    'meta_title' => ['key' => 'metaTitle', 'maxChars' => 255],
                    'meta_description' => ['key' => 'metaDescription', 'maxChars' => 255],
                    'meta_keyword' => 'keywords',
                ],
                $context,
                (int) $data['attribute_set_id']
            );

            if (isset($converted['translations'])) {
                foreach ($converted['translations'] as &$translation) {
                    $translation['productId'] = $converted['id'];
                }
                unset($translation);
            }
        }
        unset($data['translations']);

        $language = $this->mappingService->getDefaultLanguage($this->context);
        $this->convertTranslationValue($language, $converted, 'name', $data, 'name');
        $this->convertTranslationValue($language, $converted, 'description', $data, 'description');
        $this->convertTranslationValue($language, $converted, 'metaTitle', $data, 'meta_title');
        $this->convertTranslationValue($language, $converted, 'metaDescription', $data, 'meta_description');
        $this->convertTranslationValue($language, $converted, 'keywords', $data, 'meta_keyword');

        if (isset($converted['metaTitle'])) {
            $converted['metaTitle'] = $this->trimValue($converted['metaTitle']);
        }
        if (isset($converted['metaDescription'])) {
            $converted['metaDescription'] = $this->trimValue($converted['metaDescription']);
        }

        $this->updateMainMapping($migrationContext, $context);

        // Not used keys
        unset(
            $data['entity_type_id'],
            $data['attribute_set_id'],
            $data['has_options'],
            $data['required_options'],
            $data['created_at'],
            $data['updated_at'],
            $data['stockmin'],
            $data['allow_message'],
            $data['cost'],
            $data['country_of_manufacture'],
            $data['custom_design'],
            $data['custom_design_from'],
            $data['custom_design_to'],
            $data['custom_layout_update'],
            $data['email_template'],
            $data['gallery'],
            $data['gift_message_available'],
            $data['gift_wrapping_available'],
            $data['gift_wrapping_price'],
            $data['group_price'],
            $data['image'],
            $data['image_label'],
            $data['is_recurring'],
            $data['is_redeemable'],
            $data['lifetime'],
            $data['media_gallery'],
            $data['minimal_price'],
            $data['msrp'],
            $data['msrp_display_actual_price_type'],
            $data['msrp_enabled'],
            $data['news_from_date'],
            $data['news_to_date'],
            $data['old_id'],
            $data['open_amount_max'],
            $data['open_amount_min'],
            $data['options_container'],
            $data['page_layout'],
            $data['price_view'],
            $data['recurring_profile'],
            $data['short_description'],
            $data['small_image'],
            $data['small_image_label'],
            $data['special_from_date'],
            $data['special_price'],
            $data['special_to_date'],
            $data['status'],
            $data['thumbnail'],
            $data['thumbnail_label'],
            $data['tier_price'],
            $data['url_key'],
            $data['url_path'],
            $data['use_config_allow_message'],
            $data['use_config_email_template'],
            $data['use_config_is_redeemable'],
            $data['use_config_lifetime']
        );

        $resultData = $data;
        if (empty($resultData)) {
            $resultData = null;
        }

        return new ConvertStruct($converted, $resultData, $this->mainMapping['id']);
    }

    protected function convertTranslationValue(
        ?LanguageEntity $defaultLanguage,
        array &$newData,
        string $newKey,
        array &$sourceData,
        string $sourceKey,
        string $castType = self::TYPE_STRING,
        bool $unset = true
    ): void {
        if ($defaultLanguage === null || !isset($newData['translations'][$defaultLanguage->getId()][$newKey])) {
            $this->convertValue($newData, $newKey, $sourceData, $sourceKey, $castType, $unset);
        }

        if ($unset) {
            unset($sourceData[$sourceKey]);
        }
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
        if ($taxClassId === '0') {
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

        if ($this->priceIsGross === true) {
            $netPrice = \round((float) $priceData['price'] / (1 + $taxRate / 100), $this->context->getRounding()->getDecimals());
            $grossPrice = (float) $priceData['price'];
        } else {
            $netPrice = (float) $priceData['price'];
            $grossPrice = \round((float) $priceData['price'] * (1 + $taxRate / 100), $this->context->getRounding()->getDecimals());
        }
        $price = [];
        if ($currencyUuid !== Defaults::CURRENCY) {
            $price[] = [
                'currencyId' => Defaults::CURRENCY,
                'gross' => $grossPrice,
                'net' => $netPrice,
                'linked' => true,
            ];
        }
        $price[] = [
            'currencyId' => $currencyUuid,
            'gross' => $grossPrice,
            'net' => $netPrice,
            'linked' => true,
        ];

        if (isset($priceData['special_price']) && ((float) $priceData['special_price']) > 0) {
            $specialPrice = (float) $priceData['special_price'];
            if ($this->priceIsGross === true) {
                $specialPriceNet = \round($specialPrice / (1 + $taxRate / 100), $this->context->getRounding()->getDecimals());
                $specialPriceGross = $specialPrice;
            } else {
                $specialPriceNet = $specialPrice;
                $specialPriceGross = \round($specialPrice * (1 + $taxRate / 100), $this->context->getRounding()->getDecimals());
            }
            foreach ($price as &$productPrice) {
                $productPrice['listPrice']['gross'] = $productPrice['gross'];
                $productPrice['listPrice']['net'] = $productPrice['net'];
                $productPrice['listPrice']['linked'] = $productPrice['linked'];

                $productPrice['gross'] = $specialPriceGross;
                $productPrice['net'] = $specialPriceNet;
            }
        }

        return $price;
    }

    /**
     * @psalm-suppress PossiblyNullArrayAccess
     */
    protected function getPrices(array $prices, array $converted): array
    {
        foreach ($prices as $key => &$price) {
            $key = (int) $key;
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

            if (!isset($mediaData['description']) || empty($mediaData['description'])) {
                $mediaData['description'] = $newMedia['id'];

                $fileMatches = [];
                \preg_match('/^\/(.+\/)*(.+)\..+$/', $mediaData['image'], $fileMatches);
                if (isset($fileMatches[2])) {
                    $mediaData['description'] = $fileMatches[2];
                }
            }

            $this->mediaFileService->saveMediaFile(
                [
                    'runId' => $this->runUuid,
                    'entity' => MediaDataSet::getEntity(),
                    'uri' => MediaConverter::PRODUCT_MEDIA_PATH . $mediaData['image'],
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

        if ($cover === null && !empty($mediaObjects)) {
            $cover = $mediaObjects[0];
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

        $converted['visibilities'] = \array_values($visibilities);
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
                $this->oldIdentifier . '_' . $salesChannelUuid,
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
