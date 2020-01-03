<?php declare(strict_types=1);
/*
 * (c) shopware AG <info@shopware.com>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Swag\MigrationMagento\Profile\Magento\Media;

use Shopware\Core\Content\Media\Exception\DuplicatedMediaFileNameException;
use Shopware\Core\Content\Media\File\FileSaver;
use Shopware\Core\Content\Media\File\MediaFile;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Uuid\Uuid;
use Swag\MigrationMagento\Profile\Magento\DataSelection\DataSet\MediaDataSet;
use Swag\MigrationMagento\Profile\Magento\Gateway\Local\Magento19LocalGateway;
use Swag\MigrationMagento\Profile\Magento\Magento19Profile;
use SwagMigrationAssistant\Exception\NoFileSystemPermissionsException;
use SwagMigrationAssistant\Migration\DataSelection\DefaultEntities;
use SwagMigrationAssistant\Migration\Logging\Log\CannotGetFileRunLog;
use SwagMigrationAssistant\Migration\Logging\Log\ExceptionRunLog;
use SwagMigrationAssistant\Migration\Logging\LoggingServiceInterface;
use SwagMigrationAssistant\Migration\Media\MediaFileProcessorInterface;
use SwagMigrationAssistant\Migration\Media\MediaProcessWorkloadStruct;
use SwagMigrationAssistant\Migration\Media\SwagMigrationMediaFileEntity;
use SwagMigrationAssistant\Migration\MigrationContextInterface;

class LocalMediaProcessor implements MediaFileProcessorInterface
{
    /**
     * @var EntityRepositoryInterface
     */
    private $mediaFileRepo;

    /**
     * @var EntityRepositoryInterface
     */
    private $mediaRepo;

    /**
     * @var FileSaver
     */
    private $fileSaver;

    /**
     * @var LoggingServiceInterface
     */
    private $loggingService;

    public function __construct(
        EntityRepositoryInterface $migrationMediaFileRepo,
        EntityRepositoryInterface $mediaRepo,
        FileSaver $fileSaver,
        LoggingServiceInterface $loggingService
    ) {
        $this->mediaFileRepo = $migrationMediaFileRepo;
        $this->mediaRepo = $mediaRepo;
        $this->fileSaver = $fileSaver;
        $this->loggingService = $loggingService;
    }

    public function supports(MigrationContextInterface $migrationContext): bool
    {
        return $migrationContext->getProfile() instanceof Magento19Profile
            && $migrationContext->getGateway()->getName() === Magento19LocalGateway::GATEWAY_NAME
            && $migrationContext->getDataSet()::getEntity() === MediaDataSet::getEntity();
    }

    public function process(
        MigrationContextInterface $migrationContext,
        Context $context,
        array $workload,
        int $fileChunkByteSize
    ): array {
        $mappedWorkload = [];
        $runId = $migrationContext->getRunUuid();

        foreach ($workload as $work) {
            $mappedWorkload[$work->getMediaId()] = $work;
        }

        if (!is_dir('_temp') && !mkdir('_temp') && !is_dir('_temp')) {
            $exception = new NoFileSystemPermissionsException();
            $this->loggingService->addLogEntry(new ExceptionRunLog(
                $runId,
                DefaultEntities::MEDIA,
                $exception
            ));
            $this->loggingService->saveLogging($context);

            return $workload;
        }

        /** @var SwagMigrationMediaFileEntity[] $media */
        $media = $this->getMediaFiles(array_keys($mappedWorkload), $migrationContext->getRunUuid(), $context);
        $mappedWorkload = $this->getMediaPathMapping($media, $mappedWorkload, $migrationContext);

        return $this->copyMediaFiles($media, $mappedWorkload, $migrationContext, $context);
    }

    private function getMediaFiles(array $mediaIds, string $runId, Context $context): array
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsAnyFilter('mediaId', $mediaIds));
        $criteria->addFilter(new EqualsFilter('runId', $runId));
        $mediaSearchResult = $this->mediaFileRepo->search($criteria, $context);

        return $mediaSearchResult->getElements();
    }

    /**
     * @param MediaProcessWorkloadStruct[] $mappedWorkload
     *
     * @return MediaProcessWorkloadStruct[]
     */
    private function getMediaPathMapping(array $media, array $mappedWorkload, MigrationContextInterface $migrationContext): array
    {
        /** @var SwagMigrationMediaFileEntity $mediaFile */
        foreach ($media as $mediaFile) {
            $mappedWorkload[$mediaFile->getMediaId()]->setAdditionalData(['path' => $mediaFile->getUri()]);
        }

        return $mappedWorkload;
    }

    /**
     * @param MediaProcessWorkloadStruct[] $mappedWorkload
     *
     * @return MediaProcessWorkloadStruct[]
     */
    private function copyMediaFiles(
        array $media,
        array $mappedWorkload,
        MigrationContextInterface $migrationContext,
        Context $context
    ): array {
        $processedMedia = [];
        $failureUuids = [];

        /** @var SwagMigrationMediaFileEntity $mediaFile */
        foreach ($media as $mediaFile) {
            $sourcePath = $migrationContext->getConnection()->getCredentialFields()['installationRoot'] . $mappedWorkload[$mediaFile->getMediaId()]->getAdditionalData()['path'];

            $fileExtension = pathinfo($sourcePath, PATHINFO_EXTENSION);
            $filePath = sprintf('_temp/%s.%s', $mediaFile->getId(), $fileExtension);

            if (copy($sourcePath, $filePath)) {
                $fileSize = filesize($filePath);
                $mappedWorkload[$mediaFile->getMediaId()]->setState(MediaProcessWorkloadStruct::FINISH_STATE);

                $this->persistFileToMedia($filePath, $mediaFile, $fileSize, $fileExtension, $context);
                unlink($filePath);
            } else {
                $mappedWorkload[$mediaFile->getMediaId()]->setState(MediaProcessWorkloadStruct::ERROR_STATE);
                $this->loggingService->addLogEntry(new CannotGetFileRunLog(
                    $mappedWorkload[$mediaFile->getMediaId()]->getRunId(),
                    DefaultEntities::MEDIA,
                    $mediaFile->getMediaId(),
                    $sourcePath
                ));
                $failureUuids[$mediaFile->getId()] = $mediaFile->getMediaId();
            }
            $processedMedia[] = $mediaFile->getMediaId();
        }
        $this->setProcessedFlag($migrationContext->getRunUuid(), $context, $processedMedia, $failureUuids);
        $this->loggingService->saveLogging($context);

        return array_values($mappedWorkload);
    }

    private function persistFileToMedia(
        string $filePath,
        SwagMigrationMediaFileEntity $media,
        int $fileSize,
        string $fileExtension,
        Context $context
    ): void {
        $mimeType = mime_content_type($filePath);
        $mediaFile = new MediaFile($filePath, $mimeType, $fileExtension, $fileSize);

        try {
            $this->fileSaver->persistFileToMedia($mediaFile, $media->getFileName(), $media->getMediaId(), $context);
        } catch (DuplicatedMediaFileNameException $e) {
            $this->fileSaver->persistFileToMedia($mediaFile, $media->getFileName() . mb_substr(Uuid::randomHex(), 0, 5), $media->getMediaId(), $context);
        }
    }

    private function setProcessedFlag(string $runId, Context $context, array $finishedUuids, array $failureUuids): void
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsAnyFilter('mediaId', $finishedUuids));
        $criteria->addFilter(new EqualsFilter('runId', $runId));
        $mediaFiles = $this->mediaFileRepo->search($criteria, $context);

        $updateableMediaEntities = [];
        foreach ($mediaFiles->getElements() as $mediaFile) {
            /* @var SwagMigrationMediaFileEntity $mediaFile */
            $updateableMediaEntities[] = [
                'id' => $mediaFile->getId(),
                'processed' => true,
            ];
        }

        if (!empty($failureUuids)) {
            $mediaFileIds = [];
            $mediaIds = [];
            foreach ($failureUuids as $mediaFileId => $mediaId) {
                $mediaFileIds[] = [
                    'id' => $mediaFileId,
                ];
                $mediaIds[] = [
                    'id' => $mediaId,
                ];
            }

            $this->mediaFileRepo->delete($mediaFileIds, $context);
            $this->mediaRepo->delete($mediaIds, $context);
        }

        if (empty($updateableMediaEntities)) {
            return;
        }

        $this->mediaFileRepo->update($updateableMediaEntities, $context);
    }
}
