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
use SwagMigrationAssistant\Migration\Converter\ConvertStruct;
use SwagMigrationAssistant\Migration\DataSelection\DefaultEntities;
use SwagMigrationAssistant\Migration\Logging\LoggingServiceInterface;
use SwagMigrationAssistant\Migration\Media\MediaFileServiceInterface;
use SwagMigrationAssistant\Migration\MigrationContextInterface;

abstract class MediaConverter extends MagentoConverter
{
    public const PRODUCT_MEDIA_PATH = '/media/catalog/product';

    /**
     * @var string
     */
    protected $connectionId;

    /**
     * @var MediaFileServiceInterface
     */
    protected $mediaFileService;

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
        return $data['path'];
    }

    public function getMediaUuids(array $converted): ?array
    {
        $mediaUuids = [];
        foreach ($converted as $data) {
            $mediaUuids[] = $data['id'];
        }

        return $mediaUuids;
    }

    public function convert(array $data, Context $context, MigrationContextInterface $migrationContext): ConvertStruct
    {
        $this->generateChecksum($data);
        $connection = $migrationContext->getConnection();
        $this->connectionId = '';
        if ($connection !== null) {
            $this->connectionId = $connection->getId();
        }

        $converted = [];
        $this->mainMapping = $this->mappingService->getOrCreateMapping(
            $this->connectionId,
            DefaultEntities::MEDIA,
            $data['path'],
            $context,
            $this->checksum
        );
        $converted['id'] = $this->mainMapping['entityUuid'];

        $fileMatches = [];
        \preg_match('/^\/(.+\/)*(.+)\..+$/', $data['path'], $fileMatches);
        $fileName = $converted['id'];
        if (isset($fileMatches[2])) {
            $fileName = $fileMatches[2];
        }

        $this->mediaFileService->saveMediaFile(
            [
                'runId' => $migrationContext->getRunUuid(),
                'entity' => MediaDataSet::getEntity(),
                'uri' => self::PRODUCT_MEDIA_PATH . $data['path'],
                'fileName' => $fileName,
                'fileSize' => 0,
                'mediaId' => $converted['id'],
            ]
        );
        unset($data['path']);

        if (isset($data['label'])) {
            $this->convertValue($converted, 'title', $data, 'label');
        }
        unset($data['label']);

        $albumUuid = $this->mappingService->getDefaultFolderIdByEntity(DefaultEntities::PRODUCT, $migrationContext, $context);
        if ($albumUuid !== null) {
            $converted['mediaFolderId'] = $albumUuid;
        }

        $this->updateMainMapping($migrationContext, $context);

        $resultData = $data;
        if (empty($resultData)) {
            $resultData = null;
        }

        return new ConvertStruct($converted, $resultData, $this->mainMapping['id']);
    }
}
