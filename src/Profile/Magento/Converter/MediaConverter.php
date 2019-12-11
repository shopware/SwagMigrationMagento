<?php declare(strict_types=1);
/*
 * (c) shopware AG <info@shopware.com>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Swag\MigrationMagento\Profile\Magento\Converter;

use Shopware\Core\Framework\Context;
use Swag\MigrationMagento\Profile\Magento\DataSelection\DataSet\MediaDataSet;
use Swag\MigrationMagento\Profile\Magento\Magento19Profile;
use SwagMigrationAssistant\Migration\Converter\ConvertStruct;
use SwagMigrationAssistant\Migration\DataSelection\DefaultEntities;
use SwagMigrationAssistant\Migration\Logging\LoggingServiceInterface;
use SwagMigrationAssistant\Migration\Mapping\MappingServiceInterface;
use SwagMigrationAssistant\Migration\Media\MediaFileServiceInterface;
use SwagMigrationAssistant\Migration\MigrationContextInterface;

class MediaConverter extends MagentoConverter
{
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
            && $migrationContext->getDataSet()::getEntity() === MediaDataSet::getEntity();
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
        $this->connectionId = $migrationContext->getConnection()->getId();

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
        preg_match('/^\/(.+\/)*(.+)\..+$/', $data['path'], $fileMatches);
        $fileName = $converted['id'];
        if (isset($fileMatches[2])) {
            $fileName = $fileMatches[2];
        }

        $this->mediaFileService->saveMediaFile(
            [
                'runId' => $migrationContext->getRunUuid(),
                'entity' => MediaDataSet::getEntity(),
                'uri' => '/media/catalog/product' . $data['path'],
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

        if (empty($data)) {
            $data = null;
        }

        return new ConvertStruct($converted, $data, $this->mainMapping['id']);
    }
}
