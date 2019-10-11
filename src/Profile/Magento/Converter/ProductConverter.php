<?php declare(strict_types=1);

namespace Swag\MigrationMagento\Profile\Magento\Converter;

use Shopware\Core\Framework\Context;
use Swag\MigrationMagento\Profile\Magento\DataSelection\DataSet\ProductDataSet;
use Swag\MigrationMagento\Profile\Magento\Magento19Profile;
use SwagMigrationAssistant\Migration\Converter\ConvertStruct;
use SwagMigrationAssistant\Migration\DataSelection\DefaultEntities;
use SwagMigrationAssistant\Migration\Logging\LoggingServiceInterface;
use SwagMigrationAssistant\Migration\Mapping\MappingServiceInterface;
use SwagMigrationAssistant\Migration\Media\MediaFileServiceInterface;
use SwagMigrationAssistant\Migration\MigrationContextInterface;

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

    public function __construct(
        MappingServiceInterface $mappingService,
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
        $converted = [];
        // produktypen
        // grouped > auch als Variante zu behandeln
        // simple > normal
        // configurable product > variante
        // bundle > not supported yet
        // virtual / downloadable > not supported yet
        $this->mainMapping = $this->mappingService->getOrCreateMapping(
            $migrationContext->getConnection()->getId(),
            DefaultEntities::PRODUCT,
            $data['entity_uuid'],
            $context,
            $this->checksum
        );

        if (isset($data['manufacturer'])) {
            $converted['manufacturer'] = $this->getManufacturer($data['manufacturer']);
        }
        unset($data['manufacturer']);

        $converted['taxId'] = $this->getTax($data['tax_class_id']);
        unset($data['tax_class_id']);

        $converted['price'] = (float) $data['price'];
        $converted['stock'] = (int) $data['instock'];
        $converted['productNumber'] = $data['sku'];

        // price
        // productNumber
        // stock

        $this->updateMainMapping($migrationContext, $context);

        return new ConvertStruct($converted, null, $this->mainMapping['id']);
    }

    private function getManufacturer(string $manufacturer)
    {
        $mapping = $this->mappingService->getOrCreateMapping(
            $this->connectionId,
            DefaultEntities::PRODUCT_MANUFACTURER,
            $manufacturer,
            $this->context
        );
        $manufacturer['id'] = $mapping['entityUuid'];

        return $manufacturer;
    }

    private function getTax(string $taxClassId): string
    {
        $mapping = $this->mappingService->getMapping(
            $this->connectionId,
            DefaultEntities::TAX,
            $taxClassId,
            $this->context
        );

        if ($mapping === null) {
            // todo: implement exception class
            throw new \Exception('Unknown tax');
        }
        $this->mappingIds[] = $mapping['uuid'];

        return $mapping['entityUuid'];
    }
}
