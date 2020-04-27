<?php declare(strict_types=1);
/*
 * (c) shopware AG <info@shopware.com>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Swag\MigrationMagento\Profile\Magento\Media;

use GuzzleHttp\Client;
use GuzzleHttp\Promise;
use Shopware\Core\Content\Media\Exception\DuplicatedMediaFileNameException;
use Shopware\Core\Content\Media\File\FileSaver;
use Shopware\Core\Content\Media\File\MediaFile;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Uuid\Uuid;
use Swag\MigrationMagento\Exception\MediaPathNotReachableException;
use Swag\MigrationMagento\Profile\Magento\DataSelection\DataSet\MediaDataSet;
use Swag\MigrationMagento\Profile\Magento19\Magento19Profile;
use SwagMigrationAssistant\Exception\NoFileSystemPermissionsException;
use SwagMigrationAssistant\Migration\DataSelection\DefaultEntities;
use SwagMigrationAssistant\Migration\Logging\Log\CannotGetFileRunLog;
use SwagMigrationAssistant\Migration\Logging\Log\ExceptionRunLog;
use SwagMigrationAssistant\Migration\Logging\LoggingServiceInterface;
use SwagMigrationAssistant\Migration\Media\MediaFileProcessorInterface;
use SwagMigrationAssistant\Migration\Media\MediaProcessWorkloadStruct;
use SwagMigrationAssistant\Migration\Media\SwagMigrationMediaFileEntity;
use SwagMigrationAssistant\Migration\MessageQueue\Handler\ProcessMediaHandler;
use SwagMigrationAssistant\Migration\MigrationContextInterface;

class LocalMediaProcessor implements MediaFileProcessorInterface
{
    /**
     * @var EntityRepositoryInterface
     */
    protected $mediaFileRepo;

    /**
     * @var EntityRepositoryInterface
     */
    protected $mediaRepo;

    /**
     * @var FileSaver
     */
    protected $fileSaver;

    /**
     * @var LoggingServiceInterface
     */
    protected $loggingService;

    /**
     * @var MigrationContextInterface
     */
    protected $migrationContext;

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
            && $migrationContext->getDataSet()::getEntity() === MediaDataSet::getEntity();
    }

    public function process(
        MigrationContextInterface $migrationContext,
        Context $context,
        array $workload,
        int $fileChunkByteSize
    ): array {
        $mappedWorkload = [];
        $this->migrationContext = $migrationContext;
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
        $mappedWorkload = $this->getMediaPathMapping($media, $mappedWorkload);

        $installationRoot = $this->getInstallationRoot($migrationContext);

        if ($installationRoot === '' || is_dir($installationRoot) === false) {
            $shopUrl = $this->getShopUrl($migrationContext);

            if ($shopUrl === '') {
                $exception = new MediaPathNotReachableException($installationRoot);
                $this->loggingService->addLogEntry(new ExceptionRunLog(
                    $runId,
                    DefaultEntities::MEDIA,
                    $exception
                ));
                $this->loggingService->saveLogging($context);

                return $workload;
            }

            return $this->downloadMediaFiles($media, $shopUrl, $mappedWorkload, $workload, $migrationContext, $context);
        }

        return $this->copyMediaFiles($media, $mappedWorkload, $migrationContext, $context);
    }

    /**
     * Start all the download requests for the media in parallel (async) and return the promise array.
     *
     * @param SwagMigrationMediaFileEntity[] $media
     * @param MediaProcessWorkloadStruct[]   $mappedWorkload
     */
    protected function doMediaDownloadRequests(array $media, array &$mappedWorkload, Client $client, string $shopUrl): array
    {
        $promises = [];
        foreach ($media as $mediaFile) {
            $uuid = mb_strtolower($mediaFile->getMediaId());
            $additionalData = [];
            $additionalData['uri'] = $shopUrl . $mediaFile->getUri();

            $additionalData['file_size'] = $mediaFile->getFileSize();
            $additionalData['file_name'] = $mediaFile->getFileName();
            $mappedWorkload[$uuid]->setAdditionalData($additionalData);

            $promise = $this->doNormalDownloadRequest($mappedWorkload[$uuid], $client);

            if ($promise !== null) {
                $promises[$uuid] = $promise;
            }
        }

        return $promises;
    }

    protected function doNormalDownloadRequest(MediaProcessWorkloadStruct $workload, Client $client): ?Promise\PromiseInterface
    {
        $additionalData = $workload->getAdditionalData();

        try {
            $promise = $client->getAsync(
                $additionalData['uri'],
                [
                    'query' => ['alt' => 'media'],
                ]
            );

            $workload->setCurrentOffset($additionalData['file_size']);
            $workload->setState(MediaProcessWorkloadStruct::FINISH_STATE);
        } catch (\Exception $exception) {
            $promise = null;
            $workload->setErrorCount($workload->getErrorCount() + 1);
        }

        return $promise;
    }

    protected function getInstallationRoot(MigrationContextInterface $migrationContext): string
    {
        $connection = $migrationContext->getConnection();
        if ($connection === null) {
            return '';
        }

        $credentials = $connection->getCredentialFields();
        if (!isset($credentials['installationRoot']) || $credentials['installationRoot'] === '') {
            return '';
        }
        $installRoot = $credentials['installationRoot'];
        $installRoot = ltrim($installRoot, '/');
        $installRoot = rtrim($installRoot, '/');
        $installRoot = '/' . $installRoot;

        return $installRoot;
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
    private function getMediaPathMapping(array $media, array $mappedWorkload): array
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
            $sourcePath = $this->getInstallationRoot($migrationContext) . $mappedWorkload[$mediaFile->getMediaId()]->getAdditionalData()['path'];

            $fileExtension = pathinfo($sourcePath, PATHINFO_EXTENSION);
            $filePath = sprintf('_temp/%s.%s', $mediaFile->getId(), $fileExtension);

            if (copy($sourcePath, $filePath)) {
                $fileSize = filesize($filePath);
                $mappedWorkload[$mediaFile->getMediaId()]->setState(MediaProcessWorkloadStruct::FINISH_STATE);

                $this->persistFileToMedia($filePath, $mediaFile->getMediaId(), $mediaFile->getFileName(), $fileSize, $fileExtension, $context);
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
        string $mediaId,
        string $fileName,
        int $fileSize,
        string $fileExtension,
        Context $context
    ): void {
        $mimeType = mime_content_type($filePath);
        $mediaFile = new MediaFile($filePath, $mimeType, $fileExtension, $fileSize);

        try {
            $this->fileSaver->persistFileToMedia($mediaFile, $fileName, $mediaId, $context);
        } catch (DuplicatedMediaFileNameException $e) {
            $this->fileSaver->persistFileToMedia($mediaFile, $fileName . mb_substr(Uuid::randomHex(), 0, 5), $mediaId, $context);
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
            if (!in_array($mediaFile->getMediaId(), $failureUuids, true)) {
                $updateableMediaEntities[] = [
                    'id' => $mediaFile->getId(),
                    'processed' => true,
                ];
            }
        }

        if (empty($updateableMediaEntities)) {
            return;
        }

        $this->mediaFileRepo->update($updateableMediaEntities, $context);
    }

    private function getShopUrl(MigrationContextInterface $migrationContext): string
    {
        $connection = $migrationContext->getConnection();
        if ($connection === null) {
            return '';
        }

        $credentials = $connection->getCredentialFields();
        if (!isset($credentials['shopUrl'])) {
            return '';
        }

        return rtrim($credentials['shopUrl'], '/');
    }

    private function downloadMediaFiles(
        array $media,
        string $shopUrl,
        array $mappedWorkload,
        array $workload,
        MigrationContextInterface $migrationContext,
        Context $context
    ): array {
        //Do download requests and store the promises
        $client = new Client([
            'verify' => false,
        ]);
        $promises = $this->doMediaDownloadRequests($media, $mappedWorkload, $client, $shopUrl);

        // Wait for the requests to complete, even if some of them fail
        /** @var array $results */
        $results = Promise\settle($promises)->wait();

        //handle responses
        $failureUuids = [];
        $finishedUuids = [];
        foreach ($results as $uuid => $result) {
            $state = $result['state'];
            $additionalData = $mappedWorkload[$uuid]->getAdditionalData();

            $oldWorkloadSearchResult = array_filter(
                $workload,
                function (MediaProcessWorkloadStruct $work) use ($uuid) {
                    return $work->getMediaId() === $uuid;
                }
            );

            /** @var MediaProcessWorkloadStruct $oldWorkload */
            $oldWorkload = array_pop($oldWorkloadSearchResult);

            if ($state !== 'fulfilled') {
                $mappedWorkload[$uuid] = $oldWorkload;
                $mappedWorkload[$uuid]->setAdditionalData($additionalData);
                $mappedWorkload[$uuid]->setErrorCount($mappedWorkload[$uuid]->getErrorCount() + 1);

                if ($mappedWorkload[$uuid]->getErrorCount() > ProcessMediaHandler::MEDIA_ERROR_THRESHOLD) {
                    $failureUuids[] = $uuid;
                    $mappedWorkload[$uuid]->setState(MediaProcessWorkloadStruct::ERROR_STATE);
                    $this->loggingService->addLogEntry(new CannotGetFileRunLog(
                        $mappedWorkload[$uuid]->getRunId(),
                        DefaultEntities::MEDIA,
                        $mappedWorkload[$uuid]->getMediaId(),
                        $mappedWorkload[$uuid]->getAdditionalData()['uri']
                    ));
                }

                continue;
            }

            $response = $result['value'];
            $fileExtension = pathinfo($additionalData['uri'], PATHINFO_EXTENSION);
            $filePath = sprintf('_temp/%s.%s', $uuid, $fileExtension);

            $fileHandle = fopen($filePath, 'ab');
            fwrite($fileHandle, $response->getBody()->getContents());
            $fileSize = (int) filesize($filePath);
            fclose($fileHandle);

            if ($mappedWorkload[$uuid]->getState() === MediaProcessWorkloadStruct::FINISH_STATE) {
                $this->persistFileToMedia(
                    $filePath,
                    $uuid,
                    $additionalData['file_name'],
                    $fileSize,
                    $fileExtension,
                    $context
                );
                unlink($filePath);
                $finishedUuids[] = $uuid;
            }

            if ($oldWorkload->getErrorCount() === $mappedWorkload[$uuid]->getErrorCount()) {
                $mappedWorkload[$uuid]->setErrorCount(0);
            }
        }

        $this->setProcessedFlag($migrationContext->getRunUuid(), $context, $finishedUuids, $failureUuids);
        $this->loggingService->saveLogging($context);

        return array_values($mappedWorkload);
    }
}
