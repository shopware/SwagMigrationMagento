<?php declare(strict_types=1);

namespace Swag\MigrationMagento\Profile\Magento\Converter;

use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Rule\Container\AndRule;
use Shopware\Core\Framework\Rule\Container\OrRule;
use Swag\MigrationMagento\Migration\Mapping\MagentoMappingServiceInterface;
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
        $this->context = $context;
        $this->connectionId = $migrationContext->getConnection()->getId();
        $this->runUuid = $migrationContext->getRunUuid();
        $this->oldIdentifier = $data['entity_id'];

        // produktypen
        // grouped > auch als Variante zu behandeln
        // simple > normal
        // configurable product > variante
        // bundle > not supported yet > haben keine Steuersätze und sind auch eine Art container > noch nicht migrieren
        // virtual / downloadable > not supported yet
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

            return new ConvertStruct(null, $data);
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

            return new ConvertStruct(null, $data);
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

            return new ConvertStruct(null, $data);
        }

        $converted['price'] = $this->getPrice($data, $converted);

        if (empty($converted['price'])) {
            $this->loggingService->addLogEntry(new EmptyNecessaryFieldRunLog(
                $this->runUuid,
                DefaultEntities::PRODUCT,
                $this->oldIdentifier,
                'currency'
            ));

            return new ConvertStruct(null, $data);
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

        /*
         * Set configurator settings
         */
        if (isset($data['configuratorSettings'])) {
            $this->setConfiguratorSettings($converted, $data);
        }

        /*
         * Set options
         */
        if (isset($data['options'])) {
            $this->setOptions($converted, $data);
        }

        $this->updateMainMapping($migrationContext, $context);

        return new ConvertStruct($converted, null, $this->mainMapping['id']);
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
}
